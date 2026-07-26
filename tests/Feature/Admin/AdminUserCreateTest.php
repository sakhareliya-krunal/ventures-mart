<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserCreateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_admin_can_create_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new-admin@example.com')
            ->assertJsonPath('data.is_admin', true);

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@example.com',
            'is_admin' => true,
        ]);
    }

    public function test_create_admin_rejects_duplicate_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/users', [
            'name' => 'Dup Admin',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_guest_cannot_create_admin(): void
    {
        $this->postJson('/api/admin/users', [
            'name' => 'New Admin',
            'email' => 'new-admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnauthorized();
    }

    public function test_admin_can_list_customers_excluding_admins(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'boss@example.com']);
        User::factory()->create(['email' => 'buyer@example.com']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?role=customer')
            ->assertOk()
            ->assertJsonMissing(['email' => 'boss@example.com'])
            ->assertJsonFragment(['email' => 'buyer@example.com']);
    }

    public function test_admin_can_list_admins_only(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'boss@example.com']);
        User::factory()->create(['email' => 'buyer@example.com']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?role=admin')
            ->assertOk()
            ->assertJsonFragment(['email' => 'boss@example.com'])
            ->assertJsonMissing(['email' => 'buyer@example.com']);
    }
}
