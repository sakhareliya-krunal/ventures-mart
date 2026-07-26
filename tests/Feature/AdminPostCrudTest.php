<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPostCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_posts(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $create = $this->postJson('/api/admin/posts', [
            'title' => 'School Lunch Tips',
            'slug' => null,
            'excerpt' => str_repeat('e', 280),
            'body' => '<p>Pack smart portions for short breaks.</p>',
            'cover_image' => null,
            'published_at' => '2026-07-25T22:30',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.title', 'School Lunch Tips')
            ->assertJsonPath('data.slug', 'school-lunch-tips');

        $id = $create->json('data.id');

        $this->putJson("/api/admin/posts/{$id}", [
            'title' => 'School Lunch Tips Updated',
            'slug' => 'school-lunch-tips',
            'excerpt' => 'Updated excerpt',
            'body' => '<p>Updated body</p>',
            'published_at' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'School Lunch Tips Updated')
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('posts', [
            'id' => $id,
            'title' => 'School Lunch Tips Updated',
        ]);

        $this->deleteJson("/api/admin/posts/{$id}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted.');

        $this->assertDatabaseMissing('posts', ['id' => $id]);
    }

    public function test_admin_create_auto_suffixes_duplicate_slugs(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        Post::query()->create([
            'title' => 'Existing',
            'slug' => 'same-slug',
            'excerpt' => 'Existing excerpt',
            'body' => 'Existing body',
            'published_at' => now(),
        ]);

        $this->postJson('/api/admin/posts', [
            'title' => 'Another',
            'slug' => 'same-slug',
            'excerpt' => 'Another excerpt',
            'body' => 'Another body',
            'published_at' => now()->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'same-slug-2');
    }

    public function test_non_admin_cannot_manage_posts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/admin/posts', [
            'title' => 'Nope',
            'excerpt' => 'Nope',
            'body' => 'Nope',
        ])->assertForbidden();
    }
}
