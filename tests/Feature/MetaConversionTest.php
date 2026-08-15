<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\RazorpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class MetaConversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Mail::fake();
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
        ]);
        config([
            'services.meta.pixel_id' => '1234567890',
            'services.meta.access_token' => 'test-capi-token',
            'services.razorpay.key_id' => 'rzp_test_dummy',
            'services.razorpay.key_secret' => 'test_secret_dummy',
        ]);
    }

    public function test_view_content_capi_uses_product_price_from_the_database(): void
    {
        $product = $this->makeProduct(['price' => 199, 'name' => 'Steel Lunch Box']);

        $this->postJson('/api/meta/events', [
            'event_name' => 'ViewContent',
            'event_id' => 'evt-view-1',
            'event_source_url' => 'https://venturesmart.test/product/'.$product->slug,
            'custom_data' => [
                'content_ids' => [(string) $product->id],
                'value' => 1,
            ],
        ])->assertOk();

        Http::assertSent(function ($request) use ($product) {
            $event = $request['data'][0] ?? [];
            $custom = $event['custom_data'] ?? [];

            return str_contains($request->url(), '/1234567890/events')
                && $event['event_name'] === 'ViewContent'
                && $event['event_id'] === 'evt-view-1'
                && $custom['content_ids'] === [(string) $product->id]
                && $custom['content_name'] === 'Steel Lunch Box'
                && $custom['content_type'] === 'product'
                && $custom['currency'] === 'INR'
                && (float) $custom['value'] === 199.0;
        });
    }

    public function test_initiate_checkout_capi_uses_the_server_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['price' => 200, 'name' => 'Wooden Blocks']);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson('/api/meta/events', [
            'event_name' => 'InitiateCheckout',
            'event_id' => 'evt-checkout-1',
            'event_source_url' => 'https://venturesmart.test/checkout',
            'custom_data' => ['value' => 1],
        ])->assertOk();

        Http::assertSent(function ($request) use ($product) {
            $custom = $request['data'][0]['custom_data'] ?? [];

            return ($request['data'][0]['event_name'] ?? null) === 'InitiateCheckout'
                && $custom['content_ids'] === [(string) $product->id]
                && $custom['contents'][0]['quantity'] === 2
                && $custom['currency'] === 'INR'
                && (float) $custom['value'] > 1;
        });
    }

    public function test_purchase_is_rejected_on_the_browser_capi_endpoint(): void
    {
        $this->postJson('/api/meta/events', [
            'event_name' => 'Purchase',
            'event_id' => 'evt-purchase-blocked',
        ])->assertStatus(422);
    }

    public function test_cod_order_sends_purchase_once_for_an_idempotency_key(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5, 'price' => 200]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $headers = ['Idempotency-Key' => 'checkout-meta-cod-1'];
        $payload = $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
            'payment_method' => 'cod',
        ]);

        $first = $this->postJson('/api/orders', $payload, $headers)->assertCreated();
        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ]);
        $this->postJson('/api/orders', $payload, $headers)->assertSuccessful();

        $this->assertNotNull($first->json('data.meta_purchase_event_id'));
        $this->assertSame(1, $this->graphEventCount('Purchase'));
    }

    public function test_razorpay_create_does_not_send_purchase_but_verify_does(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5, 'price' => 100]);
        Sanctum::actingAs($user);

        $this->mockRazorpay([
            'createOrder' => [
                'id' => 'order_meta_1',
                'amount' => 10500,
                'currency' => 'INR',
            ],
            'verifySignature' => true,
        ]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $create = $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'payment_method' => 'razorpay',
        ]))->assertCreated();

        $this->assertSame(0, $this->graphEventCount('Purchase'));
        $this->assertNull($create->json('data.meta_purchase_event_id'));

        $orderId = $create->json('data.id');
        $verify = $this->postJson("/api/orders/{$orderId}/payment/verify", [
            'razorpay_order_id' => 'order_meta_1',
            'razorpay_payment_id' => 'pay_meta_1',
            'razorpay_signature' => 'sig_valid',
        ])->assertOk();

        $this->assertNotNull($verify->json('data.meta_purchase_event_id'));
        $this->assertSame(1, $this->graphEventCount('Purchase'));
    }

    private function graphEventCount(string $eventName): int
    {
        return collect(Http::recorded())
            ->filter(function ($pair) use ($eventName) {
                [$request] = $pair;

                return ($request['data'][0]['event_name'] ?? null) === $eventName;
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $behavior
     */
    private function mockRazorpay(array $behavior): void
    {
        $mock = Mockery::mock(RazorpayService::class);
        $mock->shouldReceive('keyId')->andReturn('rzp_test_dummy');
        $mock->shouldReceive('createOrder')->andReturn($behavior['createOrder']);
        $mock->shouldReceive('verifySignature')->andReturn((bool) $behavior['verifySignature']);
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
     * @return array<string, mixed>
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
            'payment_method' => 'cod',
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
            'slug' => 'meta-product-'.uniqid(),
            'name' => 'Meta Product',
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
            'description' => 'Meta test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
