<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCustomerDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withCredentials();
    }

    public function test_admin_can_hard_delete_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create([
            'name' => 'Buyer',
            'email' => 'buyer-delete@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
    }

    public function test_admin_cannot_delete_admin_user(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create([
            'email' => 'other-admin@example.com',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$otherAdmin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Admin accounts cannot be deleted from customers.');

        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$admin->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'You cannot delete your own account.');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_deleting_customer_nulls_order_user_id(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'order-buyer@example.com']);

        $order = Order::query()->create([
            'number' => 'VM-TEST-CUST-DEL',
            'user_id' => $customer->id,
            'full_name' => 'Order Buyer',
            'email' => $customer->email,
            'phone' => '9999999999',
            'address' => '1 Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 0,
            'total' => 100,
            'status' => 'Processing',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/users/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => null,
            'full_name' => 'Order Buyer',
        ]);
    }

    public function test_guest_cannot_delete_customer(): void
    {
        $customer = User::factory()->create();

        $this->deleteJson("/api/admin/users/{$customer->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('users', ['id' => $customer->id]);
    }
}
