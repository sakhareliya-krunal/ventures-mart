<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CodOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
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
            ->assertJsonMissing(['razorpay']);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'Processing',
            'razorpay_order_id' => null,
        ]);

        $this->assertSame(3, $product->fresh()->stock);
        $this->getJson('/api/cart')->assertJsonPath('item_count', 0);
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
