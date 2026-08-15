<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductJsonLdImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://venturesmart.test']);
    }

    public function test_product_json_ld_includes_https_urls_for_existing_images(): void
    {
        $product = $this->makeProduct([
            'slug' => 'steel-lunch-box',
            'image' => '/storage/products/abc/main.webp',
            'gallery' => [
                '/storage/products/abc/main.webp',
                '/storage/products/abc/side.webp',
            ],
        ]);

        $productSchema = $this->productSchemaFor($product->slug);

        $this->assertSame('Product', $productSchema['@type']);
        $this->assertSame([
            'https://venturesmart.test/storage/products/abc/main.webp',
            'https://venturesmart.test/storage/products/abc/side.webp',
        ], $productSchema['image']);
    }

    public function test_product_json_ld_uses_a_string_when_only_one_image_exists(): void
    {
        $product = $this->makeProduct([
            'slug' => 'single-photo-product',
            'image' => '/storage/products/abc/main.webp',
            'gallery' => [],
        ]);

        $productSchema = $this->productSchemaFor($product->slug);

        $this->assertSame(
            'https://venturesmart.test/storage/products/abc/main.webp',
            $productSchema['image'],
        );
    }

    public function test_product_json_ld_omits_image_when_none_exist(): void
    {
        $product = $this->makeProduct([
            'slug' => 'no-photo-product',
            'image' => '',
            'hover_image' => null,
            'gallery' => [],
        ]);

        $productSchema = $this->productSchemaFor($product->slug);

        $this->assertSame('Product', $productSchema['@type']);
        $this->assertArrayNotHasKey('image', $productSchema);
    }

    /**
     * @return array<string, mixed>
     */
    private function productSchemaFor(string $slug): array
    {
        $payload = $this->getJson('/api/seo?path=/product/'.$slug)
            ->assertOk()
            ->json('json_ld');

        foreach ($payload as $schema) {
            if (($schema['@type'] ?? null) === 'Product') {
                return $schema;
            }
        }

        $this->fail('Product JSON-LD was not present.');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeProduct(array $overrides = []): Product
    {
        $category = Category::query()->create([
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
            'rating' => 0,
            'reviews' => 0,
            'image' => '/storage/products/abc/main.webp',
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
