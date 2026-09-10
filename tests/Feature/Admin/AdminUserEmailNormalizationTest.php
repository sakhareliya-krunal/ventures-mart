<?php

namespace Tests\Feature\Admin;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserEmailNormalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_user_resource_normalizes_corrupted_email(): void
    {
        $user = User::factory()->admin()->create([
            'email' => "boss@example.comLOCAL_ADMIN_PASSWORD='secret'",
        ]);

        $payload = (new UserResource($user))->resolve();

        $this->assertSame('boss@example.com', $payload['email']);
    }

    public function test_admin_list_returns_normalized_email(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => "boss@example.comLOCAL_ADMIN_PASSWORD='secret'",
        ]);
        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?role=admin')
            ->assertOk()
            ->assertJsonFragment(['email' => 'boss@example.com'])
            ->assertJsonMissing(['email' => "boss@example.comLOCAL_ADMIN_PASSWORD='secret'"]);
    }
}
