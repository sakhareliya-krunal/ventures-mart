<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_public_track_returns_order_by_number(): void
    {
        $order = $this->makeOrder([
            'number' => 'VM-TRACK-TEST',
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $this->getJson('/api/orders/track/VM-TRACK-TEST')
            ->assertOk()
            ->assertJsonPath('data.number', 'VM-TRACK-TEST')
            ->assertJsonPath('data.status_label', 'Confirmed')
            ->assertJsonPath('data.timeline.confirmed', true)
            ->assertJsonPath('data.timeline.packed', false)
            ->assertJsonPath('data.invoice_available', true)
            ->assertJsonMissingPath('data.address.address');
    }

    public function test_public_track_unknown_number_returns_404(): void
    {
        $this->getJson('/api/orders/track/VM-MISSING')
            ->assertNotFound();
    }

    public function test_public_track_invoice_downloads_pdf_when_invoiceable(): void
    {
        $this->makeOrder([
            'number' => 'VM-TRACK-INV',
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $this->get('/api/orders/track/VM-TRACK-INV/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_packed_status_advances_timeline(): void
    {
        $this->makeOrder([
            'number' => 'VM-TRACK-PACKED',
            'status' => 'Packed',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $this->getJson('/api/orders/track/VM-TRACK-PACKED')
            ->assertOk()
            ->assertJsonPath('data.timeline.confirmed', true)
            ->assertJsonPath('data.timeline.packed', true)
            ->assertJsonPath('data.timeline.shipped', false);
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
