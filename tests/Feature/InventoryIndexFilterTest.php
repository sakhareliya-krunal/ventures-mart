<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_filters_and_default_page_size(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $inStock = $this->makeProduct(['name' => 'In Stock Toy', 'sku' => 'IN-1']);
        $lowStock = $this->makeProduct(['name' => 'Low Stock Toy', 'sku' => 'LOW-1']);
        $outOfStock = $this->makeProduct(['name' => 'Out Of Stock Toy', 'sku' => 'OOS-1']);

        $this->seedBalance($inStock, onHand: 20, threshold: 5);
        $this->seedBalance($lowStock, onHand: 3, threshold: 5);
        $this->seedBalance($outOfStock, onHand: 0, threshold: 5);

        $this->getJson('/api/admin/inventory')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/admin/inventory?status=out_of_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.sku', 'OOS-1');

        $this->getJson('/api/admin/inventory?status=low_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.sku', 'LOW-1');

        $this->getJson('/api/admin/inventory?status=in_stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.sku', 'IN-1');

        $this->getJson('/api/admin/inventory/summary')
            ->assertOk()
            ->assertJsonPath('low_stock_count', 1)
            ->assertJsonPath('out_of_stock_count', 1);
    }

    private function seedBalance(Product $product, int $onHand, int $threshold = 5): InventoryBalance
    {
        return InventoryBalance::query()->create([
            'product_id' => $product->id,
            'on_hand' => $onHand,
            'reserved' => 0,
            'committed' => 0,
            'version' => 0,
            'low_stock_threshold' => $threshold,
            'reorder_point' => $threshold + 5,
        ]);
    }

    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->first() ?? Category::query()->create([
            'name' => 'Toys',
            'slug' => 'inventory-filter-'.uniqid(),
            'description' => 'Toys',
            'image' => '/images/toys.jpg',
            'featured' => true,
        ]);

        return Product::query()->create(array_merge([
            'external_id' => 'ext-'.uniqid(),
            'slug' => 'inventory-'.uniqid(),
            'name' => 'Inventory Product',
            'sku' => 'INV-'.uniqid(),
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 5,
            'reviews' => 0,
            'image' => '/images/product.jpg',
            'tags' => [],
            'description' => 'Test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
