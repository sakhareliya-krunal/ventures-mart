<?php

namespace Tests\Feature;

use App\Enums\FulfillmentMethod;
use App\Mail\ShipmentTracking;
use App\Models\Order;
use App\Models\ShipmentWebhookEvent;
use App\Models\ShiprocketShipment;
use App\Models\User;
use App\Services\OrderShipmentDetails;
use App\Services\ShiprocketFulfillmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FulfillmentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'services.shiprocket.enabled' => true,
            'services.shiprocket.base_url' => 'https://apiv2.shiprocket.in/v1/external',
            'services.shiprocket.email' => 'api@example.test',
            'services.shiprocket.password' => 'secret',
            'services.shiprocket.webhook_token' => 'webhook-secret',
        ]);
    }

    public function test_courier_fields_are_owned_by_the_explicit_fulfillment_method(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $manual = $this->makeOrder(FulfillmentMethod::Manual);
        $shiprocket = $this->makeOrder(FulfillmentMethod::Shiprocket);

        $this->patchJson("/api/admin/orders/{$manual->id}", [
            'courier_partner' => 'Manual Courier',
            'tracking_number' => 'MANUAL-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.courier_partner', 'Manual Courier');

        $this->patchJson("/api/admin/orders/{$shiprocket->id}", [
            'courier_partner' => 'Not Allowed',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Courier fields are managed by Shiprocket for this order.');
    }

    public function test_admin_can_switch_before_awb_and_the_transition_is_audited(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'failed',
            'stage' => 'queued',
        ]);

        $this->postJson("/api/admin/orders/{$order->id}/fulfillment/manual", [
            'reason' => 'Use a local courier',
        ])
            ->assertOk()
            ->assertJsonPath('data.fulfillment_method', 'manual')
            ->assertJsonPath('data.can_switch_to_manual', false);

        $this->assertSame(FulfillmentMethod::Manual, $order->fresh()->fulfillment_method);
        $this->assertSame('switched_to_manual', $shipment->fresh()->stage);
        $this->assertDatabaseHas('order_fulfillment_events', [
            'order_id' => $order->id,
            'actor_user_id' => $admin->id,
            'event_type' => 'manual_switch_completed',
            'previous_method' => 'shiprocket',
            'new_method' => 'manual',
        ]);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'courier_partner' => 'Local Courier',
            'dispatched_at' => now()->toDateString(),
        ])->assertOk();
    }

    public function test_switch_is_rejected_after_awb_assignment(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'awb_assigned',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => 'AWB-LOCKED',
        ]);

        $this->postJson("/api/admin/orders/{$order->id}/fulfillment/manual")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_method']);
        $this->assertSame(FulfillmentMethod::Shiprocket, $order->fresh()->fulfillment_method);
    }

    public function test_remote_cancellation_failure_keeps_shiprocket_ownership(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/orders/cancel' => Http::response(['message' => 'Cannot cancel'], 503),
        ]);
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $order->shiprocketShipment()->create([
            'sync_status' => 'failed',
            'stage' => 'order_created',
            'shiprocket_order_id' => 5002,
            'shipment_id' => 6002,
        ]);

        $this->postJson("/api/admin/orders/{$order->id}/fulfillment/manual")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_method']);

        $this->assertSame(FulfillmentMethod::Shiprocket, $order->fresh()->fulfillment_method);
        $this->assertDatabaseHas('order_fulfillment_events', [
            'order_id' => $order->id,
            'event_type' => 'manual_switch_failed',
        ]);
    }

    public function test_stale_fulfillment_work_is_a_noop_after_manual_switch(): void
    {
        Http::fake();
        $order = $this->makeOrder(FulfillmentMethod::Manual);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order);

        $this->assertNull($shipment);
        Http::assertNothingSent();
    }

    public function test_webhook_is_authenticated_deduplicated_and_updates_tracking_dates(): void
    {
        Mail::fake();
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        ShiprocketShipment::query()->create([
            'order_id' => $order->id,
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'shiprocket_order_id' => 7001,
            'shipment_id' => 8001,
            'awb_code' => 'AWB-WEBHOOK',
        ]);
        $occurredAt = Carbon::parse('2026-08-09 14:35:12', 'Asia/Kolkata');
        $payload = [
            'awb' => 'AWB-WEBHOOK',
            'sr_order_id' => 7001,
            'courier_name' => 'Delhivery Surface',
            'current_status' => 'IN TRANSIT',
            'shipment_status_id' => 18,
            'current_timestamp' => $occurredAt->format('d m Y H:i:s'),
            'etd' => '2026-08-12 18:00:00',
        ];

        $this->postJson('/api/fulfillment/provider-update', $payload)->assertUnauthorized();
        $this->withHeader('x-api-key', 'webhook-secret')
            ->postJson('/api/fulfillment/provider-update', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'accepted');
        $this->withHeader('x-api-key', 'webhook-secret')
            ->postJson('/api/fulfillment/provider-update', $payload)
            ->assertOk();

        $this->assertSame(1, ShipmentWebhookEvent::query()->count());
        $this->assertDatabaseHas('shipment_webhook_events', ['status' => 'processed']);
        $this->assertSame('Shipped', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->dispatched_at);
        $this->assertNotNull($order->fresh()->expected_delivery_at);
        $this->assertSame(
            1,
            $order->fulfillmentEvents()->where('event_type', 'tracking_updated')->count(),
        );
        Mail::assertSent(ShipmentTracking::class, 1);
    }

    public function test_stale_and_undelivered_webhooks_do_not_regress_or_mark_delivered(): void
    {
        $order = $this->makeOrder(FulfillmentMethod::Shiprocket);
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'awb_code' => 'AWB-ORDERING',
            'shipment_status' => 'IN TRANSIT',
            'last_provider_event_at' => Carbon::parse('2026-08-09 12:00:00', 'Asia/Kolkata')->utc(),
        ]);
        $order->forceFill(['status' => 'Shipped'])->save();

        foreach ([
            ['current_status' => 'DELIVERED', 'current_timestamp' => '09 08 2026 11:00:00'],
            ['current_status' => 'UNDELIVERED', 'current_timestamp' => '09 08 2026 13:00:00'],
        ] as $payload) {
            $this->withHeader('x-api-key', 'webhook-secret')->postJson(
                '/api/fulfillment/provider-update',
                [
                    'awb' => 'AWB-ORDERING',
                    'shipment_status_id' => 99,
                    ...$payload,
                ],
            )->assertOk();
        }

        $this->assertSame('Shipped', $order->fresh()->status);
        $this->assertSame('UNDELIVERED', $shipment->fresh()->shipment_status);
    }

    public function test_manual_details_override_historical_shiprocket_data_for_customers(): void
    {
        $order = $this->makeOrder(FulfillmentMethod::Manual);
        $order->forceFill([
            'courier_partner' => 'Local Courier',
            'awb_number' => 'LOCAL-AWB',
            'tracking_number' => 'LOCAL-TRACK',
        ])->save();
        $order->shiprocketShipment()->create([
            'sync_status' => 'cancelled',
            'stage' => 'switched_to_manual',
            'courier_name' => 'Old Shiprocket Courier',
            'awb_code' => 'OLD-SR-AWB',
            'cancelled_at' => now(),
        ]);

        $details = app(OrderShipmentDetails::class)->forCustomer($order->fresh('shiprocketShipment'));

        $this->assertSame('Local Courier', $details['partner']);
        $this->assertSame('LOCAL-AWB', $details['awb_number']);
        $this->assertSame('LOCAL-TRACK', $details['tracking_number']);
    }

    private function makeOrder(FulfillmentMethod $method): Order
    {
        return Order::query()->create([
            'number' => 'VM-FUL-'.uniqid(),
            'full_name' => 'Fulfillment Buyer',
            'email' => 'buyer@example.com',
            'phone' => '9999999999',
            'address' => '1 Fulfillment Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'seller_state' => 'Gujarat',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 5,
            'total' => 105,
            'status' => 'Processing',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
            'fulfillment_method' => $method,
        ]);
    }
}
