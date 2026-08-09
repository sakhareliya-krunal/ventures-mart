<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        config([
            'services.razorpay.webhook_secret' => 'test_webhook_secret',
        ]);
    }

    public function test_payment_captured_marks_order_paid_and_decrements_stock(): void
    {
        $product = $this->makeProduct(['stock' => 5, 'price' => 200]);
        $order = $this->makeOrder($product, [
            'razorpay_order_id' => 'order_wh_1',
            'payment_status' => 'pending',
            'status' => 'AwaitingPayment',
        ]);

        $payload = $this->capturedPayload('order_wh_1', 'pay_wh_1');

        $this->postWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('Processing', $order->fresh()->status);
        $this->assertSame('pay_wh_1', $order->fresh()->razorpay_payment_id);
        $this->assertNotNull($order->fresh()->paid_at);
        $this->assertSame(4, $product->fresh()->stock);
        Mail::assertSent(OrderConfirmation::class, 1);
    }

    public function test_bad_webhook_signature_is_rejected(): void
    {
        $product = $this->makeProduct(['stock' => 5]);
        $this->makeOrder($product, [
            'razorpay_order_id' => 'order_wh_bad',
            'payment_status' => 'pending',
            'status' => 'AwaitingPayment',
        ]);

        $payload = $this->capturedPayload('order_wh_bad', 'pay_wh_bad');

        $this->call(
            'POST',
            '/api/razorpay/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => 'invalid_signature',
            ],
            content: json_encode($payload),
        )->assertStatus(400);

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_already_paid_webhook_is_idempotent(): void
    {
        $product = $this->makeProduct(['stock' => 3]);
        $order = $this->makeOrder($product, [
            'razorpay_order_id' => 'order_wh_paid',
            'razorpay_payment_id' => 'pay_existing',
            'payment_status' => 'paid',
            'status' => 'Processing',
            'paid_at' => now(),
        ]);

        $payload = $this->capturedPayload('order_wh_paid', 'pay_wh_retry');

        $this->postWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');
        $this->postWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');

        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame('pay_existing', $order->fresh()->razorpay_payment_id);
        Mail::assertSent(OrderConfirmation::class, 1);
    }

    public function test_unknown_order_still_returns_ok(): void
    {
        $payload = $this->capturedPayload('order_missing', 'pay_missing');

        $this->postWebhook($payload)
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/api/razorpay/webhook',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => $this->sign($body),
            ],
            content: $body,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function capturedPayload(string $orderId, string $paymentId): array
    {
        return [
            'event' => 'payment.captured',
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => $paymentId,
                        'order_id' => $orderId,
                        'status' => 'captured',
                        'amount' => 21000,
                        'currency' => 'INR',
                    ],
                ],
            ],
        ];
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) config('services.razorpay.webhook_secret'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(Product $product, array $overrides = []): Order
    {
        $order = Order::query()->create(array_merge([
            'number' => 'VM-WH-'.uniqid(),
            'user_id' => null,
            'full_name' => 'Webhook Buyer',
            'email' => 'webhook@example.com',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'seller_state' => 'Gujarat',
            'subtotal' => 200,
            'shipping' => 0,
            'cgst' => 5,
            'sgst' => 5,
            'igst' => 0,
            'tax' => 10,
            'total' => 210,
            'status' => 'AwaitingPayment',
            'payment_status' => 'pending',
            'payment_method' => 'razorpay',
        ], $overrides));

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => $product->image,
            'unit_price' => $product->price,
            'quantity' => 1,
            'line_total' => $product->price,
        ]);

        return $order;
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
            'slug' => 'wh-product-'.uniqid(),
            'name' => 'Webhook Product',
            'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id,
            'price' => 200,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 10,
            'image' => '/images/products/demo.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'Webhook test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
