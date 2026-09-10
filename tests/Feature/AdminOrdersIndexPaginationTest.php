<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrdersIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_orders_index_paginates_ten_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        for ($i = 1; $i <= 11; $i++) {
            Order::query()->create([
                'number' => "VM-PAGE-{$i}",
                'user_id' => $admin->id,
                'full_name' => 'Buyer',
                'email' => $admin->email,
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
            ]);
        }

        $pageOne = $this->getJson('/api/admin/orders?per_page=10&page=1')
            ->assertOk();

        $pageOne->assertJsonCount(10, 'data');
        $pageOne->assertJsonPath('meta.total', 11);
        $pageOne->assertJsonPath('meta.last_page', 2);
        $pageOne->assertJsonPath('meta.per_page', 10);
        $pageOne->assertJsonPath('meta.current_page', 1);

        $this->getJson('/api/admin/orders?per_page=10&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }
}
