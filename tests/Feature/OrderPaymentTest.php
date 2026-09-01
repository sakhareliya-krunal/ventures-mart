<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class OrderPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Mail::fake();

        config([
            'services.razorpay.key_id' => 'rzp_test_dummy',
            'services.razorpay.key_secret' => 'test_secret_dummy',
        ]);
    }

    public function test_guest_cannot_create_order(): void
    {
        $this->postJson('/api/orders', $this->addressPayload())
            ->assertUnauthorized();
    }

    public function test_create_order_returns_razorpay_payload_and_reserves_stock_without_clearing_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5, 'price' => 200]);
        Sanctum::actingAs($user);

        $this->mockRazorpay([
            'createOrder' => [
                'id' => 'order_test_123',
                'amount' => 21000,
                'currency' => 'INR',
            ],
        ]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'AwaitingPayment')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_method', 'razorpay')
            ->assertJsonPath('razorpay.order_id', 'order_test_123')
            ->assertJsonPath('razorpay.key', 'rzp_test_dummy')
            ->assertJsonPath('razorpay.currency', 'INR');

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'razorpay_order_id' => 'order_test_123',
            'payment_status' => 'pending',
            'status' => 'AwaitingPayment',
        ]);

        $this->assertSame(4, $product->fresh()->stock);
        $this->getJson('/api/cart')->assertJsonPath('item_count', 1);
        Mail::assertNothingSent();
    }

    public function test_verify_payment_marks_paid_decrements_stock_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5, 'price' => 100]);
        Sanctum::actingAs($user);

        $this->mockRazorpay([
            'createOrder' => [
                'id' => 'order_test_abc',
                'amount' => 10500,
                'currency' => 'INR',
            ],
            'verifySignature' => true,
        ]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $create = $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
        ]))->assertCreated();

        $orderId = $create->json('data.id');

        $this->postJson("/api/orders/{$orderId}/payment/verify", [
            'razorpay_order_id' => 'order_test_abc',
            'razorpay_payment_id' => 'pay_test_1',
            'razorpay_signature' => 'sig_valid',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'Processing');

        $this->assertSame(3, $product->fresh()->stock);
        $this->getJson('/api/cart')->assertJsonPath('item_count', 0);
        $this->assertNotNull(Order::query()->find($orderId)?->paid_at);
        $this->assertNotNull(Order::query()->find($orderId)?->order_confirmation_emailed_at);
        Mail::assertSent(OrderConfirmation::class, 1);
    }

    public function test_bad_signature_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 4, 'price' => 150]);
        Sanctum::actingAs($user);

        $this->mockRazorpay([
            'createOrder' => [
                'id' => 'order_test_bad',
                'amount' => 15750,
                'currency' => 'INR',
            ],
            'verifySignature' => false,
        ]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $orderId = $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
        ]))->assertCreated()->json('data.id');

        $this->postJson("/api/orders/{$orderId}/payment/verify", [
            'razorpay_order_id' => 'order_test_bad',
            'razorpay_payment_id' => 'pay_bad',
            'razorpay_signature' => 'sig_bad',
        ])->assertStatus(422);

        $this->assertSame('failed', Order::query()->find($orderId)?->payment_status);
        $this->assertSame(4, $product->fresh()->stock);
        $this->getJson('/api/cart')->assertJsonPath('item_count', 1);
        Mail::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $behavior
     */
    private function mockRazorpay(array $behavior): void
    {
        $mock = Mockery::mock(RazorpayService::class);
        $mock->shouldReceive('keyId')->andReturn('rzp_test_dummy');

        if (array_key_exists('createOrder', $behavior)) {
            $mock->shouldReceive('createOrder')->andReturn($behavior['createOrder']);
        }

        if (array_key_exists('verifySignature', $behavior)) {
            $mock->shouldReceive('verifySignature')->andReturn((bool) $behavior['verifySignature']);
        }

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
            'district' => 'Ahmedabad',
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
            'slug' => 'pay-product-'.uniqid(),
            'name' => 'Pay Product',
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
            'description' => 'Payment test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
