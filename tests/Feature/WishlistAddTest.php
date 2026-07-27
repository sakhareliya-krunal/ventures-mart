<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistAddTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();
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
            'slug' => 'test-product-'.uniqid(),
            'name' => 'Test Product',
            'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id,
            'price' => 199,
            'compare_at_price' => null,
            'rating' => 4.5,
            'reviews' => 0,
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'hover_image' => null,
            'badge' => null,
            'tags' => [],
            'description' => 'A test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }

    public function test_add_creates_wishlist_item_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();

        $this->actingAs($user)
            ->postJson('/api/wishlist/add', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('wished', true)
            ->assertJsonPath('added', true)
            ->assertJsonPath('count', 1);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_add_does_not_duplicate_existing_item(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct();

        WishlistItem::query()->create([
            'user_id' => $user->id,
            'session_id' => null,
            'product_id' => $product->id,
        ]);

        $this->actingAs($user)
            ->postJson('/api/wishlist/add', ['product_id' => $product->id])
            ->assertOk()
            ->assertJsonPath('wished', true)
            ->assertJsonPath('added', false)
            ->assertJsonPath('count', 1);

        $this->assertSame(1, WishlistItem::query()->where('user_id', $user->id)->count());
    }

    public function test_add_rejects_inactive_product(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['is_active' => false]);

        $this->actingAs($user)
            ->postJson('/api/wishlist/add', ['product_id' => $product->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);

        $this->assertDatabaseMissing('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_add_rejects_variant_outside_group(): void
    {
        $user = User::factory()->create();
        $product = $this->makeProduct(['variant_group_id' => 'group-a']);
        $other = $this->makeProduct(['variant_group_id' => 'group-b']);

        $this->actingAs($user)
            ->postJson('/api/wishlist/add', [
                'product_id' => $product->id,
                'variant_id' => $other->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant_id']);
    }

    public function test_add_accepts_matching_variant_and_wishlists_variant(): void
    {
        $user = User::factory()->create();
        $group = 'group-'.uniqid();
        $product = $this->makeProduct([
            'variant_group_id' => $group,
            'color_name' => 'Red',
        ]);
        $variant = $this->makeProduct([
            'variant_group_id' => $group,
            'color_name' => 'Blue',
        ]);

        $this->actingAs($user)
            ->postJson('/api/wishlist/add', [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
            ])
            ->assertOk()
            ->assertJsonPath('wished', true)
            ->assertJsonPath('added', true);

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $user->id,
            'product_id' => $variant->id,
        ]);
    }
}
