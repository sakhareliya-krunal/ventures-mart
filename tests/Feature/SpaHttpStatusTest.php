<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaHttpStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_spa_route_returns_success(): void
    {
        $this->get('/about')
            ->assertOk();
    }

    public function test_unknown_spa_route_returns_real_not_found_status_and_noindex(): void
    {
        $this->get('/qa-route-that-does-not-exist')
            ->assertNotFound()
            ->assertSee('noindex,follow', escape: false);
    }

    public function test_unknown_dynamic_content_route_returns_real_not_found_status(): void
    {
        $this->get('/product/qa-product-that-does-not-exist')->assertNotFound();
        $this->get('/category/qa-category-that-does-not-exist')->assertNotFound();
        $this->get('/blog/qa-post-that-does-not-exist')->assertNotFound();
    }
}
