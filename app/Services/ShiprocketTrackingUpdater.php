<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Enums\InventoryReservationState;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use App\Services\Inventory\InventoryService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

class ShiprocketTrackingUpdater
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FulfillmentAuditService $audit,
        private readonly ShipmentEmailDispatcher $shipmentEmails,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function normalizePollingResponse(array $response): array
    {
        $tracking = data_get($response, 'tracking_data', $response);

        return [
            'awb' => data_get($tracking, 'shipment_track.0.awb_code')
                ?? data_get($tracking, 'awb'),
            'courier_name' => data_get($tracking, 'shipment_track.0.courier_name')
                ?? data_get($tracking, 'courier_name'),
            'status' => data_get($tracking, 'shipment_track.0.current_status')
                ?? data_get($tracking, 'shipment_status')
                ?? data_get($tracking, 'current_status'),
            'status_id' => data_get($tracking, 'shipment_track.0.sr_status')
                ?? data_get($tracking, 'shipment_status_id')
                ?? data_get($tracking, 'current_status_id'),
            'tracking_url' => data_get($tracking, 'track_url')
                ?? data_get($tracking, 'tracking_url'),
            'etd' => data_get($tracking, 'shipment_track.0.edd')
                ?? data_get($tracking, 'etd'),
            'occurred_at' => data_get($tracking, 'current_timestamp'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeWebhookPayload(array $payload): array
    {
        return [
            'awb' => $payload['awb'] ?? null,
            'courier_name' => $payload['courier_name'] ?? null,
            'status' => $payload['current_status'] ?? $payload['shipment_status'] ?? null,
            'status_id' => $payload['shipment_status_id'] ?? $payload['current_status_id'] ?? null,
            'tracking_url' => $payload['track_url'] ?? $payload['tracking_url'] ?? null,
            'etd' => $payload['etd'] ?? null,
            'occurred_at' => $payload['current_timestamp'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $tracking
     */
    public function apply(
        Order $order,
        ShiprocketShipment $shipment,
        array $tracking,
        string $source,
        string $externalEventId,
    ): ShiprocketShipment {
        if ($order->fulfillment_method !== FulfillmentMethod::Shiprocket) {
            return $shipment;
        }

        $occurredAt = $this->parseProviderDate($tracking['occurred_at'] ?? null);
        if (
            $occurredAt
            && $shipment->last_provider_event_at
            && $occurredAt->lessThanOrEqualTo($shipment->last_provider_event_at)
        ) {
            $shipment->forceFill(['last_synced_at' => now()])->save();
            $this->audit->record(
                $order,
                'tracking_event_ignored',
                $source,
                "order:{$order->id}:tracking-ignored:{$externalEventId}",
                [
                    'shipment' => $shipment,
                    'external_event_id' => $externalEventId,
                    'provider_status' => $tracking['status'] ?? null,
                    'provider_status_id' => $tracking['status_id'] ?? null,
                    'reason' => 'Provider event was older than the latest applied event',
                    'occurred_at' => $occurredAt,
                ],
            );

            return $shipment;
        }

        return DB::transaction(function () use (
            $order,
            $shipment,
            $tracking,
            $source,
            $externalEventId,
            $occurredAt,
        ): ShiprocketShipment {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $lockedShipment = ShiprocketShipment::query()->lockForUpdate()->findOrFail($shipment->id);

            if ($lockedOrder->fulfillment_method !== FulfillmentMethod::Shiprocket) {
                return $lockedShipment;
            }

            $providerStatus = trim((string) ($tracking['status'] ?? ''));
            $localStatus = $this->localStatusFor($providerStatus);
            if ($localStatus && $this->statusRank($localStatus) < $this->statusRank($lockedOrder->status)) {
                $localStatus = null;
            }

            $lockedShipment->forceFill([
                'awb_code' => $tracking['awb'] ?: $lockedShipment->awb_code,
                'courier_name' => $tracking['courier_name'] ?: $lockedShipment->courier_name,
                'shipment_status' => $providerStatus !== '' ? $providerStatus : $lockedShipment->shipment_status,
                'shipment_status_id' => is_numeric($tracking['status_id'] ?? null)
                    ? (int) $tracking['status_id']
                    : $lockedShipment->shipment_status_id,
                'tracking_url' => $tracking['tracking_url'] ?: $lockedShipment->tracking_url,
                'last_synced_at' => now(),
                'last_provider_event_at' => $occurredAt ?: $lockedShipment->last_provider_event_at,
                'last_error' => null,
            ])->save();

            $updates = [
                'courier_partner' => $lockedShipment->courier_name ?: $lockedOrder->courier_partner,
                'awb_number' => $lockedShipment->awb_code ?: $lockedOrder->awb_number,
                'tracking_number' => $lockedShipment->awb_code ?: $lockedOrder->tracking_number,
            ];

            if ($localStatus && ! in_array($lockedOrder->status, ['Delivered', 'Cancelled'], true)) {
                $updates['status'] = $localStatus;
            }

            if (in_array($localStatus, ['Shipped', 'Delivered'], true)) {
                $this->consumeAtCourierHandoff($lockedOrder);
                $updates['dispatched_at'] = $lockedOrder->dispatched_at ?: ($occurredAt ?: now());
            }

            if ($localStatus === 'Delivered') {
                $updates['delivered_at'] = $lockedOrder->delivered_at ?: ($occurredAt ?: now());
            }

            $etd = $this->parseProviderDate($tracking['etd'] ?? null);
            if ($etd) {
                $updates['expected_delivery_at'] = $etd;
            }

            $previousStatus = $lockedOrder->status;
            $lockedOrder->forceFill($updates)->save();

            $this->audit->record(
                $lockedOrder,
                'tracking_updated',
                $source,
                "order:{$lockedOrder->id}:tracking:{$externalEventId}",
                [
                    'shipment' => $lockedShipment,
                    'previous_status' => $previousStatus,
                    'new_status' => $lockedOrder->status,
                    'provider_status' => $providerStatus ?: null,
                    'provider_status_id' => $tracking['status_id'] ?? null,
                    'external_event_id' => $externalEventId,
                    'occurred_at' => $occurredAt,
                ],
            );
            $this->shipmentEmails->dispatch($lockedOrder, $lockedShipment);

            return $lockedShipment->fresh();
        });
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

    private function localStatusFor(string $status): ?string
    {
        $normalized = strtolower(trim($status));

        if (
            $normalized === ''
            || str_contains($normalized, 'undelivered')
            || str_contains($normalized, 'rto')
            || str_contains($normalized, 'return')
            || str_contains($normalized, 'cancel')
            || str_contains($normalized, 'lost')
            || str_contains($normalized, 'damaged')
        ) {
            return null;
        }

        if ($normalized === 'delivered' || str_starts_with($normalized, 'delivered ')) {
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

    private function statusRank(string $status): int
    {
        return match ($status) {
            'Processing' => 1,
            'Packed' => 2,
            'Shipped' => 3,
            'Delivered' => 4,
            'Cancelled' => 5,
            default => 0,
        };
    }

    private function parseProviderDate(mixed $value): ?CarbonInterface
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d m Y H:i:s', 'Y-m-d H:i:s'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, 'Asia/Kolkata')->utc();
            } catch (Throwable) {
                // Try the next known provider format.
            }
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
