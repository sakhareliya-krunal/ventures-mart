<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminBannerCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_banner_endpoint_lists_active_banners_in_sort_order(): void
    {
        Banner::query()->delete();

        Banner::query()->create([
            'mobile_image' => '/storage/banners/second-mobile.webp',
            'web_image' => '/storage/banners/second-web.webp',
            'alt_text' => 'Second banner',
            'sort_order' => 20,
            'is_active' => true,
        ]);
        Banner::query()->create([
            'mobile_image' => '/storage/banners/hidden-mobile.webp',
            'web_image' => '/storage/banners/hidden-web.webp',
            'alt_text' => 'Hidden banner',
            'sort_order' => 10,
            'is_active' => false,
        ]);
        Banner::query()->create([
            'mobile_image' => '/storage/banners/first-mobile.webp',
            'web_image' => '/storage/banners/first-web.webp',
            'alt_text' => 'First banner',
            'sort_order' => 5,
            'is_active' => true,
        ]);

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.alt_text', 'First banner')
            ->assertJsonPath('data.1.alt_text', 'Second banner');
    }

    public function test_admin_can_create_update_and_delete_banners(): void
    {
        Banner::query()->delete();
        Sanctum::actingAs(User::factory()->admin()->create());

        $create = $this->postJson('/api/admin/banners', [
            'mobile_image' => '/storage/banners/mobile.webp',
            'web_image' => '/storage/banners/web.webp',
            'alt_text' => 'Launch banner',
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.mobile_image', '/storage/banners/mobile.webp')
            ->assertJsonPath('data.web_image', '/storage/banners/web.webp')
            ->assertJsonPath('data.alt_text', 'Launch banner');

        $id = $create->json('data.id');

        $this->putJson("/api/admin/banners/{$id}", [
            'mobile_image' => '/storage/banners/mobile-updated.webp',
            'web_image' => '/storage/banners/web-updated.webp',
            'alt_text' => 'Updated banner',
            'sort_order' => 1,
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.mobile_image', '/storage/banners/mobile-updated.webp')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('banners', [
            'id' => $id,
            'web_image' => '/storage/banners/web-updated.webp',
            'is_active' => false,
        ]);

        $this->deleteJson("/api/admin/banners/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Banner deleted.');

        $this->assertDatabaseMissing('banners', ['id' => $id]);
    }

    public function test_banner_admin_requires_admin_access_and_valid_images(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/admin/banners')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/admin/banners', [
            'alt_text' => 'Missing images',
        ])->assertUnprocessable();
    }

}
