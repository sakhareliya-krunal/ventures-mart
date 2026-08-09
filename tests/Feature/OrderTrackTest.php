<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderTrackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'invoice.gstin' => '24EDLPK6446N1ZX',
            'invoice.legal_name' => 'Neelkanth Emporium',
            'invoice.trade_name' => 'Ventures Mart',
            'invoice.default_hsn' => '9503',
            'invoice.email' => 'neelkanthventures1804@gmail.com',
        ]);
    }

    public function test_owner_can_view_complete_order_by_number(): void
    {
        $order = $this->makeOrder([
            'number' => 'VM-TRACK-TEST',
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        Sanctum::actingAs($order->user);

        $this->getJson('/api/orders/track/VM-TRACK-TEST')
            ->assertOk()
            ->assertJsonPath('data.number', 'VM-TRACK-TEST')
            ->assertJsonPath('data.status_label', 'Confirmed')
            ->assertJsonPath('data.timeline.confirmed', true)
            ->assertJsonPath('data.timeline.packed', false)
            ->assertJsonPath('data.invoice_available', true)
            ->assertJsonPath('data.customer.email', 'track@example.com')
            ->assertJsonPath('data.address.address', '12 Secret Street')
            ->assertJsonPath('data.address.postal_code', '360024')
            ->assertJsonPath('data.items.0.hsn', '9503');
    }

    public function test_tracking_requires_authentication_and_order_ownership(): void
    {
        $order = $this->makeOrder(['number' => 'VM-PRIVATE']);

        $this->getJson('/api/orders/track/VM-PRIVATE')->assertUnauthorized();
        $this->get('/api/orders/track/VM-PRIVATE/invoice')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/orders/track/VM-PRIVATE')->assertForbidden();
        $this->get('/api/orders/track/VM-PRIVATE/invoice')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/orders/track/VM-PRIVATE')
            ->assertOk()
            ->assertJsonPath('data.number', $order->number);
    }

    public function test_authenticated_unknown_number_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/orders/track/VM-MISSING')
            ->assertNotFound();
    }

    public function test_owner_can_download_tracked_order_invoice(): void
    {
        $order = $this->makeOrder([
            'number' => 'VM-TRACK-INV',
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        Sanctum::actingAs($order->user);

        $this->get('/api/orders/track/VM-TRACK-INV/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_packed_status_advances_timeline(): void
    {
        $order = $this->makeOrder([
            'number' => 'VM-TRACK-PACKED',
            'status' => 'Packed',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);
        Sanctum::actingAs($order->user);

        $this->getJson('/api/orders/track/VM-TRACK-PACKED')
            ->assertOk()
            ->assertJsonPath('data.timeline.confirmed', true)
            ->assertJsonPath('data.timeline.packed', true)
            ->assertJsonPath('data.timeline.shipped', false);
    }

    public function test_customer_receives_safe_shiprocket_tracking_details(): void
    {
        $order = $this->makeOrder([
            'number' => 'VM-SHIPROCKET-TRACK',
            'courier_partner' => 'Old courier',
            'awb_number' => 'OLD-AWB',
        ]);
        $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'courier_name' => 'Delhivery Surface',
            'awb_code' => 'AWB-NEW-123',
            'pickup_status' => 'Scheduled',
            'shipment_status' => 'IN TRANSIT',
            'tracking_url' => 'https://shiprocket.co/tracking/AWB-NEW-123',
            'last_error' => 'must remain private',
            'last_synced_at' => now(),
        ]);
        Sanctum::actingAs($order->user);

        $this->getJson('/api/orders/track/VM-SHIPROCKET-TRACK')
            ->assertOk()
            ->assertJsonPath('data.shipment.partner', 'Delhivery Surface')
            ->assertJsonPath('data.shipment.tracking_id', 'AWB-NEW-123')
            ->assertJsonPath('data.shipment.shipment_status', 'IN TRANSIT')
            ->assertJsonPath(
                'data.shipment.tracking_url',
                'https://shiprocket.co/tracking/AWB-NEW-123',
            )
            ->assertJsonMissingPath('data.shipment.shiprocket_order_id')
            ->assertJsonMissingPath('data.shipment.last_error');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => 'VM-TRACK-'.uniqid(),
            'user_id' => User::factory()->create()->id,
            'full_name' => 'Track Buyer',
            'email' => 'track@example.com',
            'phone' => '9999999999',
            'address' => '12 Secret Street',
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
        ], $overrides));

        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys-track',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        $product = Product::query()->create([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'track-product-'.uniqid(),
            'name' => 'Track Product',
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
            'description' => 'Track',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ]);

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

        return $order->fresh('items');
    }
}
