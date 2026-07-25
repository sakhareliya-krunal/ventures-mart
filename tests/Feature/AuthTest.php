<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // SPA cookie session auth — JSON requests must send cookies.
        $this->withCredentials();
    }

    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'shopper@example.com',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'shopper@example.com')
            ->assertJsonPath('user.is_admin', false);

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.is_admin', false);
    }

    public function test_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'shopper@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'shopper@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->assertAuthenticated();

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Logged out']);

        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_admin_can_access_admin_me(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'sakhareliyakrunal33@gmail.com',
            'password' => 'password',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'sakhareliyakrunal33@gmail.com')
            ->assertJsonPath('data.is_admin', true);
    }

    public function test_non_admin_cannot_access_admin_me(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/me')->assertForbidden();
    }

    public function test_guest_cannot_access_admin_me(): void
    {
        $this->getJson('/api/admin/me')->assertUnauthorized();
    }
}
