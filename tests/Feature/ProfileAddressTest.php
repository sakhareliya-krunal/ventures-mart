<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileAddressTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        Sanctum::actingAs($user);

        $this->patchJson('/api/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
        ]);
    }

    public function test_user_can_manage_own_addresses(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/addresses', [
            'label' => 'Home',
            'full_name' => 'Test User',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'district' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.district', 'Ahmedabad');
        $addressId = $create->json('data.id');

        $this->getJson('/api/addresses')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->patchJson("/api/addresses/{$addressId}", [
            'label' => 'Office',
            'full_name' => 'Test User',
            'phone' => '9999999999',
            'address' => '99 Office Road',
            'city' => 'Ahmedabad',
            'district' => 'Gandhinagar',
            'state' => 'Gujarat',
            'postal_code' => '380002',
        ])
            ->assertOk()
            ->assertJsonPath('data.label', 'Office')
            ->assertJsonPath('data.district', 'Gandhinagar');

        $this->deleteJson("/api/addresses/{$addressId}")
            ->assertOk();

        $this->assertSame(0, Address::query()->count());
    }

    public function test_user_cannot_edit_another_users_address(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = Address::query()->create([
            'user_id' => $owner->id,
            'label' => 'Home',
            'full_name' => 'Owner',
            'phone' => '111',
            'address' => '1 Road',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => '100001',
            'is_default' => true,
        ]);

        Sanctum::actingAs($intruder);

        $this->patchJson("/api/addresses/{$address->id}", [
            'label' => 'Hack',
            'full_name' => 'Hack',
            'phone' => '222',
            'address' => '2 Road',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => '100002',
        ])->assertNotFound();
    }

    public function test_district_is_required_when_saving_an_address(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/addresses', [
            'label' => 'Home',
            'full_name' => 'Test User',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['district']);
    }
}
