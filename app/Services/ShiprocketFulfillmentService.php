<?php

namespace App\Services;

use App\Enums\InventoryReservationState;
use App\Exceptions\ShiprocketException;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use App\Services\Inventory\InventoryService;
use Throwable;

class ShiprocketFulfillmentService
{
    public function __construct(
        private readonly ShiprocketService $shiprocket,
        private readonly ShiprocketParcel $parcels,
        private readonly InventoryService $inventory,
    ) {}

    public function fulfill(Order $order): ShiprocketShipment
    {
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
            }

            if (! $shipment->awb_code) {
                $courierId = $this->recommendedCourierId($order, $pickup, $parcel);
                $awbResponse = $this->shiprocket->assignAwb((int) $shipment->shipment_id, $courierId);
                $awb = (string) data_get($awbResponse, 'response.data.awb_code', '');

                if ($awb === '') {
                    throw new ShiprocketException(
                        (string) ($awbResponse['message'] ?? 'Shiprocket did not assign an AWB.')
                    );
                }

                $shipment->forceFill([
                    'courier_company_id' => data_get($awbResponse, 'response.data.courier_company_id', $courierId),
                    'courier_name' => data_get($awbResponse, 'response.data.courier_name'),
                    'awb_code' => $awb,
                    'stage' => 'awb_assigned',
                    'awb_assigned_at' => now(),
                ])->save();
                $this->syncOrderCourierFields($order, $shipment);
            }

            if (! $shipment->pickup_scheduled_at) {
                $pickupResponse = $this->shiprocket->schedulePickup((int) $shipment->shipment_id);
                $pickupStatus = (string) (
                    data_get($pickupResponse, 'response.pickup_status')
                    ?? data_get($pickupResponse, 'pickup_status')
                    ?? $pickupResponse['message']
                    ?? 'Scheduled'
                );

                $shipment->forceFill([
                    'pickup_status' => $pickupStatus,
                    'stage' => 'pickup_scheduled',
                    'pickup_scheduled_at' => now(),
                    'sync_status' => 'completed',
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

            $order->loadMissing('items.inventoryReservation');
            foreach ($order->items as $item) {
                if (in_array($item->inventoryReservation?->state, [
                    InventoryReservationState::Reserved,
                    InventoryReservationState::Committed,
                ], true)) {
                    $this->inventory->release(
                        $item,
                        'Shiprocket cancellation confirmed',
                        "order:{$order->id}:item:{$item->id}:shiprocket-cancel",
                        correlationId: 'order:'.$order->id,
                    );
                }
            }
            $order->forceFill([
                'status' => 'Cancelled',
                'cancelled_at' => now(),
                'cancel_requested_at' => $order->cancel_requested_at ?? now(),
                'cancellation_reason' => $order->cancellation_reason ?? 'Cancelled in Shiprocket',
            ])->save();

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
        $shipment = $order->shiprocketShipment;

        if (! $shipment?->awb_code || $shipment->cancelled_at) {
            return $shipment;
        }

        try {
            $response = $this->shiprocket->trackByAwb($shipment->awb_code);
            $tracking = data_get($response, 'tracking_data', $response);
            $status = (string) (
                data_get($tracking, 'shipment_track.0.current_status')
                ?? data_get($tracking, 'shipment_status')
                ?? data_get($tracking, 'current_status')
                ?? ''
            );
            $statusId = data_get($tracking, 'shipment_track.0.sr_status')
                ?? data_get($tracking, 'shipment_status_id')
                ?? data_get($tracking, 'current_status_id');
            $trackingUrl = data_get($tracking, 'track_url')
                ?? data_get($tracking, 'tracking_url');
            $etd = data_get($tracking, 'shipment_track.0.edd')
                ?? data_get($tracking, 'etd');

            $shipment->forceFill([
                'shipment_status' => $status !== '' ? $status : $shipment->shipment_status,
                'shipment_status_id' => is_numeric($statusId) ? (int) $statusId : $shipment->shipment_status_id,
                'tracking_url' => $trackingUrl ?: $shipment->tracking_url,
                'last_synced_at' => now(),
                'last_error' => null,
            ])->save();

            $updates = [
                'tracking_number' => $shipment->awb_code,
            ];
            $localStatus = $this->localStatusFor($status);
            if ($localStatus === 'Shipped') {
                $this->consumeAtCourierHandoff($order);
            }
            if ($localStatus && ! in_array($order->status, ['Delivered', 'Cancelled'], true)) {
                $updates['status'] = $localStatus;
            }
            if ($localStatus === 'Shipped' && ! $order->dispatched_at) {
                $updates['dispatched_at'] = now();
            }
            if ($etd) {
                $updates['expected_delivery_at'] = $etd;
            }
            $order->forceFill($updates)->save();

            return $shipment->fresh();
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

    private function consumeAtCourierHandoff(Order $order): void
    {
        $order->loadMissing('items.inventoryReservation');

        foreach ($order->items as $item) {
            if ($item->inventoryReservation?->state !== InventoryReservationState::Committed) {
                continue;
            }

            $this->inventory->consume(
                $item,
                "order:{$order->id}:item:{$item->id}:courier-handoff",
                correlationId: 'order:'.$order->id,
                reason: 'Shiprocket courier handoff confirmed',
            );
        }
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

    private function phone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function localStatusFor(string $status): ?string
    {
        $normalized = strtolower($status);

        if (str_contains($normalized, 'delivered')) {
            return 'Delivered';
        }

        if (
            str_contains($normalized, 'shipped')
            || str_contains($normalized, 'transit')
            || str_contains($normalized, 'picked up')
            || str_contains($normalized, 'out for delivery')
        ) {
            return 'Shipped';
        }

        if (
            str_contains($normalized, 'ready to ship')
            || str_contains($normalized, 'awb')
            || str_contains($normalized, 'pickup')
            || str_contains($normalized, 'manifest')
        ) {
            return 'Packed';
        }

        return null;
    }
}
