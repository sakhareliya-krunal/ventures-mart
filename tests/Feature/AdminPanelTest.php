<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_stats_and_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'number' => 'VM-TEST-1',
            'user_id' => $admin->id,
            'full_name' => 'Admin Buyer',
            'email' => $admin->email,
            'phone' => '9999999999',
            'address' => '1 Admin Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('orders_count', 1)
            ->assertJsonPath('customers_count', 0)
            ->assertJsonStructure([
                'orders_by_status' => [
                    'Processing',
                    'Shipped',
                    'Delivered',
                    'Cancelled',
                ],
                'revenue_series',
                'revenue_last_7_days',
                'revenue_range',
                'revenue_period_label',
                'revenue_period_total',
                'revenue_period_orders',
                'low_stock_products',
                'recent_messages',
                'recent_posts',
            ])
            ->assertJsonPath('orders_by_status.Processing', 1)
            ->assertJsonPath('revenue_range', 'week')
            ->assertJsonCount(7, 'revenue_series')
            ->assertJsonCount(7, 'revenue_last_7_days');

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.number', 'VM-TEST-1');

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.number', 'VM-TEST-1');

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Shipped',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Shipped');
    }

    public function test_admin_stats_revenue_ranges(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats?range=day')
            ->assertOk()
            ->assertJsonPath('revenue_range', 'day')
            ->assertJsonPath('revenue_period_label', 'Today')
            ->assertJsonCount(24, 'revenue_series');

        $this->getJson('/api/admin/stats?range=week')
            ->assertOk()
            ->assertJsonPath('revenue_range', 'week')
            ->assertJsonCount(7, 'revenue_series');

        $this->getJson('/api/admin/stats?range=month')
            ->assertOk()
            ->assertJsonPath('revenue_range', 'month')
            ->assertJsonPath('revenue_period_label', now()->format('F Y'))
            ->assertJsonCount(now()->daysInMonth, 'revenue_series');

        $this->getJson('/api/admin/stats?range=year')
            ->assertOk()
            ->assertJsonPath('revenue_range', 'year')
            ->assertJsonPath('revenue_period_label', (string) now()->year)
            ->assertJsonCount(12, 'revenue_series');

        $this->getJson('/api/admin/stats?range=invalid')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['range']);
    }

    public function test_day_revenue_points_include_exact_orders_in_morning_and_afternoon(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-09 12:00:00', 'Asia/Kolkata'));

        try {
            $admin = User::factory()->admin()->create();
            Sanctum::actingAs($admin);

            $morningFirst = $this->makeDashboardOrder(
                'VM-DAY-AM-1',
                100,
                Carbon::parse('2026-08-09 09:15:05', 'Asia/Kolkata')->utc(),
            );
            $morningSecond = $this->makeDashboardOrder(
                'VM-DAY-AM-2',
                150,
                Carbon::parse('2026-08-09 09:45:30', 'Asia/Kolkata')->utc(),
            );
            $afternoon = $this->makeDashboardOrder(
                'VM-DAY-PM-1',
                200,
                Carbon::parse('2026-08-09 15:07:12', 'Asia/Kolkata')->utc(),
            );
            $this->makeDashboardOrder(
                'VM-DAY-CANCELLED',
                999,
                Carbon::parse('2026-08-09 09:30:00', 'Asia/Kolkata')->utc(),
                'Cancelled',
            );

            $response = $this->getJson('/api/admin/stats?range=day')
                ->assertOk()
                ->assertJsonPath('revenue_period_total', 450)
                ->assertJsonPath('revenue_period_orders', 3)
                ->assertJsonPath('revenue_series.9.key', '09')
                ->assertJsonPath('revenue_series.9.label', '9 AM')
                ->assertJsonPath('revenue_series.9.total', 250)
                ->assertJsonCount(2, 'revenue_series.9.orders')
                ->assertJsonPath('revenue_series.9.orders.0.id', $morningFirst->id)
                ->assertJsonPath('revenue_series.9.orders.0.number', 'VM-DAY-AM-1')
                ->assertJsonPath(
                    'revenue_series.9.orders.0.created_at',
                    $morningFirst->created_at->toIso8601String(),
                )
                ->assertJsonPath('revenue_series.9.orders.0.created_at_display', '9:15:05 AM')
                ->assertJsonPath('revenue_series.9.orders.1.id', $morningSecond->id)
                ->assertJsonPath('revenue_series.9.orders.1.created_at_display', '9:45:30 AM')
                ->assertJsonPath('revenue_series.15.key', '15')
                ->assertJsonPath('revenue_series.15.label', '3 PM')
                ->assertJsonPath('revenue_series.15.total', 200)
                ->assertJsonPath('revenue_series.15.orders.0.id', $afternoon->id)
                ->assertJsonPath('revenue_series.15.orders.0.created_at_display', '3:07:12 PM');

            $seriesOrders = collect($response->json('revenue_series'))
                ->flatMap(fn (array $point) => $point['orders'] ?? []);
            $this->assertFalse($seriesOrders->contains('number', 'VM-DAY-CANCELLED'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_cannot_mutate_address_or_delete_audited_order(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys-admin-order',
            'description' => 'Toys',
            'image' => '/products/toys/demo.jpg',
            'featured' => true,
        ]);
        $product = Product::query()->create([
            'external_id' => 'ext-admin-order-1',
            'category_id' => $category->id,
            'name' => 'Plush',
            'slug' => 'plush-admin-order',
            'sku' => 'PLUSH-ADMIN-1',
            'price' => 100,
            'stock' => 2,
            'is_active' => true,
            'image' => '/images/products/demo.jpg',
            'description' => 'Test product',
            'tags' => [],
            'details' => [],
            'gallery' => [],
        ]);

        $order = Order::query()->create([
            'number' => 'VM-TEST-DEL',
            'user_id' => $admin->id,
            'full_name' => 'Admin Buyer',
            'email' => $admin->email,
            'phone' => '9999999999',
            'address' => '1 Admin Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'product_image' => null,
            'unit_price' => 100,
            'quantity' => 3,
            'line_total' => 300,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', false);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'full_name' => 'Updated Buyer',
            'email' => 'updated@example.com',
            'phone' => '9888777666',
            'address' => '22 New Road',
            'city' => 'Surat',
            'state' => 'Gujarat',
            'postal_code' => '395001',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Nothing to update.');

        $order->refresh();
        $this->assertSame('Admin Buyer', $order->full_name);
        $this->assertSame('Ahmedabad', $order->city);
        $this->assertSame('380001', $order->postal_code);

        $this->deleteJson("/api/admin/orders/{$order->id}")
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'This order cannot be deleted because it has already been confirmed or is in fulfillment. Cancel the order instead if it must be stopped.',
            );

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertSame(2, $product->fresh()->stock);
    }

    public function test_admin_can_delete_cancelled_test_order(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'number' => 'VM-TEST-CANCEL-DEL',
            'user_id' => $admin->id,
            'full_name' => 'Test Buyer',
            'email' => $admin->email,
            'phone' => '9999999999',
            'address' => '1 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Cancelled',
            'payment_method' => 'razorpay',
            'payment_status' => 'failed',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.can_delete', true);

        $this->deleteJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Order deleted.');

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
    }

    public function test_non_admin_cannot_delete_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::query()->create([
            'number' => 'VM-TEST-FORBIDDEN',
            'user_id' => $user->id,
            'full_name' => 'Buyer',
            'email' => $user->email,
            'phone' => '9999999999',
            'address' => '1 Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 50,
            'shipping' => 0,
            'tax' => 0,
            'total' => 50,
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/admin/orders/{$order->id}")->assertForbidden();
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_non_admin_cannot_access_admin_apis(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_guest_cannot_access_admin_apis(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }

    public function test_guest_html_accept_on_admin_api_returns_401_not_login_route_500(): void
    {
        $stats = $this->get('/api/admin/stats', ['Accept' => 'text/html']);
        $stats->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated')
            ->assertJsonPath('message', 'Please sign in to continue.');
        $this->assertStringNotContainsString('Route [login]', $stats->getContent());

        $errors = $this->get('/api/admin/errors', ['Accept' => 'text/html']);
        $errors->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
        $this->assertStringNotContainsString('Route [login]', $errors->getContent());
    }

    private function makeDashboardOrder(
        string $number,
        float $total,
        \DateTimeInterface $createdAt,
        string $status = 'Processing',
    ): Order {
        $order = Order::query()->create([
            'number' => $number,
            'user_id' => null,
            'full_name' => 'Dashboard Buyer',
            'email' => 'dashboard@example.com',
            'phone' => '9999999999',
            'address' => '1 Dashboard Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => $total,
            'shipping' => 0,
            'tax' => 0,
            'total' => $total,
            'status' => $status,
        ]);

        $order->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();

        return $order->fresh();
    }
}
