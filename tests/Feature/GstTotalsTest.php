<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductQueryService;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class GstTotalsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();

        config([
            'gst.seller_state' => 'Gujarat',
            'gst.rate' => 0.05,
            'services.razorpay.key_id' => 'rzp_test_dummy',
            'services.razorpay.key_secret' => 'test_secret_dummy',
        ]);
    }

    public function test_cart_without_state_returns_estimate_cgst_sgst(): void
    {
        $product = $this->makeProduct(['price' => 200, 'stock' => 5]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('totals.subtotal', 200)
            ->assertJsonPath('totals.tax', 10)
            ->assertJsonPath('totals.cgst', 5)
            ->assertJsonPath('totals.sgst', 5)
            ->assertJsonPath('totals.igst', 0)
            ->assertJsonPath('totals.tax_type', 'estimate');
    }

    public function test_cart_with_gujarat_state_uses_cgst_sgst(): void
    {
        $product = $this->makeProduct(['price' => 200, 'stock' => 5]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->getJson('/api/cart?state=GJ')
            ->assertOk()
            ->assertJsonPath('totals.tax', 10)
            ->assertJsonPath('totals.cgst', 5)
            ->assertJsonPath('totals.sgst', 5)
            ->assertJsonPath('totals.igst', 0)
            ->assertJsonPath('totals.tax_type', 'cgst_sgst');
    }

    public function test_cart_with_other_state_uses_igst(): void
    {
        $product = $this->makeProduct(['price' => 200, 'stock' => 5]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->getJson('/api/cart?state=Maharashtra')
            ->assertOk()
            ->assertJsonPath('totals.tax', 10)
            ->assertJsonPath('totals.cgst', 0)
            ->assertJsonPath('totals.sgst', 0)
            ->assertJsonPath('totals.igst', 10)
            ->assertJsonPath('totals.tax_type', 'igst');
    }

    public function test_order_create_persists_gujarat_split_and_seller_state(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['price' => 200, 'stock' => 5]);
        Sanctum::actingAs($user);

        $this->mockRazorpay();

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'state' => 'GUJARAT',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.cgst', 5)
            ->assertJsonPath('data.sgst', 5)
            ->assertJsonPath('data.igst', 0)
            ->assertJsonPath('data.tax', 10)
            ->assertJsonPath('data.seller_state', 'Gujarat')
            ->assertJsonPath('data.address.state', 'Gujarat');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'cgst' => 5,
            'sgst' => 5,
            'igst' => 0,
            'seller_state' => 'Gujarat',
            'state' => 'Gujarat',
        ]);
    }

    public function test_order_create_persists_igst_for_other_state(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['price' => 200, 'stock' => 5]);
        Sanctum::actingAs($user);

        $this->mockRazorpay();

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.cgst', 0)
            ->assertJsonPath('data.sgst', 0)
            ->assertJsonPath('data.igst', 10)
            ->assertJsonPath('data.tax', 10)
            ->assertJsonPath('data.seller_state', 'Gujarat');
    }

    public function test_calculate_totals_aliases_match_seller(): void
    {
        $service = app(ProductQueryService::class);
        $lines = [['price' => 100, 'quantity' => 1]];

        foreach (['Gujarat', 'gj', ' GUJARAT '] as $state) {
            $totals = $service->calculateTotals($lines, $state);
            $this->assertSame(2.5, $totals['cgst'], $state);
            $this->assertSame(2.5, $totals['sgst'], $state);
            $this->assertSame(0.0, $totals['igst'], $state);
            $this->assertSame('cgst_sgst', $totals['tax_type'], $state);
        }
    }

    private function mockRazorpay(): void
    {
        $mock = Mockery::mock(RazorpayService::class);
        $mock->shouldReceive('keyId')->andReturn('rzp_test_dummy');
        $mock->shouldReceive('createOrder')->andReturn([
            'id' => 'order_gst_test',
            'amount' => 25900,
            'currency' => 'INR',
        ]);
        $mock->shouldReceive('checkoutPayload')->andReturnUsing(function (Order $order) {
            return [
                'key' => 'rzp_test_dummy',
                'order_id' => $order->razorpay_order_id,
                'amount' => (int) round(((float) $order->total) * 100),
                'currency' => 'INR',
                'name' => 'Ventures Mart',
                'description' => 'Order '.$order->number,
                'prefill' => [
                    'name' => $order->full_name,
                    'email' => $order->email,
                    'contact' => $order->phone,
                ],
            ];
        });

        $this->app->instance(RazorpayService::class, $mock);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'payment_method' => 'razorpay',
        ], $overrides);
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
            'slug' => 'gst-product-'.uniqid(),
            'name' => 'GST Product',
            'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 10,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'GST test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
