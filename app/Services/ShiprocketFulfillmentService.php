<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Exceptions\ShiprocketException;
use App\Jobs\FulfillShiprocketOrder;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use App\Services\Inventory\InventoryService;
use Throwable;

class ShiprocketFulfillmentService
{
    private const AWB_PENDING_REDELAY_MINUTES = 3;

    private const AWB_PENDING_MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly ShiprocketService $shiprocket,
        private readonly ShiprocketParcel $parcels,
        private readonly InventoryService $inventory,
        private readonly FulfillmentAuditService $audit,
        private readonly ShiprocketTrackingUpdater $trackingUpdater,
        private readonly ShipmentEmailDispatcher $shipmentEmails,
    ) {}

    public function fulfill(Order $order): ?ShiprocketShipment
    {
        if (! $this->isShiprocketOwned($order)) {
            return $order->shiprocketShipment;
        }

        if (! $this->shiprocket->enabled()) {
            throw new ShiprocketException('Shiprocket integration is disabled.');
        }

        $order->loadMissing(['items', 'shiprocketShipment']);
        $this->assertReady($order);

        $shipment = ShiprocketShipment::query()->firstOrCreate(
            ['order_id' => $order->id],
            ['sync_status' => 'pending', 'stage' => 'queued']
        );

        if ($shipment->sync_status === 'completed' || $shipment->cancelled_at) {
            return $shipment;
        }

        $shipment->forceFill([
            'sync_status' => 'processing',
            'attempts' => $shipment->attempts + 1,
            'last_error' => null,
        ])->save();

        try {
            $pickup = $this->shiprocket->resolvePickupLocation();
            $parcel = $this->parcels->forOrder($order);
            $payload = $this->orderPayload($order, $pickup, $parcel);
            $fingerprint = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

            if (! $shipment->shiprocket_order_id || ! $shipment->shipment_id) {
                if (! $this->isShiprocketOwned($order->fresh())) {
                    return $shipment;
                }

                $recovered = $this->recoverRemoteOrder($order, $shipment, $fingerprint);
                if (! $recovered) {
                    $created = $this->shiprocket->createOrder($payload);
                    $shiprocketOrderId = (int) ($created['order_id'] ?? 0);
                    $shipmentId = (int) ($created['shipment_id'] ?? 0);

                    if ($shiprocketOrderId < 1 || $shipmentId < 1) {
                        throw new ShiprocketException('Shiprocket order creation returned incomplete identifiers.');
                    }

                    $shipment->forceFill([
                        'shiprocket_order_id' => $shiprocketOrderId,
                        'shipment_id' => $shipmentId,
                        'request_fingerprint' => $fingerprint,
                        'stage' => 'order_created',
                        'order_created_at' => now(),
                    ])->save();
                    $this->audit->record(
                        $order,
                        'remote_order_created',
                        'job',
                        "order:{$order->id}:shiprocket-order-created:{$shiprocketOrderId}",
                        [
                            'shipment' => $shipment,
                            'external_event_id' => (string) $shiprocketOrderId,
                        ],
                    );
                }
            } else {
                $shipment->forceFill([
                    'request_fingerprint' => $shipment->request_fingerprint ?: $fingerprint,
                ])->save();
            }

            if (! $shipment->awb_code) {
                if (! $this->isShiprocketOwned($order->fresh())) {
                    return $shipment;
                }

                $assigned = $this->assignAwbWithFallback($order, $shipment, $pickup, $parcel);
                if (! $assigned) {
                    return $this->markAwaitingAwb($order, $shipment);
                }
            }

            if (! $shipment->pickup_scheduled_at) {
                if (! $this->isShiprocketOwned($order->fresh())) {
                    return $shipment;
                }

                try {
                    $pickupResponse = $this->shiprocket->schedulePickup((int) $shipment->shipment_id);
                    $pickupStatus = (string) (
                        data_get($pickupResponse, 'response.pickup_status')
                        ?? data_get($pickupResponse, 'pickup_status')
                        ?? $pickupResponse['message']
                        ?? 'Scheduled'
                    );
                } catch (Throwable $pickupException) {
                    if (! $this->shiprocket->isAlreadyGeneratedPickupError($pickupException)) {
                        throw $pickupException;
                    }

                    $pickupStatus = $this->pickupStatusFromAlreadyGenerated($pickupException->getMessage());
                }

                $shipment->forceFill([
                    'pickup_status' => $pickupStatus,
                    'stage' => 'pickup_scheduled',
                    'pickup_scheduled_at' => now(),
                    'sync_status' => 'completed',
                    'last_error' => null,
                ])->save();
                $this->audit->record(
                    $order,
                    'pickup_scheduled',
                    'job',
                    "order:{$order->id}:pickup-scheduled",
                    [
                        'shipment' => $shipment,
                        'provider_status' => $pickupStatus,
                    ],
                );
            } else {
                $shipment->forceFill([
                    'sync_status' => 'completed',
                    'last_error' => null,
                ])->save();
            }

            return $shipment->fresh();
        } catch (Throwable $exception) {
            $shipment->forceFill([
                'sync_status' => 'failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    public function cancel(Order $order): ?ShiprocketShipment
    {
        $shipment = $order->shiprocketShipment;

        if (! $shipment || ! $shipment->shiprocket_order_id || $shipment->cancelled_at) {
            return $shipment;
        }

        try {
            $this->shiprocket->cancelOrder((int) $shipment->shiprocket_order_id);
            $shipment->forceFill([
                'sync_status' => 'cancelled',
                'stage' => 'cancelled',
                'shipment_status' => 'Cancelled',
                'cancelled_at' => now(),
                'last_error' => null,
            ])->save();

            app(OrderCancellationService::class)->finalizeAfterShiprocket($order->fresh(['items', 'shiprocketShipment']));

            return $shipment->fresh();
        } catch (Throwable $exception) {
            $shipment->forceFill([
                'sync_status' => 'cancel_failed',
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    public function syncTracking(Order $order): ?ShiprocketShipment
    {
        if (! $this->isShiprocketOwned($order)) {
            return $order->shiprocketShipment;
        }

        $shipment = $order->shiprocketShipment;

        if (! $shipment?->awb_code || $shipment->cancelled_at) {
            return $shipment;
        }

        try {
            $response = $this->shiprocket->trackByAwb($shipment->awb_code);
            $tracking = $this->trackingUpdater->normalizePollingResponse($response);
            $eventId = hash('sha256', json_encode($tracking, JSON_THROW_ON_ERROR));

            return $this->trackingUpdater->apply(
                $order,
                $shipment,
                $tracking,
                'scheduler',
                $eventId,
            );
        } catch (Throwable $exception) {
            $shipment->forceFill([
                'last_synced_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $pickup
     * @param  array{weight: float, length: float, breadth: float, height: float}  $parcel
     * @return array<string, mixed>
     */
    public function orderPayload(Order $order, array $pickup, array $parcel): array
    {
        $order->loadMissing('items');
        $taxRate = (float) config('gst.rate', 0.05);

        return [
            'order_id' => $order->number,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => (string) ($pickup['pickup_location'] ?? ''),
            'billing_customer_name' => $order->full_name,
            'billing_last_name' => '',
            'billing_address' => $order->address,
            'billing_address_2' => $order->district ?: '',
            'billing_city' => $order->city,
            'billing_pincode' => $order->postal_code,
            'billing_state' => $order->state,
            'billing_country' => 'India',
            'billing_email' => $order->email,
            'billing_phone' => $this->phone($order->phone),
            'shipping_is_billing' => true,
            'order_items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'sku' => $item->product_sku,
                'units' => (int) $item->quantity,
                'selling_price' => round((float) $item->unit_price * (1 + $taxRate), 2),
                'discount' => '',
                'tax' => round($taxRate * 100, 2),
                'hsn' => $item->hsn ?: config('invoice.default_hsn'),
            ])->values()->all(),
            'payment_method' => $order->payment_method === 'cod' ? 'COD' : 'Prepaid',
            'shipping_charges' => (float) $order->shipping,
            'giftwrap_charges' => 0,
            'transaction_charges' => (float) $order->cod_fee,
            'total_discount' => 0,
            'sub_total' => round((float) $order->subtotal + (float) $order->tax, 2),
            'length' => $parcel['length'],
            'breadth' => $parcel['breadth'],
            'height' => $parcel['height'],
            'weight' => $parcel['weight'],
        ];
    }

    /**
     * @param  array<string, mixed>  $pickup
     * @param  array{weight: float, length: float, breadth: float, height: float}  $parcel
     */
    private function assignAwbWithFallback(
        Order $order,
        ShiprocketShipment $shipment,
        array $pickup,
        array $parcel,
    ): bool {
        $courierId = $this->recommendedCourierId($order, $pickup, $parcel);
        $awbResponse = $this->shiprocket->assignAwb((int) $shipment->shipment_id, $courierId);
        $awb = $this->extractAwbCode($awbResponse);
        $assignedCourierId = data_get($awbResponse, 'response.data.courier_company_id', $courierId);
        $courierName = data_get($awbResponse, 'response.data.courier_name');

        if ($awb === '' || (int) data_get($awbResponse, 'awb_assign_status', 1) === 0) {
            $autoResponse = $this->shiprocket->assignAwb((int) $shipment->shipment_id, null);
            $autoAwb = $this->extractAwbCode($autoResponse);
            if ($autoAwb !== '' && (int) data_get($autoResponse, 'awb_assign_status', 1) !== 0) {
                $awb = $autoAwb;
                $awbResponse = $autoResponse;
                $assignedCourierId = data_get($autoResponse, 'response.data.courier_company_id');
                $courierName = data_get($autoResponse, 'response.data.courier_name');
            }
        }

        if ($awb === '') {
            try {
                $remote = $this->shiprocket->findOrderByChannelOrderId((string) $order->number);
            } catch (Throwable) {
                $remote = null;
            }

            if ($remote && filled($remote['awb_code'])) {
                $awb = (string) $remote['awb_code'];
                $assignedCourierId = $remote['courier_company_id'] ?: $assignedCourierId;
                $courierName = $remote['courier_name'] ?: $courierName;
            }
        }

        if ($awb === '') {
            return false;
        }

        $shipment->forceFill([
            'courier_company_id' => $assignedCourierId,
            'courier_name' => $courierName,
            'awb_code' => $awb,
            'stage' => 'awb_assigned',
            'awb_assigned_at' => now(),
            'last_error' => null,
        ])->save();
        $this->syncOrderCourierFields($order, $shipment);
        $this->shipmentEmails->dispatch($order->fresh(), $shipment);
        $this->audit->record(
            $order,
            'awb_assigned',
            'job',
            "order:{$order->id}:awb-assigned:{$awb}",
            [
                'shipment' => $shipment,
                'external_event_id' => $awb,
            ],
        );

        return true;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractAwbCode(array $response): string
    {
        $candidates = [
            data_get($response, 'response.data.awb_code'),
            data_get($response, 'response.data.awb'),
            data_get($response, 'awb_code'),
            data_get($response, 'awb'),
        ];

        foreach ($candidates as $candidate) {
            $awb = trim((string) $candidate);
            if ($awb !== '') {
                return $awb;
            }
        }

        return '';
    }

    private function markAwaitingAwb(Order $order, ShiprocketShipment $shipment): ShiprocketShipment
    {
        $shipment->forceFill([
            'sync_status' => 'awaiting_awb',
            'stage' => 'order_created',
            'last_error' => 'AWB assignment pending from Shiprocket.',
        ])->save();

        $this->audit->record(
            $order,
            'awb_assignment_pending',
            'job',
            "order:{$order->id}:awb-pending:{$shipment->attempts}",
            [
                'shipment' => $shipment,
                'reason' => 'Shiprocket order exists but AWB is not assigned yet; will retry the same shipment.',
            ],
        );

        if ((int) $shipment->attempts < self::AWB_PENDING_MAX_ATTEMPTS) {
            FulfillShiprocketOrder::dispatch($order->id)
                ->delay(now()->addMinutes(self::AWB_PENDING_REDELAY_MINUTES));
        }

        return $shipment->fresh();
    }

    private function recommendedCourierId(Order $order, array $pickup, array $parcel): int
    {
        $response = $this->shiprocket->serviceability([
            'pickup_postcode' => $pickup['pin_code'] ?? '',
            'delivery_postcode' => $order->postal_code,
            'weight' => $parcel['weight'],
            'cod' => $order->payment_method === 'cod' ? 1 : 0,
        ]);
        $recommended = (int) data_get($response, 'data.recommended_courier_company_id', 0);

        if ($recommended > 0) {
            return $recommended;
        }

        $couriers = data_get($response, 'data.available_courier_companies', []);
        if (! is_array($couriers) || $couriers === []) {
            throw new ShiprocketException('No Shiprocket courier is serviceable for this order.');
        }

        $courier = collect($couriers)
            ->sortBy(fn (array $item) => (float) ($item['freight_charge'] ?? PHP_FLOAT_MAX))
            ->first();
        $courierId = (int) ($courier['courier_company_id'] ?? 0);

        if ($courierId < 1) {
            throw new ShiprocketException('Shiprocket serviceability returned no usable courier.');
        }

        return $courierId;
    }

    private function syncOrderCourierFields(Order $order, ShiprocketShipment $shipment): void
    {
        $order->forceFill([
            'courier_partner' => $shipment->courier_name ?: 'Shiprocket',
            'awb_number' => $shipment->awb_code,
            'tracking_number' => $shipment->awb_code,
        ])->save();
    }

    /**
     * Reuse a prior remote create when local IDs are missing (fingerprint or channel order lookup).
     */
    private function recoverRemoteOrder(Order $order, ShiprocketShipment $shipment, string $fingerprint): bool
    {
        if (
            $shipment->request_fingerprint
            && hash_equals((string) $shipment->request_fingerprint, $fingerprint)
            && $shipment->shiprocket_order_id
            && $shipment->shipment_id
        ) {
            return true;
        }

        try {
            $remote = $this->shiprocket->findOrderByChannelOrderId((string) $order->number);
        } catch (Throwable) {
            $remote = null;
        }

        if (! $remote) {
            return false;
        }

        $updates = [
            'shiprocket_order_id' => $remote['order_id'],
            'shipment_id' => $remote['shipment_id'],
            'request_fingerprint' => $fingerprint,
            'stage' => $shipment->stage === 'queued' ? 'order_created' : $shipment->stage,
            'order_created_at' => $shipment->order_created_at ?: now(),
        ];

        if (filled($remote['awb_code']) && ! $shipment->awb_code) {
            $updates['awb_code'] = $remote['awb_code'];
            $updates['courier_name'] = $remote['courier_name'] ?: $shipment->courier_name;
            $updates['courier_company_id'] = $remote['courier_company_id'] ?: $shipment->courier_company_id;
            $updates['stage'] = 'awb_assigned';
            $updates['awb_assigned_at'] = $shipment->awb_assigned_at ?: now();
        }

        $shipment->forceFill($updates)->save();

        if (filled($shipment->awb_code)) {
            $this->syncOrderCourierFields($order, $shipment);
            $this->shipmentEmails->dispatch($order->fresh(), $shipment);
        }

        $this->audit->record(
            $order,
            'remote_order_recovered',
            'job',
            "order:{$order->id}:shiprocket-order-recovered:{$remote['order_id']}",
            [
                'shipment' => $shipment,
                'external_event_id' => (string) $remote['order_id'],
                'reason' => 'Recovered existing Shiprocket order by channel order id',
            ],
        );

        return true;
    }

    private function pickupStatusFromAlreadyGenerated(string $message): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return 'Already generated';
        }

        $withoutBody = preg_split('/\s*\|\s*/', $trimmed, 2)[0] ?? $trimmed;

        return mb_substr(trim($withoutBody) !== '' ? trim($withoutBody) : 'Already generated', 0, 255);
    }

    private function assertReady(Order $order): void
    {
        if ($order->status === 'Cancelled') {
            throw new ShiprocketException('Cancelled orders cannot be sent to Shiprocket.');
        }

        if ($order->payment_method === 'razorpay' && ! $order->isPaid()) {
            throw new ShiprocketException('Unpaid online orders cannot be sent to Shiprocket.');
        }

        if ($order->items->isEmpty()) {
            throw new ShiprocketException('The order has no items to fulfill.');
        }
    }

    private function isShiprocketOwned(Order $order): bool
    {
        return $order->fulfillment_method === FulfillmentMethod::Shiprocket;
    }

    private function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }
}
