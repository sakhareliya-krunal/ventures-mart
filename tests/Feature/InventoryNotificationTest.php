<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InventoryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_notification_is_deduplicated_and_resets_after_replenishment(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $product = $this->product(6);
        $inventory = app(InventoryService::class);
        $balance = $inventory->ensureBalance($product);
        $this->artisan('inventory:dispatch-outbox')->assertSuccessful();

        $inventory->adjust($product, 'decrease', 1, 'Sale correction', 'test:low:1');
        $this->artisan('inventory:dispatch-outbox')->assertSuccessful();
        Notification::assertSentToTimes($admin, LowStockNotification::class, 1);

        $inventory->adjust($product, 'decrease', 1, 'Sale correction', 'test:low:2');
        $this->artisan('inventory:dispatch-outbox')->assertSuccessful();
        Notification::assertSentToTimes($admin, LowStockNotification::class, 1);

        $inventory->adjust($product, 'receive', 10, 'Restock', 'test:restock');
        $this->artisan('inventory:dispatch-outbox')->assertSuccessful();

        $this->assertNull($balance->fresh()->low_stock_notified_at);
    }

    private function product(int $stock): Product
    {
        $category = Category::query()->create([
            'name' => 'Inventory',
            'slug' => 'inventory-notifications',
            'description' => 'Inventory',
            'image' => '/images/products/demo.jpg',
            'featured' => false,
        ]);

        return Product::query()->create([
            'external_id' => 'inventory-notification-product',
            'slug' => 'inventory-notification-product',
            'name' => 'Inventory notification product',
            'sku' => 'INV-NOTIFY-1',
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 0,
            'reviews' => 0,
            'image' => '/images/products/demo.jpg',
            'tags' => [],
            'description' => 'Inventory notification test product',
            'details' => [],
            'stock' => $stock,
            'is_active' => true,
            'gallery' => [],
            'low_stock_threshold' => 5,
        ]);
    }
}
