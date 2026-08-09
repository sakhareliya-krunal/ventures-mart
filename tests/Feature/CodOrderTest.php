<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientInventoryException;
use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CodOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
        Mail::fake();
        config(['app.url' => 'https://venturesmart.in']);
    }

    public function test_cod_create_marks_processing_decrements_stock_and_clears_cart(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5, 'price' => 200]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $response = $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
            'payment_method' => 'cod',
        ]));

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'Processing')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.cod_fee', 99)
            ->assertJsonPath('data.fulfillment_method', 'manual')
            ->assertJsonMissing(['razorpay']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'Processing',
            'razorpay_order_id' => null,
            'district' => 'Ahmedabad',
            'cod_fee' => 99,
        ]);

        $this->assertSame(3, $product->fresh()->stock);
        $this->getJson('/api/cart')->assertJsonPath('item_count', 0);

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertNotNull($order->order_confirmation_emailed_at);
        $this->assertNotNull($order->invoice_number);

        Mail::assertSent(OrderConfirmation::class, function (OrderConfirmation $mail) use ($order) {
            $html = $mail->render();

            return $mail->hasTo($order->email)
                && $mail->envelope()->subject === 'Order confirmed - '.$order->number
                && count($mail->attachments()) === 1
                && str_starts_with($mail->invoiceFilename, 'Invoice-VM-')
                && str_contains($html, 'https://venturesmart.in/images/products/demo.jpg')
                && str_contains($html, 'Your customer invoice is attached');
        });
    }

    public function test_cod_order_failure_is_not_reported_as_payment_initialization_failure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(OrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new RuntimeException('SQLSTATE[42S22]: Unknown column weight_kg'));
        });

        $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
        ]))
            ->assertStatus(500)
            ->assertJson([
                'message' => 'Unable to place your order. Please try again.',
                'code' => 'order_create_failed',
            ])
            ->assertJsonMissing(['code' => 'payment_init_failed']);
    }

    public function test_insufficient_inventory_returns_validation_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->mock(OrderService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('create')
                ->once()
                ->andThrow(new InsufficientInventoryException('Out of stock'));
        });

        $this->postJson('/api/orders', $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
            'payment_method' => 'cod',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart']);
    }

    public function test_cod_checkout_idempotency_key_prevents_duplicate_orders_and_stock_commit(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['stock' => 5]);
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertOk();

        $payload = $this->addressPayload([
            'email' => $user->email,
            'full_name' => $user->name,
        ]);
        $first = $this->withHeader('Idempotency-Key', 'checkout-cod-test-1')
            ->postJson('/api/orders', $payload)
            ->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'checkout-cod-test-1')
            ->postJson('/api/orders', $payload)
            ->assertCreated();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Order::query()->where('checkout_idempotency_key', 'checkout-cod-test-1')->count());
        $this->assertSame(3, $product->fresh()->stock);
        Mail::assertSent(OrderConfirmation::class, 1);
    }

    public function test_admin_can_mark_cod_payment_received(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeCodOrder();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'payment_status' => 'paid',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_admin_delivered_auto_marks_cod_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $order = $this->makeCodOrder();

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Delivered',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Delivered')
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertNotNull($order->fresh()->paid_at);
    }

    public function test_admin_cannot_manually_mark_razorpay_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'number' => 'VM-RZ-1',
            'user_id' => $admin->id,
            'full_name' => 'Buyer',
            'email' => 'buyer@example.com',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'district' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 5,
            'total' => 105,
            'status' => 'AwaitingPayment',
            'payment_status' => 'pending',
            'payment_method' => 'razorpay',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'payment_status' => 'paid',
        ])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
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
            'payment_method' => 'cod',
        ], $overrides);
    }

    private function makeCodOrder(): Order
    {
        return Order::query()->create([
            'number' => 'VM-COD-1',
            'user_id' => User::factory()->create()->id,
            'full_name' => 'COD Buyer',
            'email' => 'cod@example.com',
            'phone' => '9888888888',
            'address' => '9 COD Lane',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 200,
            'shipping' => 0,
            'tax' => 10,
            'total' => 210,
            'status' => 'Processing',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);
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
            'slug' => 'cod-product-'.uniqid(),
            'name' => 'COD Product',
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
            'description' => 'COD test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
