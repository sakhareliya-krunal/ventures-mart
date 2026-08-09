<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryLedgerEntry;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventory = app(InventoryService::class);
    }

    public function test_reserve_commit_release_and_expire_update_balances_and_stock_projection(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $this->seedBalance($product, onHand: 10);

        $order = $this->makeOrder($product, quantity: 3);
        $item = $order->items->first();

        $this->inventory->reserve(
            $item,
            Carbon::now()->addMinutes(15),
            "order:{$order->id}:item:{$item->id}:reserve",
        );

        $product->refresh();
        $balance = InventoryBalance::query()->where('product_id', $product->id)->first();

        $this->assertSame(3, $balance->reserved);
        $this->assertSame(7, $product->stock);

        $this->inventory->commit($item, "order:{$order->id}:item:{$item->id}:commit");

        $balance->refresh();
        $product->refresh();

        $this->assertSame(0, $balance->reserved);
        $this->assertSame(3, $balance->committed);
        $this->assertSame(7, $product->stock);
        $this->assertSame(InventoryReservationState::Committed, $item->fresh()->inventoryReservation->state);

        $this->inventory->release($item, 'Cancelled before shipment', "order:{$order->id}:item:{$item->id}:release");

        $balance->refresh();
        $product->refresh();

        $this->assertSame(0, $balance->committed);
        $this->assertSame(10, $product->stock);
    }

    public function test_expire_releases_unpaid_reservation(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $this->seedBalance($product, onHand: 10);
        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();

        $this->inventory->reserve(
            $item,
            Carbon::now()->addMinutes(15),
            "order:{$order->id}:item:{$item->id}:reserve",
        );

        $this->inventory->expire($item, "order:{$order->id}:item:{$item->id}:expire");

        $balance = InventoryBalance::query()->where('product_id', $product->id)->first();
        $product->refresh();

        $this->assertSame(0, $balance->reserved);
        $this->assertSame(10, $product->stock);
        $this->assertSame(InventoryReservationState::Expired, $item->fresh()->inventoryReservation->state);
    }

    public function test_consume_reduces_on_hand_and_committed_once(): void
    {
        $product = $this->makeProduct(['stock' => 8]);
        $this->seedBalance($product, onHand: 10, committed: 2);
        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();

        InventoryReservation::query()->create([
            'order_item_id' => $item->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'state' => InventoryReservationState::Committed,
            'committed_at' => now(),
        ]);

        $key = "order:{$order->id}:item:{$item->id}:consume";

        $this->inventory->consume($item, $key);
        $this->inventory->consume($item, $key);

        $balance = InventoryBalance::query()->where('product_id', $product->id)->first();
        $product->refresh();

        $this->assertSame(8, $balance->on_hand);
        $this->assertSame(0, $balance->committed);
        $this->assertSame(8, $product->stock);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('idempotency_key', $key)->count());
    }

    public function test_cod_style_commit_and_adjust_respect_invariants(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $this->seedBalance($product, onHand: 5);

        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();

        $this->inventory->commit(
            $item,
            "order:{$order->id}:item:{$item->id}:commit",
            fromReserved: false,
        );

        $this->inventory->adjust(
            $product,
            'receive',
            3,
            'Warehouse receipt',
            'adjust:product:'.$product->id.':receive:1',
        );

        $balance = InventoryBalance::query()->where('product_id', $product->id)->first();
        $product->refresh();

        $this->assertSame(8, $balance->on_hand);
        $this->assertSame(2, $balance->committed);
        $this->assertSame(6, $product->stock);
    }

    public function test_damage_writeoff_is_idempotent_and_preserves_allocated_stock(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $this->seedBalance($product, onHand: 5);
        $key = "damage:product:{$product->id}:case:1";

        $this->inventory->writeOffDamage($product, 2, 'Damaged in storage', $key);
        $this->inventory->writeOffDamage($product, 2, 'Damaged in storage', $key);

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(3, $product->inventoryBalance()->first()->on_hand);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('idempotency_key', $key)->count());
    }

    public function test_consumed_inventory_can_be_partially_restocked_once(): void
    {
        $product = $this->makeProduct(['stock' => 8]);
        $this->seedBalance($product, onHand: 8);
        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();
        $item->forceFill(['shipped_quantity' => 2])->save();

        InventoryReservation::query()->create([
            'order_item_id' => $item->id,
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'state' => InventoryReservationState::Consumed,
            'consumed_at' => now(),
        ]);

        $key = "return:order-item:{$item->id}:quantity:1";
        $this->inventory->restockReturn($item, 1, $key);
        $this->inventory->restockReturn($item, 1, $key);

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertSame(1, $item->fresh()->restocked_quantity);
        $this->assertSame(1, InventoryLedgerEntry::query()->where('idempotency_key', $key)->count());
    }

    public function test_reconcile_repair_restores_ledger_reservation_and_stock_consistency(): void
    {
        $product = $this->makeProduct(['stock' => 6]);
        $balance = $this->inventory->ensureBalance($product);
        $balance->forceFill(['reserved' => 2])->save();
        Product::query()->whereKey($product->id)->update(['stock' => 99]);

        $result = $this->inventory->reconcile($product->fresh(), repair: true);
        $clean = $this->inventory->reconcile($product->fresh());

        $this->assertTrue($result['repaired']);
        $this->assertSame([], $clean['issues']);
        $this->assertSame(6, $product->fresh()->stock);
    }

    public function test_reconcile_detects_stock_projection_mismatch(): void
    {
        $product = $this->makeProduct(['stock' => 4]);
        $this->seedBalance($product, onHand: 6, committed: 1);

        Product::query()->whereKey($product->id)->update(['stock' => 99]);

        $result = $this->inventory->reconcile($product->fresh());

        $this->assertNotEmpty($result['issues']);
        $this->assertTrue(collect($result['issues'])->contains(
            fn (array $issue) => $issue['code'] === 'product_stock_projection_mismatch'
        ));
    }

    private function seedBalance(Product $product, int $onHand, int $reserved = 0, int $committed = 0): InventoryBalance
    {
        return InventoryBalance::query()->create([
            'product_id' => $product->id,
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'committed' => $committed,
            'version' => 0,
            'low_stock_threshold' => 5,
            'reorder_point' => 10,
        ]);
    }

    private function makeOrder(Product $product, int $quantity): Order
    {
        $order = Order::query()->create([
            'number' => 'VM-TEST-'.uniqid(),
            'full_name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '9999999999',
            'address' => '123 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'seller_state' => 'Maharashtra',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Processing',
            'inventory_status' => OrderInventoryStatus::Unallocated,
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'line_total' => $product->price * $quantity,
        ]);

        return $order->fresh(['items']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        return Product::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'inventory-product-'.uniqid(),
            'name' => 'Inventory Product',
            'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 0,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'Inventory test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
