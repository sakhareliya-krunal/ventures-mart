<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardStatsListLimitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_stats_widget_lists_are_capped_at_four(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $category = Category::query()->create([
            'name' => 'Dashboard Toys',
            'slug' => 'dashboard-toys',
            'description' => 'Toys',
            'image' => '/products/toys/demo.jpg',
            'featured' => false,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            Product::query()->create([
                'external_id' => "ext-dash-low-{$i}",
                'category_id' => $category->id,
                'name' => "Low Stock Product {$i}",
                'slug' => "low-stock-product-{$i}",
                'sku' => "LOW-{$i}",
                'price' => 100,
                'stock' => $i,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'image' => '/images/products/demo.jpg',
                'description' => 'Test',
                'tags' => [],
                'details' => [],
                'gallery' => [],
            ]);

            ContactMessage::query()->create([
                'name' => "Sender {$i}",
                'email' => "sender{$i}@example.com",
                'message' => "Message {$i}",
                'created_at' => Carbon::now()->subMinutes($i),
                'updated_at' => Carbon::now()->subMinutes($i),
            ]);

            Post::query()->create([
                'title' => "Post {$i}",
                'slug' => "dashboard-post-{$i}",
                'excerpt' => 'Excerpt',
                'body' => 'Body',
                'published_at' => Carbon::now()->subHours($i),
            ]);

            Order::query()->create([
                'number' => "VM-DASH-{$i}",
                'user_id' => $admin->id,
                'full_name' => 'Buyer',
                'email' => $admin->email,
                'phone' => '9999999999',
                'address' => '1 Street',
                'city' => 'Ahmedabad',
                'state' => 'Gujarat',
                'postal_code' => '380001',
                'subtotal' => 100,
                'shipping' => 0,
                'tax' => 0,
                'total' => 100,
                'status' => 'Processing',
                'created_at' => Carbon::now()->subHours($i),
                'updated_at' => Carbon::now()->subHours($i),
            ]);
        }

        $response = $this->getJson('/api/admin/stats')->assertOk();

        $recentOrders = $response->json('recent_orders');
        if (isset($recentOrders['data']) && is_array($recentOrders['data'])) {
            $recentOrders = $recentOrders['data'];
        }

        $this->assertCount(4, $recentOrders);
        $this->assertSame('VM-DASH-1', $recentOrders[0]['number'] ?? null);

        $this->assertCount(4, $response->json('low_stock_products'));
        $this->assertCount(4, $response->json('recent_messages'));
        $this->assertCount(4, $response->json('recent_posts'));
    }
}
