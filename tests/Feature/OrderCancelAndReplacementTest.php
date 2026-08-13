<?php

namespace Tests\Feature;

use App\Enums\FulfillmentMethod;
use App\Enums\InventoryReservationState;
use App\Jobs\CancelShiprocketOrder;
use App\Jobs\FulfillShiprocketOrder;
use App\Jobs\SendOrderCancellationEmail;
use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderReplacementRequest;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCancelAndReplacementTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_cancel_pre_ship_cod_order_and_release_inventory(): void
    {
        Queue::fake();
        $product = $this->makeProduct(['stock' => 5]);
        $order = $this->makeOrder($product, [
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        $item = $order->items->first();
        app(InventoryService::class)->commit(
            $item,
            "order:{$order->id}:item:{$item->id}:commit",
            fromReserved: false,
        );
        Sanctum::actingAs($order->user);

        $this->postJson("/api/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'Changed my mind',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Cancelled')
            ->assertJsonPath('data.payment_status', 'pending');

        $this->assertSame('Cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->cancelled_at);
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertSame(
            InventoryReservationState::Released,
            $item->fresh()->inventoryReservation->state,
        );
        Queue::assertPushed(SendOrderCancellationEmail::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_prepaid_cancel_sets_refund_pending_and_admin_can_mark_refunded(): void
    {
        Queue::fake();
        $product = $this->makeProduct(['stock' => 3]);
        $order = $this->makeOrder($product, [
            'status' => 'Packed',
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
        Sanctum::actingAs($order->user);

        $this->postJson("/api/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'Ordered by mistake',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'refund_pending');

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'payment_status' => 'refunded',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'refunded')
            ->assertJsonPath('data.can_mark_refunded', false);
    }

    public function test_customer_cannot_cancel_after_awb_or_shipped(): void
    {
        $product = $this->makeProduct();
        $withAwb = $this->makeOrder($product, [
            'status' => 'Packed',
            'fulfillment_method' => FulfillmentMethod::Shiprocket,
        ]);
        $withAwb->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'awb_assigned',
            'shiprocket_order_id' => 11,
            'shipment_id' => 22,
            'awb_code' => 'AWB-BLOCK',
        ]);
        Sanctum::actingAs($withAwb->user);
        $this->postJson("/api/orders/{$withAwb->id}/cancel", [
            'cancellation_reason' => 'Too late',
        ])->assertStatus(422);

        $shipped = $this->makeOrder($product, ['status' => 'Shipped']);
        Sanctum::actingAs($shipped->user);
        $this->postJson("/api/orders/{$shipped->id}/cancel", [
            'cancellation_reason' => 'Too late',
        ])->assertStatus(422);
    }

    public function test_cancel_requires_ownership_and_reason(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, ['status' => 'Processing']);

        $this->postJson("/api/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'Guest',
        ])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/orders/{$order->id}/cancel", [
            'cancellation_reason' => 'Not mine',
        ])->assertForbidden();

        Sanctum::actingAs($order->user);
        $this->postJson("/api/orders/{$order->id}/cancel", [])
            ->assertStatus(422);
    }

    public function test_admin_shiprocket_cancel_queues_remote_job(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'status' => 'Processing',
            'fulfillment_method' => FulfillmentMethod::Shiprocket,
        ]);
        $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'order_created',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
        ]);
        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Cancelled',
            'cancellation_reason' => 'Admin stop',
        ])->assertOk();

        $this->assertNotNull($response->json('data.cancel_requested_at'));
        Queue::assertPushed(CancelShiprocketOrder::class, fn ($job) => $job->orderId === $order->id);
        $this->assertNull($order->fresh()->cancelled_at);
    }

    public function test_track_payload_exposes_cancel_and_replacement_flags(): void
    {
        $product = $this->makeProduct();
        $processing = $this->makeOrder($product, [
            'number' => 'VM-CAN-TRACK',
            'status' => 'Processing',
        ]);
        Sanctum::actingAs($processing->user);
        $this->getJson('/api/orders/track/VM-CAN-TRACK')
            ->assertOk()
            ->assertJsonPath('data.can_cancel', true)
            ->assertJsonPath('data.can_request_replacement', false)
            ->assertJsonPath('data.support.replacement_path', '/replacement');

        $delivered = $this->makeOrder($product, [
            'number' => 'VM-REP-TRACK',
            'status' => 'Delivered',
            'delivered_at' => now()->subDays(2),
        ]);
        Sanctum::actingAs($delivered->user);
        $this->getJson('/api/orders/track/VM-REP-TRACK')
            ->assertOk()
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.can_request_replacement', true);
    }

    public function test_customer_can_request_replacement_within_window(): void
    {
        Storage::fake('public');
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'status' => 'Delivered',
            'delivered_at' => now()->subDays(3),
        ]);
        Sanctum::actingAs($order->user);

        $this->post("/api/orders/{$order->id}/replacement-requests", [
            'reason' => 'damaged',
            'notes' => 'Corner dented',
            'photos' => [UploadedFile::fake()->image('damage.jpg')],
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'requested')
            ->assertJsonPath('data.reason', 'damaged');

        $this->assertDatabaseHas('order_replacement_requests', [
            'order_id' => $order->id,
            'status' => 'requested',
            'reason' => 'damaged',
        ]);

        $this->postJson("/api/orders/{$order->id}/replacement-requests", [
            'reason' => 'defective',
        ])->assertStatus(422);
    }

    public function test_replacement_outside_window_and_invalid_reason_are_rejected(): void
    {
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'status' => 'Delivered',
            'delivered_at' => now()->subDays(8),
        ]);
        Sanctum::actingAs($order->user);

        $this->postJson("/api/orders/{$order->id}/replacement-requests", [
            'reason' => 'damaged',
        ])->assertStatus(422);

        $fresh = $this->makeOrder($product, [
            'status' => 'Delivered',
            'delivered_at' => now()->subDay(),
        ]);
        Sanctum::actingAs($fresh->user);
        $this->postJson("/api/orders/{$fresh->id}/replacement-requests", [
            'reason' => 'changed-mind',
        ])->assertStatus(422);
    }

    public function test_admin_can_approve_replacement_creating_linked_order(): void
    {
        Queue::fake();
        Mail::fake();
        config(['services.shiprocket.enabled' => false]);

        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct(['stock' => 4]);
        $order = $this->makeOrder($product, [
            'status' => 'Delivered',
            'delivered_at' => now()->subDay(),
        ]);
        $request = OrderReplacementRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => 'requested',
            'reason' => 'incorrect',
            'notes' => 'Wrong colour',
            'requested_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/admin/replacement-requests/{$request->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'fulfilled');

        $this->assertTrue(str_starts_with((string) $response->json('data.replacement_order.number'), 'VM-R-'));

        $replacement = Order::query()->where('parent_order_id', $order->id)->first();
        $this->assertNotNull($replacement);
        $this->assertSame('replacement', $replacement->order_type);
        $this->assertSame(0.0, (float) $replacement->total);
        $this->assertSame('paid', $replacement->payment_status);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertNotNull($replacement->fresh()->order_confirmation_emailed_at);
        Mail::assertSent(OrderConfirmation::class, 1);
        Queue::assertNotPushed(FulfillShiprocketOrder::class);
    }

    public function test_admin_can_reject_replacement_request(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'status' => 'Delivered',
            'delivered_at' => now()->subDay(),
        ]);
        $request = OrderReplacementRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => 'requested',
            'reason' => 'defective',
            'requested_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/replacement-requests/{$request->id}/reject", [
            'rejection_reason' => 'Outside policy photos unclear',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertSame('Outside policy photos unclear', $request->fresh()->rejection_reason);
    }

    public function test_admin_replacement_inbox_lists_requests(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->makeProduct();
        $order = $this->makeOrder($product, [
            'number' => 'VM-INBOX-1',
            'status' => 'Delivered',
            'delivered_at' => now()->subDay(),
        ]);
        OrderReplacementRequest::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'status' => 'requested',
            'reason' => 'damaged',
            'requested_at' => now(),
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/replacement-requests?search=VM-INBOX-1')
            ->assertOk()
            ->assertJsonPath('data.0.order.number', 'VM-INBOX-1')
            ->assertJsonPath('meta.total', 1);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys-cancel-rep',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        return Product::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'cancel-rep-'.uniqid(),
            'name' => 'Cancel Rep Product',
            'sku' => 'SKU-'.uniqid(),
            'hsn' => '9503',
            'category_id' => $category->id,
            'price' => 200,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 10,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'Product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(Product $product, array $overrides = []): Order
    {
        $user = User::factory()->create();

        $order = Order::query()->create(array_merge([
            'number' => 'VM-CR-'.uniqid(),
            'user_id' => $user->id,
            'full_name' => 'Cancel Buyer',
            'email' => $user->email,
            'phone' => '9999999999',
            'address' => '12 Cancel Street',
            'city' => 'Rajkot',
            'state' => 'Gujarat',
            'postal_code' => '360024',
            'seller_state' => 'Gujarat',
            'subtotal' => 200,
            'shipping' => 0,
            'cod_fee' => 0,
            'cgst' => 5,
            'sgst' => 5,
            'igst' => 0,
            'tax' => 10,
            'total' => 210,
            'status' => 'Processing',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'fulfillment_method' => FulfillmentMethod::Manual,
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'hsn' => '9503',
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => 200,
            'quantity' => 1,
            'line_total' => 200,
        ]);

        return $order->fresh(['items', 'user']);
    }
}
