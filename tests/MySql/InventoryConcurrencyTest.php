<?php

namespace Tests\MySql;

use App\Enums\InventoryReservationState;
use App\Models\Category;
use App\Models\InventoryLedgerEntry;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.mysql.database');
        if (! preg_match('/^[A-Za-z0-9_]+_test$/', $database)) {
            $this->fail('MySQL concurrency tests require a dedicated database ending in "_test".');
        }

        $this->createTestDatabase($database);
        DB::purge('mysql');
        Artisan::call('migrate:fresh', ['--database' => 'mysql', '--force' => true]);
    }

    public function test_final_unit_can_only_be_reserved_once(): void
    {
        $product = $this->makeProduct(1);
        app(InventoryService::class)->ensureBalance($product);
        $first = $this->makeOrderItem($product);
        $second = $this->makeOrderItem($product);

        $results = $this->race(
            ['reserve', (string) $first->id, 'race:final-unit:first'],
            ['reserve', (string) $second->id, 'race:final-unit:second'],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $this->assertSame(1, collect($results)->where('ok', false)->count());
        $balance = $product->inventoryBalance()->firstOrFail();
        $this->assertSame(1, $balance->reserved);
        $this->assertSame(0, $balance->available());
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_duplicate_commit_is_idempotent_under_race(): void
    {
        $product = $this->makeProduct(1);
        app(InventoryService::class)->ensureBalance($product);
        $item = $this->makeOrderItem($product);
        $key = 'race:duplicate-commit';

        $results = $this->race(
            ['commit_cod', (string) $item->id, $key],
            ['commit_cod', (string) $item->id, $key],
        );

        $this->assertTrue(collect($results)->every('ok'));
        $balance = $product->inventoryBalance()->firstOrFail();
        $this->assertSame(1, $balance->committed);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('idempotency_key', $key)->count());
    }

    public function test_payment_commit_wins_safely_against_expiry(): void
    {
        $product = $this->makeProduct(1);
        app(InventoryService::class)->ensureBalance($product);
        $item = $this->makeOrderItem($product, 'razorpay');
        app(InventoryService::class)->reserve($item, now()->subMinute(), 'race:payment-expiry:reserve');

        $results = $this->race(
            ['commit_reserved', (string) $item->id, 'race:payment-expiry:commit'],
            ['expire', (string) $item->id, 'race:payment-expiry:expire'],
        );

        $this->assertGreaterThanOrEqual(1, collect($results)->where('ok', true)->count());
        $this->assertSame(
            InventoryReservationState::Committed,
            $item->fresh()->inventoryReservation->state,
        );
        $balance = $product->inventoryBalance()->firstOrFail();
        $this->assertSame(0, $balance->reserved);
        $this->assertSame(1, $balance->committed);
    }

    public function test_cancellation_and_handoff_cannot_both_mutate_stock(): void
    {
        $product = $this->makeProduct(1);
        app(InventoryService::class)->ensureBalance($product);
        $item = $this->makeOrderItem($product);
        app(InventoryService::class)->commit($item, 'race:cancel-handoff:commit', fromReserved: false);

        $results = $this->race(
            ['release', (string) $item->id, 'race:cancel-handoff:release'],
            ['consume', (string) $item->id, 'race:cancel-handoff:consume'],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $reservation = $item->fresh()->inventoryReservation;
        $this->assertContains($reservation->state, [
            InventoryReservationState::Released,
            InventoryReservationState::Consumed,
        ]);
        $balance = $product->inventoryBalance()->firstOrFail();
        $this->assertGreaterThanOrEqual(0, $balance->on_hand);
        $this->assertGreaterThanOrEqual(0, $balance->committed);
        $this->assertGreaterThanOrEqual(0, $balance->available());
    }

    public function test_adjustment_and_checkout_preserve_nonnegative_availability(): void
    {
        $product = $this->makeProduct(1);
        app(InventoryService::class)->ensureBalance($product);
        $item = $this->makeOrderItem($product);

        $results = $this->race(
            ['reserve', (string) $item->id, 'race:adjust-checkout:reserve'],
            ['adjust_decrease', (string) $product->id, 'race:adjust-checkout:decrease'],
        );

        $this->assertSame(1, collect($results)->where('ok', true)->count());
        $balance = $product->inventoryBalance()->firstOrFail();
        $this->assertSame(0, $balance->available());
        $this->assertGreaterThanOrEqual(0, $balance->on_hand);
        $this->assertGreaterThanOrEqual(0, $balance->reserved);
    }

    public function test_multi_sku_locks_are_deterministic_in_opposite_input_order(): void
    {
        $first = $this->makeProduct(2);
        $second = $this->makeProduct(2);
        app(InventoryService::class)->ensureBalance($first);
        app(InventoryService::class)->ensureBalance($second);

        $results = $this->race(
            ['lock_many', "{$first->id},{$second->id}", 'race:locks:first'],
            ['lock_many', "{$second->id},{$first->id}", 'race:locks:second'],
        );

        $this->assertTrue(collect($results)->every('ok'));
    }

    /**
     * @param  array{string, string, string}  $first
     * @param  array{string, string, string}  $second
     * @return list<array{ok: bool, operation: string, exception?: string}>
     */
    private function race(array $first, array $second): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'inventory-race-');
        @unlink($barrier);

        $processes = collect([$first, $second])->map(function (array $arguments) use ($barrier) {
            $process = new Process([
                PHP_BINARY,
                base_path('tests/Support/inventory_concurrency_worker.php'),
                ...$arguments,
                $barrier,
            ], base_path(), [
                'APP_ENV' => 'testing',
                'DB_CONNECTION' => 'mysql',
                'DB_DATABASE' => config('database.connections.mysql.database'),
                'DB_URL' => '',
                'CACHE_STORE' => 'array',
                'QUEUE_CONNECTION' => 'sync',
                'SESSION_DRIVER' => 'array',
            ]);
            $process->setTimeout(20);
            $process->start();

            return $process;
        });

        file_put_contents($barrier, 'go');

        try {
            return $processes->map(function (Process $process): array {
                $process->wait();
                $lines = array_values(array_filter(explode(PHP_EOL, trim($process->getOutput()))));
                $result = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

                return $result;
            })->all();
        } finally {
            @unlink($barrier);
        }
    }

    private function createTestDatabase(string $database): void
    {
        $connection = config('database.connections.mysql');
        $pdo = new PDO(
            "mysql:host={$connection['host']};port={$connection['port']};charset=utf8mb4",
            $connection['username'],
            $connection['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function makeProduct(int $stock): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Concurrency',
            'slug' => 'concurrency',
            'description' => 'Concurrency tests',
            'image' => '/images/concurrency.jpg',
            'featured' => false,
        ]);

        return Product::query()->create([
            'external_id' => 'race-'.uniqid(),
            'slug' => 'race-'.uniqid(),
            'name' => 'Concurrency Product',
            'sku' => 'RACE-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 0,
            'reviews' => 0,
            'image' => '/images/concurrency.jpg',
            'description' => 'Concurrency test product',
            'tags' => [],
            'details' => [],
            'gallery' => [],
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    private function makeOrderItem(Product $product, string $paymentMethod = 'cod'): OrderItem
    {
        $order = Order::query()->create([
            'number' => 'VM-RACE-'.uniqid(),
            'full_name' => 'Concurrency Test',
            'email' => 'concurrency@example.test',
            'phone' => '9999999999',
            'address' => '1 Test Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'seller_state' => 'Gujarat',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 5,
            'total' => 105,
            'status' => $paymentMethod === 'cod' ? 'Processing' : 'AwaitingPayment',
            'payment_status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        return $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => 100,
            'quantity' => 1,
            'line_total' => 100,
        ]);
    }
}
