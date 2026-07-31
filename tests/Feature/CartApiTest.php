<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_cart_payload_uses_slim_product_shape(): void
    {
        $product = $this->makeProduct([
            'stock' => 10,
            'price' => 199,
        ]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.product.id', $product->id)
            ->assertJsonPath('items.0.product.slug', $product->slug)
            ->assertJsonPath('items.0.product.name', $product->name)
            ->assertJsonPath('items.0.product.price', 199)
            ->assertJsonPath('items.0.product.stock', 10)
            ->assertJsonStructure([
                'items' => [
                    [
                        'product_id',
                        'quantity',
                        'product' => ['id', 'slug', 'name', 'price', 'image', 'stock'],
                    ],
                ],
                'item_count',
                'totals' => ['subtotal', 'shipping', 'cgst', 'sgst', 'igst', 'tax', 'tax_type', 'total'],
            ])
            ->assertJsonMissingPath('items.0.product.description')
            ->assertJsonMissingPath('items.0.product.gallery')
            ->assertJsonMissingPath('items.0.product.variants');
    }

    public function test_cart_quantity_can_be_updated_and_removed(): void
    {
        $product = $this->makeProduct(['stock' => 8, 'price' => 100]);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $this->patchJson("/api/cart/items/{$product->id}", ['quantity' => 3])
            ->assertOk()
            ->assertJsonPath('items.0.quantity', 3)
            ->assertJsonPath('items.0.product.stock', 8)
            ->assertJsonPath('item_count', 1);

        $this->deleteJson("/api/cart/items/{$product->id}")
            ->assertOk()
            ->assertJsonPath('item_count', 0)
            ->assertJsonPath('items', []);
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
            'slug' => 'cart-product-'.uniqid(),
            'name' => 'Cart Product',
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
            'description' => 'A long description that must not appear in cart payloads.',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ], $overrides));
    }
}
