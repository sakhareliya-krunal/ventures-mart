<?php

namespace Tests\Feature;

use App\Models\SeoMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaticPageSeoImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://venturesmart.test']);
    }

    public function test_static_page_exposes_its_explicit_admin_seo_image(): void
    {
        SeoMetadata::query()->create([
            'page_key' => 'shopping-confidence-shipping-replacement',
            'locale' => 'en-IN',
            'og_image' => '/storage/products/confidence.webp',
        ]);

        $this->getJson('/api/seo?path=/shopping-confidence-shipping-replacement')
            ->assertOk()
            ->assertJsonPath(
                'page_image',
                'https://venturesmart.test/storage/products/confidence.webp',
            );
    }

    public function test_static_page_image_is_null_when_only_the_global_fallback_exists(): void
    {
        $this->getJson('/api/seo?path=/shopping-confidence-shipping-replacement')
            ->assertOk()
            ->assertJsonPath('page_image', null)
            ->assertJsonPath('og.image', 'https://venturesmart.test/images/ventures-mart-logo.png');
    }
}
