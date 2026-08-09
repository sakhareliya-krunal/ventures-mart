<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryRolloutCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollout_check_blocks_missing_balance_then_passes_after_initialization(): void
    {
        $category = Category::query()->create([
            'name' => 'Rollout',
            'slug' => 'rollout',
            'description' => 'Rollout tests',
            'image' => '/images/rollout.jpg',
            'featured' => false,
        ]);
        $product = Product::query()->create([
            'external_id' => 'rollout-product',
            'slug' => 'rollout-product',
            'name' => 'Rollout Product',
            'sku' => 'ROLLOUT-1',
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 0,
            'reviews' => 0,
            'image' => '/images/rollout.jpg',
            'description' => 'Rollout test product',
            'tags' => [],
            'details' => [],
            'gallery' => [],
            'stock' => 4,
            'is_active' => true,
        ]);

        $this->artisan('inventory:rollout-check')
            ->expectsOutputToContain('[FAIL] products_without_balance: 1')
            ->assertFailed();

        app(InventoryService::class)->ensureBalance($product);

        $this->artisan('inventory:rollout-check')
            ->expectsOutputToContain('Inventory rollout checks passed.')
            ->assertSuccessful();
    }
}
