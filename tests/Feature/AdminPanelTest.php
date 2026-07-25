<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_stats_and_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::query()->create([
            'number' => 'VM-TEST-1',
            'user_id' => $admin->id,
            'full_name' => 'Admin Buyer',
            'email' => $admin->email,
            'phone' => '9999999999',
            'address' => '1 Admin Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Processing',
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('orders_count', 1);

        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.number', 'VM-TEST-1');

        $this->getJson("/api/admin/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.number', 'VM-TEST-1');

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Shipped',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'Shipped');
    }

    public function test_non_admin_cannot_access_admin_apis(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/admin/stats')->assertForbidden();
        $this->getJson('/api/admin/orders')->assertForbidden();
    }

    public function test_guest_cannot_access_admin_apis(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
    }
}
