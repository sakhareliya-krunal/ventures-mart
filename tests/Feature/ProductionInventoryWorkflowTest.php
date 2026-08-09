<?php

namespace Tests\Feature;

use App\Enums\InventoryReservationState;
use App\Enums\OrderInventoryStatus;
use App\Models\Category;
use App\Models\InventoryAuditFlag;
use App\Models\InventoryBalance;
use App\Models\InventoryOutboxMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ShiprocketShipment;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\OrderService;
use App\Services\RazorpayService;
use App\Services\ShiprocketFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class ProductionInventoryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_razorpay_checkout_reserves_stock_before_external_payment(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 2]);
        Sanctum::actingAs($user);

        $this->mock(RazorpayService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createOrder')->once()->andReturn([
                'id' => 'order_inventory_test',
                'amount' => 10500,
                'currency' => 'INR',
            ]);
            $mock->shouldReceive('checkoutPayload')->once()->andReturn([
                'order_id' => 'order_inventory_test',
                'amount' => 10500,
                'currency' => 'INR',
            ]);
        });

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->postJson('/api/orders', $this->checkoutPayload($user))
            ->assertCreated()
            ->assertJsonPath('razorpay.order_id', 'order_inventory_test');

        $order = Order::query()->findOrFail($response->json('data.id'));
        $reservation = $order->inventoryReservations()->firstOrFail();

        $this->assertSame(InventoryReservationState::Reserved, $reservation->state);
        $this->assertSame(0, $product->fresh()->stock);
        $this->assertNotNull($order->payment_expires_at);
        $this->assertDatabaseHas('inventory_outbox_messages', [
            'idempotency_key' => "order:{$order->id}:item:{$reservation->order_item_id}:reserve:event",
        ]);
    }

    public function test_expiry_releases_inventory_and_closes_unpaid_order(): void
    {
        Carbon::setTestNow('2026-08-09 10:00:00');
        $product = $this->makeProduct(['stock' => 3]);
        $order = $this->makeOrder($product, quantity: 2, paymentMethod: 'razorpay');
        $item = $order->items->first();
        $inventory = app(InventoryService::class);
        $inventory->reserve($item, now()->subMinute(), "order:{$order->id}:item:{$item->id}:reserve");

        $this->artisan('inventory:expire-reservations')->assertSuccessful();

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertSame('failed', $order->fresh()->payment_status);
        $this->assertSame(InventoryReservationState::Expired, $item->fresh()->inventoryReservation->state);
        Carbon::setTestNow();
    }

    public function test_late_captured_payment_without_stock_is_paid_and_held_from_fulfillment(): void
    {
        $product = $this->makeProduct(['stock' => 1]);
        $order = $this->makeOrder($product, quantity: 1, paymentMethod: 'razorpay');
        $item = $order->items->first();
        $inventory = app(InventoryService::class);
        $inventory->reserve($item, now()->subMinute(), "order:{$order->id}:item:{$item->id}:reserve");
        $inventory->expire($item, "order:{$order->id}:item:{$item->id}:expire");
        $inventory->adjust($product, 'decrease', 1, 'Stock consumed elsewhere', 'late-payment-stock-loss');

        app(OrderService::class)->markPaidFromWebhook($order, 'pay_late_inventory_hold');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('InventoryHold', $order->status);
        $this->assertSame(OrderInventoryStatus::Exception, $order->inventory_status);
        $this->assertSame('pay_late_inventory_hold', $order->razorpay_payment_id);
        $this->assertDatabaseMissing('shiprocket_shipments', ['order_id' => $order->id]);
    }

    public function test_admin_adjustment_requires_version_and_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct(['stock' => 5]);
        $balance = app(InventoryService::class)->ensureBalance($product);
        Sanctum::actingAs($admin);

        $payload = [
            'operation' => 'receive',
            'quantity' => 4,
            'reason' => 'Purchase order receipt',
            'expected_version' => $balance->version,
            'idempotency_key' => 'admin-receipt-001',
        ];

        $this->postJson("/api/admin/inventory/{$product->id}/adjustments", $payload)
            ->assertOk()
            ->assertJsonPath('data.available', 9)
            ->assertJsonPath('data.version', 1);
        $this->postJson("/api/admin/inventory/{$product->id}/adjustments", $payload)
            ->assertOk()
            ->assertJsonPath('data.available', 9);
        $this->postJson("/api/admin/inventory/{$product->id}/adjustments", [
            ...$payload,
            'idempotency_key' => 'admin-receipt-stale-version',
        ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'inventory_version_conflict');

        $this->getJson('/api/admin/inventory/ledger?product_id='.$product->id)
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'Purchase order receipt');

        $this->getJson("/api/admin/inventory/{$product->id}/movements")
            ->assertOk()
            ->assertJsonPath('data.0.reason', 'Purchase order receipt');
        $this->getJson('/api/admin/inventory/summary')
            ->assertOk()
            ->assertJsonPath('total_available', 9);
        $this->get('/api/admin/inventory/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $second = $this->makeProduct(['stock' => 2]);
        $secondBalance = app(InventoryService::class)->ensureBalance($second);
        $this->postJson('/api/admin/inventory/bulk-adjustments', [
            'adjustments' => [
                [
                    'product_id' => $product->id,
                    'operation' => 'receive',
                    'quantity' => 1,
                    'reason' => 'Bulk receipt',
                    'expected_version' => 1,
                    'idempotency_key' => 'bulk-receipt-001',
                ],
                [
                    'product_id' => $second->id,
                    'operation' => 'receive',
                    'quantity' => 1,
                    'reason' => 'Bulk receipt',
                    'expected_version' => $secondBalance->version,
                    'idempotency_key' => 'bulk-receipt-002',
                ],
            ],
        ])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_return_restock_is_idempotent_and_writes_outbox(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct(['stock' => 3]);
        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();
        $item->forceFill(['shipped_quantity' => 2])->save();
        Sanctum::actingAs($admin);

        $payload = [
            'order_item_id' => $item->id,
            'quantity' => 1,
            'disposition' => 'restock',
            'reason' => 'Customer return received',
            'idempotency_key' => 'return-rma-001',
        ];

        $this->postJson('/api/admin/inventory/returns', $payload)->assertCreated();
        $this->postJson('/api/admin/inventory/returns', $payload)->assertCreated();

        $this->assertSame(4, $product->fresh()->stock);
        $this->assertSame(1, $item->fresh()->returned_quantity);
        $this->assertSame(1, InventoryOutboxMessage::query()
            ->where('idempotency_key', 'return-rma-001:event')->count());
        $this->getJson('/api/admin/inventory/returns')
            ->assertOk()
            ->assertJsonPath('data.0.order_item_id', $item->id)
            ->assertJsonPath('data.0.disposition', 'restock');
    }

    public function test_admin_can_review_audit_flags_and_order_inventory_allocation(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct(['stock' => 4]);
        $order = $this->makeOrder($product, quantity: 2, paymentMethod: 'razorpay');
        $item = $order->items->first();
        $item->forceFill([
            'shipped_quantity' => 2,
            'returned_quantity' => 1,
            'restocked_quantity' => 1,
        ])->save();
        $order->forceFill([
            'inventory_status' => OrderInventoryStatus::Reserved,
            'payment_expires_at' => now()->addMinutes(15),
            'cancel_requested_at' => now(),
            'cancellation_reason' => 'Customer request',
        ])->save();
        app(InventoryService::class)->reserve(
            $item,
            now()->addMinutes(15),
            "order:{$order->id}:item:{$item->id}:admin-resource",
        );
        $flag = InventoryAuditFlag::query()->create([
            'product_id' => $product->id,
            'order_id' => $order->id,
            'code' => 'balance_mismatch',
            'message' => 'Balance requires review.',
            'context' => ['expected' => 2, 'actual' => 1],
        ]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.inventory_status', 'reserved')
            ->assertJsonPath('data.items.0.shipped_quantity', 2)
            ->assertJsonPath('data.items.0.returned_quantity', 1)
            ->assertJsonPath('data.items.0.restocked_quantity', 1)
            ->assertJsonPath('data.items.0.inventory_reservation.state', 'reserved');

        $this->getJson('/api/admin/inventory/audit-flags')
            ->assertOk()
            ->assertJsonPath('data.0.id', $flag->id)
            ->assertJsonPath('data.0.product.name', $product->name);
        $this->patchJson("/api/admin/inventory/audit-flags/{$flag->id}/resolve")
            ->assertOk()
            ->assertJsonPath('ok', true);
        $this->assertNotNull($flag->fresh()->resolved_at);
    }

    public function test_shiprocket_handoff_consumes_committed_inventory_once(): void
    {
        config([
            'services.shiprocket.enabled' => true,
            'services.shiprocket.base_url' => 'https://apiv2.shiprocket.in/v1/external',
            'services.shiprocket.email' => 'api@example.test',
            'services.shiprocket.password' => 'secret',
        ]);
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/courier/track/awb/*' => Http::response([
                'tracking_data' => [
                    'shipment_track' => [[
                        'current_status' => 'In Transit',
                        'sr_status' => 6,
                    ]],
                ],
            ]),
        ]);

        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder($product, quantity: 2);
        $item = $order->items->first();
        app(InventoryService::class)->commit(
            $item,
            "order:{$order->id}:item:{$item->id}:commit",
            fromReserved: false,
        );
        ShiprocketShipment::query()->create([
            'order_id' => $order->id,
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'shiprocket_order_id' => 100,
            'shipment_id' => 200,
            'awb_code' => 'AWB-INVENTORY',
        ]);

        $service = app(ShiprocketFulfillmentService::class);
        $service->syncTracking($order->fresh('shiprocketShipment'));
        $service->syncTracking($order->fresh('shiprocketShipment'));

        $balance = InventoryBalance::query()->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(3, $balance->on_hand);
        $this->assertSame(0, $balance->committed);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(InventoryReservationState::Consumed, $item->fresh()->inventoryReservation->state);
    }

    private function makeOrder(Product $product, int $quantity, string $paymentMethod = 'cod'): Order
    {
        $order = Order::query()->create([
            'number' => 'VM-PI-'.uniqid(),
            'full_name' => 'Inventory Buyer',
            'email' => 'inventory@example.com',
            'phone' => '9999999999',
            'address' => '1 Inventory Road',
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
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => 100,
            'quantity' => $quantity,
            'line_total' => 100 * $quantity,
        ]);

        return $order->fresh('items');
    }

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'production-inventory',
            'description' => 'Toys',
            'image' => '/images/toys.jpg',
            'featured' => true,
        ]);

        return Product::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'inventory-'.uniqid(),
            'name' => 'Inventory Product',
            'sku' => 'INV-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 5,
            'reviews' => 0,
            'image' => '/images/product.jpg',
            'tags' => [],
            'description' => 'Test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }

    private function checkoutPayload(User $user): array
    {
        return [
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '9999999999',
            'address' => '1 Inventory Road',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'payment_method' => 'razorpay',
        ];
    }
}
