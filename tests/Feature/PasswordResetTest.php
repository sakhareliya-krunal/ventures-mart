<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_forgot_password_sends_reset_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'reset@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If that email is registered, we sent a password reset link.',
            );

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_forgot_password_does_not_reveal_missing_email(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', [
            'email' => 'missing@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If that email is registered, we sent a password reset link.',
            );

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'password',
        ]);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password reset successfully. You can sign in with your new password.',
            );

        $this->assertTrue(Hash::check('fresh-password', $user->fresh()->password));

        $this->postJson('/api/login', [
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create([
            'email' => 'reset@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => 'reset@example.com',
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertUnprocessable();
    }
}
