<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Enums\InventoryReservationState;
use App\Models\Order;
use App\Models\ShiprocketShipment;
use App\Models\ShiprocketTrackingEvent;
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
        $activities = data_get($tracking, 'shipment_track_activities', []);
        if (! is_array($activities)) {
            $activities = [];
        }

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
            'occurred_at' => data_get($tracking, 'current_timestamp')
                ?? data_get($tracking, 'shipment_track.0.updated_time')
                ?? data_get($activities, '0.date'),
            'activities' => $activities,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalizeWebhookPayload(array $payload): array
    {
        $activities = $payload['activities']
            ?? $payload['shipment_track_activities']
            ?? $payload['scans']
            ?? [];
        if (! is_array($activities)) {
            $activities = [];
        }

        return [
            'awb' => $payload['awb'] ?? null,
            'courier_name' => $payload['courier_name'] ?? null,
            'status' => $payload['current_status'] ?? $payload['shipment_status'] ?? null,
            'status_id' => $payload['shipment_status_id'] ?? $payload['current_status_id'] ?? null,
            'tracking_url' => $payload['track_url'] ?? $payload['tracking_url'] ?? null,
            'etd' => $payload['etd'] ?? null,
            'occurred_at' => $payload['current_timestamp'] ?? null,
            'activities' => $activities,
            'channel_order_id' => $payload['channel_order_id']
                ?? $payload['order_id']
                ?? $payload['sr_channel_order_id']
                ?? null,
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
        $isStale = $occurredAt
            && $shipment->last_provider_event_at
            && $occurredAt->lessThanOrEqualTo($shipment->last_provider_event_at);

        if ($isStale) {
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

                $this->persistTrackingHistory($lockedShipment, $tracking, $source);
                $this->reconcilePickupState(
                    $lockedOrder,
                    $lockedShipment,
                    trim((string) ($tracking['status'] ?? '')),
                    $occurredAt,
                    $source,
                );

                $lockedShipment->forceFill(['last_synced_at' => now()])->save();
                $this->audit->record(
                    $lockedOrder,
                    'tracking_event_ignored',
                    $source,
                    "order:{$lockedOrder->id}:tracking-ignored:{$externalEventId}",
                    [
                        'shipment' => $lockedShipment,
                        'external_event_id' => $externalEventId,
                        'provider_status' => $tracking['status'] ?? null,
                        'provider_status_id' => $tracking['status_id'] ?? null,
                        'reason' => 'Provider event was older than the latest applied event',
                        'occurred_at' => $occurredAt,
                    ],
                );

                return $lockedShipment->fresh();
            });
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
            ])->save();

            $this->persistTrackingHistory($lockedShipment, $tracking, $source);
            $this->reconcilePickupState(
                $lockedOrder,
                $lockedShipment,
                $providerStatus !== '' ? $providerStatus : (string) $lockedShipment->shipment_status,
                $occurredAt,
                $source,
            );

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

    /**
     * @param  array<string, mixed>  $tracking
     */
    private function persistTrackingHistory(
        ShiprocketShipment $shipment,
        array $tracking,
        string $source,
    ): void {
        $activities = $tracking['activities'] ?? [];
        if (! is_array($activities) || $activities === []) {
            $status = trim((string) ($tracking['status'] ?? ''));
            if ($status === '') {
                return;
            }

            $activities = [[
                'status' => $status,
                'sr-status' => $tracking['status_id'] ?? null,
                'location' => $tracking['location'] ?? null,
                'date' => $tracking['occurred_at'] ?? null,
            ]];
        }

        foreach ($activities as $activity) {
            if (! is_array($activity)) {
                if (! is_string($activity) || trim($activity) === '') {
                    continue;
                }

                $activity = ['status' => $activity];
            }

            $status = trim((string) (
                $activity['status']
                ?? $activity['activity']
                ?? $activity['current_status']
                ?? ''
            ));
            $location = trim((string) ($activity['location'] ?? $activity['location_code'] ?? ''));
            $statusId = $activity['sr-status']
                ?? $activity['sr_status']
                ?? $activity['status_id']
                ?? null;
            $occurredAt = $this->parseProviderDate(
                $activity['date']
                ?? $activity['event_date']
                ?? $activity['timestamp']
                ?? $activity['updated_time']
                ?? null
            );

            $hash = hash('sha256', implode('|', [
                $shipment->id,
                $status,
                (string) $statusId,
                $location,
                $occurredAt?->toIso8601String() ?? '',
                json_encode($activity, JSON_UNESCAPED_UNICODE) ?: '',
            ]));

            ShiprocketTrackingEvent::query()->firstOrCreate(
                [
                    'shiprocket_shipment_id' => $shipment->id,
                    'event_hash' => $hash,
                ],
                [
                    'status' => $status !== '' ? mb_substr($status, 0, 255) : null,
                    'status_id' => is_numeric($statusId) ? (int) $statusId : null,
                    'location' => $location !== '' ? mb_substr($location, 0, 255) : null,
                    'source' => mb_substr($source, 0, 32),
                    'raw' => $activity,
                    'occurred_at' => $occurredAt,
                ],
            );
        }
    }

    private function reconcilePickupState(
        Order $order,
        ShiprocketShipment $shipment,
        string $providerStatus,
        ?CarbonInterface $occurredAt,
        string $source,
    ): void {
        if ($shipment->cancelled_at || ! filled($shipment->awb_code)) {
            return;
        }

        $statusForPickup = $providerStatus !== ''
            ? $providerStatus
            : (string) $shipment->shipment_status;

        if (! $this->indicatesPickupScheduled($statusForPickup)) {
            return;
        }

        $updates = [];

        if (! $shipment->pickup_status) {
            $updates['pickup_status'] = mb_substr($statusForPickup, 0, 255);
        }

        if (! $shipment->pickup_scheduled_at) {
            $updates['pickup_scheduled_at'] = $occurredAt ?: now();
        }

        $stage = (string) $shipment->stage;
        if (
            $stage !== 'cancelled'
            && ! in_array($stage, ['pickup_scheduled', 'shipped', 'delivered'], true)
        ) {
            $updates['stage'] = 'pickup_scheduled';
        }

        if (in_array((string) $shipment->sync_status, ['failed', 'processing', 'pending'], true)) {
            $updates['sync_status'] = 'completed';
            $updates['last_error'] = null;
        }

        if ($updates === []) {
            return;
        }

        $shipment->forceFill($updates)->save();

        $this->audit->record(
            $order,
            'pickup_reconciled',
            $source,
            "order:{$order->id}:pickup-reconciled:".($shipment->awb_code ?: $shipment->id),
            [
                'shipment' => $shipment,
                'provider_status' => $statusForPickup,
                'reason' => 'Provider tracking indicated pickup already generated/scheduled',
                'occurred_at' => $occurredAt,
            ],
        );
    }

    private function indicatesPickupScheduled(string $status): bool
    {
        $normalized = strtolower(trim($status));
        if ($normalized === '') {
            return false;
        }

        $needles = [
            'pickup generated',
            'pickup scheduled',
            'out for pickup',
            'pickup queued',
            'manifested',
            'manifest generated',
            'ready to ship',
            'picked up',
            'in transit',
            'shipped',
            'out for delivery',
            'delivered',
        ];

        foreach ($needles as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
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
