<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ShipmentWebhookEvent;
use App\Models\ShiprocketShipment;
use App\Services\ApplicationErrorRecorder;
use App\Services\OrderFulfillmentLock;
use App\Services\ShiprocketTrackingUpdater;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessShiprocketWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public readonly int $eventId)
    {
        $this->afterCommit();
    }

    public function handle(
        ShiprocketTrackingUpdater $updater,
        OrderFulfillmentLock $lock,
    ): void {
        $event = ShipmentWebhookEvent::query()->findOrFail($this->eventId);
        if (in_array($event->status, ['processed', 'rejected'], true)) {
            return;
        }

        $shipment = $this->findShipment($event);
        if (! $shipment) {
            $event->forceFill([
                'status' => 'rejected',
                'last_error' => 'No local Shiprocket shipment matched this webhook.',
                'processed_at' => now(),
            ])->save();

            return;
        }

        try {
            $lock->run($shipment->order_id, function () use ($event, $shipment, $updater): void {
                $order = Order::query()->with(['items.inventoryReservation', 'shiprocketShipment'])
                    ->findOrFail($shipment->order_id);
                $event->forceFill([
                    'order_id' => $order->id,
                    'shiprocket_shipment_id' => $shipment->id,
                    'status' => 'processing',
                    'attempts' => $event->attempts + 1,
                    'last_error' => null,
                ])->save();

                $updatedShipment = $updater->apply(
                    $order,
                    $shipment,
                    $updater->normalizeWebhookPayload($event->payload),
                    'webhook',
                    $event->external_event_key,
                );

                $event->forceFill([
                    'status' => 'processed',
                    'provider_occurred_at' => $updatedShipment->last_provider_event_at,
                    'processed_at' => now(),
                    'last_error' => null,
                ])->save();
            });
        } catch (Throwable $exception) {
            $event->forceFill([
                'status' => 'failed',
                'attempts' => max(1, (int) $event->attempts),
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception) {
            app(ApplicationErrorRecorder::class)->recordJobFailure(
                self::class,
                $exception,
                ['shipment_webhook_event_id' => $this->eventId],
            );
        }
    }

    private function findShipment(ShipmentWebhookEvent $event): ?ShiprocketShipment
    {
        if (filled($event->awb)) {
            $byAwb = ShiprocketShipment::query()->where('awb_code', $event->awb)->first();
            if ($byAwb) {
                return $byAwb;
            }
        }

        if (filled($event->remote_order_id)) {
            $byRemote = ShiprocketShipment::query()
                ->where('shiprocket_order_id', $event->remote_order_id)
                ->first();
            if ($byRemote) {
                return $byRemote;
            }
        }

        $payload = is_array($event->payload) ? $event->payload : [];
        $channelOrderId = trim((string) (
            $payload['channel_order_id']
            ?? $payload['order_id']
            ?? $payload['sr_channel_order_id']
            ?? ''
        ));

        if ($channelOrderId === '') {
            return null;
        }

        return ShiprocketShipment::query()
            ->whereHas('order', fn ($query) => $query->where('number', $channelOrderId))
            ->first();
    }
}
