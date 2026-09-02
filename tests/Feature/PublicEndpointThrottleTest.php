<?php

namespace Tests\Feature;

use App\Mail\ContactFormSubmitted;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicEndpointThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_form_is_rate_limited(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/contact', [
                'name' => 'Test User',
                'email' => "customer{$i}@example.com",
                'message' => 'Please confirm contact mail works.',
            ])->assertCreated();
        }

        $this->postJson('/api/contact', [
            'name' => 'Test User',
            'email' => 'customer-final@example.com',
            'message' => 'Please confirm contact mail works.',
        ])->assertStatus(429);

        Mail::assertSent(ContactFormSubmitted::class, 5);
    }

    public function test_public_product_reviews_are_rate_limited(): void
    {
        $product = $this->makeProduct();

        for ($i = 0; $i < 10; $i++) {
            $this->postJson("/api/products/{$product->slug}/reviews", [
                'author_name' => "Reviewer {$i}",
                'rating' => 5,
                'body' => 'This product was exactly what we needed.',
            ])->assertCreated();
        }

        $this->postJson("/api/products/{$product->slug}/reviews", [
            'author_name' => 'Reviewer final',
            'rating' => 5,
            'body' => 'This product was exactly what we needed.',
        ])->assertStatus(429);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', [
                'email' => 'missing@example.com',
                'password' => 'wrong-password',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/login', [
            'email' => 'missing@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_cart_and_wishlist_mutations_have_generous_throttles(): void
    {
        $this->assertRouteHasMiddleware('POST', 'api/cart', 'throttle:60,1');
        $this->assertRouteHasMiddleware('PATCH', 'api/cart/items/{product}', 'throttle:60,1');
        $this->assertRouteHasMiddleware('DELETE', 'api/cart/items/{product}', 'throttle:60,1');
        $this->assertRouteHasMiddleware('DELETE', 'api/cart', 'throttle:60,1');
        $this->assertRouteHasMiddleware('POST', 'api/wishlist/toggle', 'throttle:60,1');
        $this->assertRouteHasMiddleware('POST', 'api/wishlist/add', 'throttle:60,1');
        $this->assertRouteHasMiddleware('DELETE', 'api/wishlist/{product}', 'throttle:60,1');
    }

    private function assertRouteHasMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = collect(Route::getRoutes())->first(function ($route) use ($method, $uri) {
            return in_array($method, $route->methods(), true)
                && $route->uri() === $uri;
        });

        $this->assertNotNull($route, "Route {$method} {$uri} was not found.");
        $this->assertContains($middleware, $route->gatherMiddleware());
    }

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