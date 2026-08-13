<?php

namespace Tests\Feature;

use App\Exceptions\ShiprocketException;
use App\Jobs\CancelShiprocketOrder;
use App\Jobs\FulfillShiprocketOrder;
use App\Mail\ShipmentTracking;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShiprocketFulfillmentService;
use App\Services\ShiprocketParcel;
use App\Services\ShiprocketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShiprocketFulfillmentTest extends TestCase
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
            'services.shiprocket.password' => 'not-a-real-secret',
            'services.shiprocket.pickup_location' => null,
            'services.shiprocket.fallback_weight_kg' => 0.5,
            'services.shiprocket.fallback_length_cm' => 20,
            'services.shiprocket.fallback_breadth_cm' => 15,
            'services.shiprocket.fallback_height_cm' => 10,
        ]);
    }

    public function test_client_caches_authentication_and_resolves_primary_pickup(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
        ]);

        $service = app(ShiprocketService::class);
        $this->assertSame('Primary', $service->resolvePickupLocation()['pickup_location']);
        $this->assertSame('Primary', $service->resolvePickupLocation()['pickup_location']);

        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request->url() ===
            'https://apiv2.shiprocket.in/v1/external/auth/login');
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/auth/login')
        ));
    }

    public function test_fulfillment_creates_order_assigns_awb_and_schedules_pickup_once(): void
    {
        Mail::fake();
        $order = $this->makeOrder();
        $this->fakeSuccessfulWorkflow();

        $service = app(ShiprocketFulfillmentService::class);
        $shipment = $service->fulfill($order);

        $this->assertSame('completed', $shipment->sync_status);
        $this->assertSame('pickup_scheduled', $shipment->stage);
        $this->assertSame(5001, $shipment->shiprocket_order_id);
        $this->assertSame(6001, $shipment->shipment_id);
        $this->assertSame('AWB123', $shipment->awb_code);
        $this->assertSame('Delhivery Surface', $order->fresh()->courier_partner);
        $this->assertSame('AWB123', $order->fresh()->tracking_number);
        $this->assertNotNull($order->fresh()->shipping_notification_emailed_at);
        Mail::assertSent(ShipmentTracking::class, 1);

        $service->fulfill($order->fresh(['items', 'shiprocketShipment']));
        Mail::assertSent(ShipmentTracking::class, 1);

        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/assign/awb')
        ));
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/generate/pickup')
        ));

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/orders/create/adhoc')
                && $request['order_id'] === 'VM-SR-1'
                && $request['payment_method'] === 'COD'
                && $request['pickup_location'] === 'Primary'
                && $request['billing_address_2'] === 'Ahmedabad'
                && $request['weight'] === 1.0
                && $request['height'] === 20.0
                && $request['sub_total'] === 210.0
                && $request['order_items'][0]['selling_price'] === 105.0
                && $request['order_items'][0]['tax'] === 5.0;
        });
    }

    public function test_partial_failure_resumes_without_duplicate_order_or_awb(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/orders/create/adhoc' => Http::response(['order_id' => 5001, 'shipment_id' => 6001]),
            '*/courier/assign/awb' => Http::response([
                'response' => ['data' => [
                    'awb_code' => 'AWB123',
                    'courier_company_id' => 42,
                    'courier_name' => 'Delhivery Surface',
                ]],
            ]),
            '*/courier/generate/pickup' => Http::sequence()
                ->push(['message' => 'Pickup unavailable'], 503)
                ->push(['pickup_status' => 'Scheduled'], 200),
            '*/orders*' => Http::response(['data' => []]),
        ]);

        $service = app(ShiprocketFulfillmentService::class);

        try {
            $service->fulfill($order);
            $this->fail('Expected the first pickup request to fail.');
        } catch (ShiprocketException) {
            $this->assertDatabaseHas('shiprocket_shipments', [
                'order_id' => $order->id,
                'stage' => 'awb_assigned',
                'sync_status' => 'failed',
            ]);
        }

        $shipment = $service->fulfill($order->fresh(['items', 'shiprocketShipment']));
        $this->assertSame('completed', $shipment->sync_status);
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/assign/awb')
        ));
        $this->assertCount(2, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/generate/pickup')
        ));
    }

    public function test_parcel_aggregates_measurements_and_uses_fallbacks(): void
    {
        $order = $this->makeOrder();
        $order->items()->create([
            'product_name' => 'Fallback item',
            'product_sku' => 'FALLBACK-1',
            'product_slug' => 'fallback-item',
            'unit_price' => 50,
            'quantity' => 1,
            'line_total' => 50,
        ]);

        $parcel = app(ShiprocketParcel::class)->forOrder($order->fresh('items'));

        $this->assertSame(1.5, $parcel['weight']);
        $this->assertSame(20.0, $parcel['length']);
        $this->assertSame(15.0, $parcel['breadth']);
        $this->assertSame(30.0, $parcel['height']);
    }

    public function test_cod_checkout_queues_fulfillment_and_snapshots_package_data(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $product = $this->makeProduct();
        Sanctum::actingAs($user);

        $this->postJson('/api/cart', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $response = $this->postJson('/api/orders', [
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'district' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'payment_method' => 'cod',
        ])->assertCreated();

        $orderId = $response->json('data.id');
        $response->assertJsonPath('data.fulfillment_method', 'shiprocket');
        Queue::assertPushed(
            FulfillShiprocketOrder::class,
            fn (FulfillShiprocketOrder $job) => $job->orderId === $orderId
        );
        $this->assertDatabaseHas('order_items', [
            'order_id' => $orderId,
            'weight_kg' => 0.5,
            'length_cm' => 20,
            'breadth_cm' => 15,
            'height_cm' => 10,
        ]);
    }

    public function test_unpaid_razorpay_order_is_not_queued_but_verified_payment_is(): void
    {
        Queue::fake();
        $product = $this->makeProduct();
        $order = Order::query()->create([
            'number' => 'VM-RZ-SR-1',
            'full_name' => 'Paid Buyer',
            'email' => 'paid@example.com',
            'phone' => '9999999999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 100,
            'shipping' => 0,
            'tax' => 5,
            'total' => 105,
            'status' => 'AwaitingPayment',
            'fulfillment_method' => 'shiprocket',
            'payment_status' => 'pending',
            'payment_method' => 'razorpay',
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_slug' => $product->slug,
            'unit_price' => 100,
            'quantity' => 1,
            'weight_kg' => 0.5,
            'length_cm' => 20,
            'breadth_cm' => 15,
            'height_cm' => 10,
            'line_total' => 100,
        ]);

        Queue::assertNotPushed(FulfillShiprocketOrder::class);

        app(OrderService::class)->markPaidFromWebhook($order, 'pay_shiprocket_test');

        Queue::assertPushed(
            FulfillShiprocketOrder::class,
            fn (FulfillShiprocketOrder $job) => $job->orderId === $order->id
        );
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_admin_cancellation_queues_remote_cancellation(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $order = $this->makeOrder();
        $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => 'AWB123',
        ]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/orders/{$order->id}", [
            'status' => 'Cancelled',
        ])->assertOk();

        Queue::assertPushed(
            CancelShiprocketOrder::class,
            fn (CancelShiprocketOrder $job) => $job->orderId === $order->id
        );
    }

    public function test_pickup_already_generated_is_treated_as_success(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/orders/create/adhoc' => Http::response(['order_id' => 5001, 'shipment_id' => 6001]),
            '*/courier/assign/awb' => Http::response([
                'response' => ['data' => [
                    'awb_code' => 'AWB123',
                    'courier_company_id' => 42,
                    'courier_name' => 'Delhivery Surface',
                ]],
            ]),
            '*/courier/generate/pickup' => Http::response([
                'message' => 'Pickup already generated against this shipment.',
            ], 400),
            '*/orders*' => Http::response(['data' => []]),
        ]);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order);

        $this->assertSame('completed', $shipment->sync_status);
        $this->assertSame('pickup_scheduled', $shipment->stage);
        $this->assertNotNull($shipment->pickup_scheduled_at);
        $this->assertStringContainsStringIgnoringCase('already generated', (string) $shipment->pickup_status);
        $this->assertNull($shipment->last_error);
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/assign/awb')
        ));
    }

    public function test_tracking_reconciles_failed_awb_assigned_when_pickup_generated(): void
    {
        $order = $this->makeOrder();
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'failed',
            'stage' => 'awb_assigned',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => 'AWB123',
            'awb_assigned_at' => now()->subHour(),
            'last_error' => 'Pickup timeout',
        ]);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/courier/track/awb/AWB123' => Http::response([
                'tracking_data' => [
                    'shipment_track' => [[
                        'awb_code' => 'AWB123',
                        'current_status' => 'Pickup Generated',
                        'sr_status' => 7,
                        'courier_name' => 'Delhivery Surface',
                    ]],
                    'shipment_track_activities' => [
                        [
                            'date' => now()->subMinutes(10)->format('Y-m-d H:i:s'),
                            'status' => 'AWB Assigned',
                            'location' => 'Origin',
                        ],
                        [
                            'date' => now()->format('Y-m-d H:i:s'),
                            'status' => 'Pickup Generated',
                            'location' => 'Hub',
                        ],
                    ],
                    'track_url' => 'https://example.test/track/AWB123',
                    'current_timestamp' => now()->format('Y-m-d H:i:s'),
                ],
            ]),
        ]);

        $updated = app(ShiprocketFulfillmentService::class)
            ->syncTracking($order->fresh('shiprocketShipment'));

        $this->assertSame('completed', $updated->sync_status);
        $this->assertSame('pickup_scheduled', $updated->stage);
        $this->assertSame('Pickup Generated', $updated->shipment_status);
        $this->assertNotNull($updated->pickup_status);
        $this->assertNotNull($updated->pickup_scheduled_at);
        $this->assertNull($updated->last_error);
        $this->assertDatabaseCount('shiprocket_tracking_events', 2);
        $this->assertDatabaseHas('shiprocket_tracking_events', [
            'shiprocket_shipment_id' => $shipment->id,
            'status' => 'Pickup Generated',
        ]);
    }

    public function test_remote_channel_order_lookup_prevents_duplicate_create(): void
    {
        $order = $this->makeOrder();
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/orders*' => Http::response([
                'data' => [[
                    'id' => 7777,
                    'channel_order_id' => 'VM-SR-1',
                    'shipments' => [[
                        'id' => 8888,
                        'awb' => 'AWB-EXISTING',
                        'courier_name' => 'Existing Courier',
                        'courier_company_id' => 42,
                    ]],
                ]],
            ]),
            '*/courier/generate/pickup' => Http::response(['pickup_status' => 'Scheduled']),
        ]);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order);

        $this->assertSame(7777, $shipment->shiprocket_order_id);
        $this->assertSame(8888, $shipment->shipment_id);
        $this->assertSame('AWB-EXISTING', $shipment->awb_code);
        $this->assertSame('completed', $shipment->sync_status);
        $this->assertCount(0, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
        $this->assertCount(0, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/assign/awb')
        ));
    }

    public function test_webhook_matches_channel_order_number_and_heals_pickup(): void
    {
        config(['services.shiprocket.webhook_token' => 'webhook-secret']);
        $order = $this->makeOrder();
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'failed',
            'stage' => 'awb_assigned',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => null,
            'last_error' => 'Pickup failed',
        ]);

        $this->postJson('/api/fulfillment/provider-update', [
            'channel_order_id' => 'VM-SR-1',
            'awb' => 'AWB-WH-1',
            'current_status' => 'Pickup Generated',
            'shipment_status_id' => 7,
            'current_timestamp' => now()->format('Y-m-d H:i:s'),
            'courier_name' => 'Delhivery Surface',
            'track_url' => 'https://example.test/track/AWB-WH-1',
        ], [
            'x-api-key' => 'webhook-secret',
        ])->assertOk();

        $shipment->refresh();
        $this->assertSame('AWB-WH-1', $shipment->awb_code);
        $this->assertSame('Pickup Generated', $shipment->shipment_status);
        $this->assertSame('completed', $shipment->sync_status);
        $this->assertSame('pickup_scheduled', $shipment->stage);
        $this->assertNotNull($shipment->pickup_scheduled_at);
    }

    public function test_cancellation_and_tracking_sync_update_local_state(): void
    {
        $order = $this->makeOrder();
        $shipment = $order->shiprocketShipment()->create([
            'sync_status' => 'completed',
            'stage' => 'pickup_scheduled',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => 'AWB123',
            'pickup_scheduled_at' => now()->subHour(),
            'pickup_status' => 'Scheduled',
        ]);
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/courier/track/awb/AWB123' => Http::response([
                'tracking_data' => [
                    'shipment_track' => [[
                        'current_status' => 'IN TRANSIT',
                        'sr_status' => 18,
                        'edd' => now()->addDays(2)->toDateString(),
                    ]],
                    'track_url' => 'https://example.test/track/AWB123',
                ],
            ]),
            '*/orders/cancel' => Http::response(['status' => 1]),
        ]);

        $service = app(ShiprocketFulfillmentService::class);
        $service->syncTracking($order->fresh('shiprocketShipment'));

        $this->assertSame('Shipped', $order->fresh()->status);
        $this->assertSame('IN TRANSIT', $shipment->fresh()->shipment_status);
        $this->assertNotNull($shipment->fresh()->last_synced_at);

        $service->cancel($order->fresh('shiprocketShipment'));
        $this->assertSame('cancelled', $shipment->fresh()->sync_status);
        $this->assertNotNull($shipment->fresh()->cancelled_at);
    }

    private function fakeSuccessfulWorkflow(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/orders/create/adhoc' => Http::response(['order_id' => 5001, 'shipment_id' => 6001]),
            '*/courier/assign/awb' => Http::response([
                'response' => ['data' => [
                    'awb_code' => 'AWB123',
                    'courier_company_id' => 42,
                    'courier_name' => 'Delhivery Surface',
                ]],
            ]),
            '*/courier/generate/pickup' => Http::response(['pickup_status' => 'Scheduled']),
            '*/orders*' => Http::response(['data' => []]),
        ]);
    }

    public function test_empty_awb_sets_awaiting_awb_and_redelays_without_duplicate_create(): void
    {
        Queue::fake();
        $order = $this->makeOrder();
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/orders/create/adhoc' => Http::response(['order_id' => 5001, 'shipment_id' => 6001]),
            '*/courier/assign/awb' => Http::response([
                'awb_assign_status' => 0,
                'response' => ['data' => ['awb_code' => null]],
            ]),
            '*/orders*' => Http::response(['data' => []]),
        ]);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order);

        $this->assertSame('awaiting_awb', $shipment->sync_status);
        $this->assertSame('order_created', $shipment->stage);
        $this->assertSame(5001, $shipment->shiprocket_order_id);
        $this->assertSame(6001, $shipment->shipment_id);
        $this->assertNull($shipment->awb_code);
        $this->assertStringContainsStringIgnoringCase('pending', (string) $shipment->last_error);
        Queue::assertPushed(FulfillShiprocketOrder::class);

        $this->assertCount(1, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
        $this->assertGreaterThanOrEqual(2, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/courier/assign/awb')
        )->count());
    }

    public function test_awaiting_awb_retry_assigns_awb_and_schedules_pickup(): void
    {
        Mail::fake();
        $order = $this->makeOrder();
        $order->shiprocketShipment()->create([
            'sync_status' => 'awaiting_awb',
            'stage' => 'order_created',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'attempts' => 1,
            'last_error' => 'AWB assignment pending from Shiprocket.',
            'order_created_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/serviceability/*' => Http::response([
                'data' => ['recommended_courier_company_id' => 42],
            ]),
            '*/courier/assign/awb' => Http::sequence()
                ->push([
                    'awb_assign_status' => 0,
                    'response' => ['data' => ['awb_code' => null]],
                ])
                ->push([
                    'awb_assign_status' => 1,
                    'response' => ['data' => [
                        'awb_code' => 'AWB999',
                        'courier_company_id' => 42,
                        'courier_name' => 'Delhivery Surface',
                    ]],
                ]),
            '*/courier/generate/pickup' => Http::response(['pickup_status' => 'Scheduled']),
            '*/orders*' => Http::response(['data' => []]),
        ]);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order->fresh(['items', 'shiprocketShipment']));

        $this->assertSame('completed', $shipment->sync_status);
        $this->assertSame('AWB999', $shipment->awb_code);
        $this->assertSame('pickup_scheduled', $shipment->stage);
        $this->assertCount(0, Http::recorded(
            fn (Request $request) => str_ends_with($request->url(), '/orders/create/adhoc')
        ));
    }

    public function test_pickup_already_in_queue_is_treated_as_success(): void
    {
        $order = $this->makeOrder();
        $order->shiprocketShipment()->create([
            'sync_status' => 'failed',
            'stage' => 'awb_assigned',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'awb_code' => 'AWB123',
            'courier_name' => 'Delhivery Surface',
            'awb_assigned_at' => now()->subHour(),
        ]);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response($this->pickupResponse()),
            '*/courier/generate/pickup' => Http::response([
                'message' => 'Already in Pickup Queue',
            ], 400),
        ]);

        $shipment = app(ShiprocketFulfillmentService::class)->fulfill($order->fresh(['items', 'shiprocketShipment']));

        $this->assertSame('completed', $shipment->sync_status);
        $this->assertSame('pickup_scheduled', $shipment->stage);
        $this->assertNotNull($shipment->pickup_scheduled_at);
        $this->assertStringContainsStringIgnoringCase('pickup queue', (string) $shipment->pickup_status);
    }

    public function test_pickup_resolve_prefers_invoice_postal_code_match(): void
    {
        config(['invoice.postal_code' => '360024']);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/settings/company/pickup' => Http::response([
                'data' => [
                    'shipping_address' => [
                        [
                            'pickup_location' => 'Other Warehouse',
                            'pin_code' => '110001',
                            'city' => 'Delhi',
                            'state' => 'Delhi',
                            'status' => 2,
                            'is_primary_location' => 1,
                        ],
                        [
                            'pickup_location' => 'Shapar Rajkot',
                            'pin_code' => '360024',
                            'city' => 'Rajkot',
                            'state' => 'Gujarat',
                            'status' => 2,
                            'is_primary_location' => 0,
                        ],
                    ],
                ],
            ]),
        ]);

        $location = app(ShiprocketService::class)->resolvePickupLocation();
        $this->assertSame('Shapar Rajkot', $location['pickup_location']);
        $this->assertSame('360024', (string) $location['pin_code']);
    }

    public function test_manual_switch_preserves_shiprocket_ids_without_cancel(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $order = $this->makeOrder();
        $order->shiprocketShipment()->create([
            'sync_status' => 'awaiting_awb',
            'stage' => 'order_created',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'order_created_at' => now(),
        ]);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'test-token']),
            '*/orders/cancel' => Http::response(['message' => 'cancelled']),
        ]);

        $response = $this->postJson("/api/admin/orders/{$order->id}/fulfillment/manual", [
            'reason' => 'Ops handling locally',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fulfillment_method', 'manual')
            ->assertJsonPath('data.can_restore_to_shiprocket', true)
            ->assertJsonPath('data.shiprocket.shiprocket_order_id', 5001)
            ->assertJsonPath('data.shiprocket.shipment_id', 6001)
            ->assertJsonPath('data.shiprocket.sync_status', 'switched_to_manual');

        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/orders/cancel'));
    }

    public function test_restore_to_shiprocket_queues_fulfillment(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $order = $this->makeOrder();
        $order->forceFill(['fulfillment_method' => 'manual'])->save();
        $order->shiprocketShipment()->create([
            'sync_status' => 'switched_to_manual',
            'stage' => 'switched_to_manual',
            'shiprocket_order_id' => 5001,
            'shipment_id' => 6001,
            'order_created_at' => now(),
        ]);

        $response = $this->postJson("/api/admin/orders/{$order->id}/fulfillment/shiprocket", [
            'reason' => 'Resume Shiprocket',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fulfillment_method', 'shiprocket')
            ->assertJsonPath('data.shiprocket.sync_status', 'pending')
            ->assertJsonPath('data.can_restore_to_shiprocket', false);

        Queue::assertPushed(FulfillShiprocketOrder::class, fn (FulfillShiprocketOrder $job) => $job->orderId === $order->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function pickupResponse(): array
    {
        return [
            'data' => [
                'shipping_address' => [[
                    'pickup_location' => 'Primary',
                    'pin_code' => '360024',
                    'city' => 'Rajkot',
                    'state' => 'Gujarat',
                    'status' => 2,
                    'is_primary_location' => 1,
                ]],
            ],
        ];
    }

    private function makeOrder(): Order
    {
        $order = Order::query()->create([
            'number' => 'VM-SR-1',
            'full_name' => 'Test Buyer',
            'email' => 'buyer@example.com',
            'phone' => '+91 99999 99999',
            'address' => '12 Test Street',
            'city' => 'Ahmedabad',
            'district' => 'Ahmedabad',
            'state' => 'Gujarat',
            'postal_code' => '380001',
            'subtotal' => 200,
            'shipping' => 0,
            'tax' => 10,
            'total' => 210,
            'status' => 'Processing',
            'fulfillment_method' => 'shiprocket',
            'payment_status' => 'pending',
            'payment_method' => 'cod',
        ]);
        $order->items()->create([
            'product_name' => 'Measured item',
            'product_sku' => 'MEASURED-1',
            'product_slug' => 'measured-item',
            'unit_price' => 100,
            'quantity' => 2,
            'weight_kg' => 0.5,
            'length_cm' => 12,
            'breadth_cm' => 8,
            'height_cm' => 10,
            'line_total' => 200,
        ]);

        return $order->fresh('items');
    }

    private function makeProduct(): Product
    {
        $category = Category::query()->create([
            'name' => 'Toys',
            'slug' => 'toys',
            'description' => 'Toys',
            'image' => '/images/toys.jpg',
            'featured' => true,
        ]);

        return Product::query()->create([
            'external_id' => 'shiprocket-product',
            'slug' => 'shiprocket-product',
            'name' => 'Shiprocket Product',
            'sku' => 'SR-PRODUCT',
            'category_id' => $category->id,
            'price' => 100,
            'rating' => 4.5,
            'reviews' => 0,
            'image' => '/images/product.jpg',
            'tags' => [],
            'description' => 'Test product',
            'details' => [],
            'stock' => 10,
            'is_active' => true,
            'gallery' => [],
        ]);
    }
}
