<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductStorefrontAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_out_of_stock_product_cannot_be_added_to_cart(): void
    {
        $product = $this->makeProduct(['stock' => 0]);

        $this->postJson('/api/cart', ['product_id' => $product->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);
    }

    public function test_inactive_product_is_hidden_from_public_api_and_cart(): void
    {
        $hidden = $this->makeProduct([
            'slug' => 'hidden-box',
            'sku' => 'HID-1',
            'is_active' => false,
            'stock' => 5,
        ]);
        $visible = $this->makeProduct([
            'slug' => 'visible-box',
            'sku' => 'VIS-1',
            'is_active' => true,
            'stock' => 5,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonMissing(['slug' => 'hidden-box'])
            ->assertJsonFragment(['slug' => 'visible-box']);

        $this->getJson('/api/products/hidden-box')->assertNotFound();

        $this->postJson('/api/cart', ['product_id' => $hidden->id, 'quantity' => 1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product']);
    }

    public function test_sale_endpoint_requires_compare_at_above_price(): void
    {
        $this->makeProduct([
            'slug' => 'fake-sale',
            'sku' => 'SALE-FAKE',
            'price' => 100,
            'compare_at_price' => 80,
        ]);
        $this->makeProduct([
            'slug' => 'real-sale',
            'sku' => 'SALE-REAL',
            'price' => 80,
            'compare_at_price' => 100,
        ]);

        $this->getJson('/api/products/sale')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'real-sale'])
            ->assertJsonMissing(['slug' => 'fake-sale']);
    }

    public function test_price_bounds_reflects_active_catalog_min_and_max(): void
    {
        $this->makeProduct([
            'slug' => 'cheap-box',
            'sku' => 'CHEAP-1',
            'price' => 599,
            'is_active' => true,
        ]);
        $this->makeProduct([
            'slug' => 'pricey-box',
            'sku' => 'PRICE-1',
            'price' => 1499,
            'is_active' => true,
        ]);
        $this->makeProduct([
            'slug' => 'hidden-pricey',
            'sku' => 'HID-PRICE',
            'price' => 9999,
            'is_active' => false,
        ]);

        $this->getJson('/api/products/price-bounds')
            ->assertOk()
            ->assertJsonPath('min', 599)
            ->assertJsonPath('max', 1499);
    }

    public function test_products_can_be_filtered_by_category_slug(): void
    {
        $lunch = Category::query()->create([
            'name' => 'Lunch Box',
            'slug' => 'lunch-box',
            'description' => 'Lunch boxes',
            'image' => '/products/lunch-box/delicious-steel-lunch-box/01.jpg',
            'featured' => true,
        ]);
        $toys = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'description' => 'Toys',
            'image' => '/products/toys/wooden-building-blocks/01.jpg',
            'featured' => true,
        ]);

        $this->makeProduct([
            'slug' => 'steel-lunch',
            'sku' => 'LB-1',
            'category_id' => $lunch->id,
            'name' => 'Steel Lunch',
        ]);
        $this->makeProduct([
            'slug' => 'wood-blocks',
            'sku' => 'TY-1',
            'category_id' => $toys->id,
            'name' => 'Wood Blocks',
        ]);

        $response = $this->getJson('/api/products?category=lunch-box')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'steel-lunch', 'category' => 'lunch-box'])
            ->assertJsonMissing(['slug' => 'wood-blocks']);

        $categories = collect($response->json('data'))->pluck('category')->unique()->values()->all();
        $this->assertSame(['lunch-box'], $categories);
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
}
