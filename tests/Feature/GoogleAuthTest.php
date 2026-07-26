<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Services\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withCredentials();
        config(['services.google.client_id' => 'test-google-client-id.apps.googleusercontent.com']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeGoogleProfile(array $overrides = [], int $times = 1): void
    {
        $profile = array_merge([
            'sub' => 'google-sub-123',
            'email' => 'google.user@example.com',
            'email_verified' => true,
            'name' => 'Google User',
            'picture' => 'https://example.com/avatar.jpg',
        ], $overrides);

        $this->mock(GoogleIdTokenVerifier::class, function ($mock) use ($profile, $times) {
            $mock->shouldReceive('verify')
                ->times($times)
                ->with('fake-google-credential')
                ->andReturn($profile);
        });
    }

    public function test_register_with_google_creates_account(): void
    {
        $this->fakeGoogleProfile();

        $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'register',
        ])
            ->assertCreated()
            ->assertJsonPath('user.email', 'google.user@example.com')
            ->assertJsonPath('user.has_password', false);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'google.user@example.com',
            'google_id' => 'google-sub-123',
        ]);
    }

    public function test_register_with_google_signs_in_existing_account(): void
    {
        $user = User::factory()->create([
            'email' => 'google.user@example.com',
            'password' => 'password',
            'google_id' => null,
        ]);

        $this->fakeGoogleProfile();

        $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'register',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'google.user@example.com')
            ->assertJsonPath('user.id', $user->id);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-sub-123',
        ]);
    }

    public function test_login_with_google_signs_in_existing_user_and_links_google_id(): void
    {
        $user = User::factory()->create([
            'email' => 'google.user@example.com',
            'password' => 'password',
            'google_id' => null,
        ]);

        $this->fakeGoogleProfile();

        $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'login',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'google.user@example.com');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'google_id' => 'google-sub-123',
        ]);
    }

    public function test_login_with_google_rejects_missing_account(): void
    {
        $this->fakeGoogleProfile();

        $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'login',
        ])
            ->assertNotFound()
            ->assertJsonPath('code', 'account_missing');

        $this->assertGuest();
    }

    public function test_google_register_logout_login_restores_same_user_and_addresses(): void
    {
        $this->fakeGoogleProfile(times: 2);

        $register = $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'register',
        ])->assertCreated();

        $userId = $register->json('user.id');
        $this->assertNotNull($userId);
        $this->assertAuthenticated();

        Address::query()->create([
            'user_id' => $userId,
            'label' => 'Home',
            'full_name' => 'Google User',
            'phone' => '555-0100',
            'address' => '123 Main St',
            'city' => 'Austin',
            'state' => 'TX',
            'postal_code' => '78701',
            'is_default' => true,
        ]);

        $this->postJson('/api/logout')->assertOk();
        $this->assertGuest();

        $login = $this->postJson('/api/auth/google', [
            'credential' => 'fake-google-credential',
            'intent' => 'login',
        ])
            ->assertOk()
            ->assertJsonPath('user.id', $userId)
            ->assertJsonPath('user.email', 'google.user@example.com');

        $this->assertAuthenticated();
        $this->assertSame($userId, $login->json('user.id'));

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonPath('data.0.address', '123 Main St')
            ->assertJsonPath('data.0.label', 'Home');
    }
}
