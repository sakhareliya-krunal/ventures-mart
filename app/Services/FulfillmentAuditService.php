<?php

namespace App\Services;

use App\Enums\FulfillmentMethod;
use App\Models\Order;
use App\Models\OrderFulfillmentEvent;
use App\Models\ShiprocketShipment;
use Carbon\CarbonInterface;

class FulfillmentAuditService
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        Order $order,
        string $eventType,
        string $source,
        string $idempotencyKey,
        array $details = [],
    ): OrderFulfillmentEvent {
        $shipment = $details['shipment'] ?? null;
        $occurredAt = $details['occurred_at'] ?? null;

        return OrderFulfillmentEvent::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'order_id' => $order->id,
                'shiprocket_shipment_id' => $shipment instanceof ShiprocketShipment ? $shipment->id : null,
                'actor_user_id' => $details['actor_user_id'] ?? null,
                'source' => $source,
                'event_type' => $eventType,
                'previous_method' => $this->methodValue($details['previous_method'] ?? null),
                'new_method' => $this->methodValue($details['new_method'] ?? null),
                'previous_status' => $details['previous_status'] ?? null,
                'new_status' => $details['new_status'] ?? null,
                'provider_status' => $details['provider_status'] ?? null,
                'provider_status_id' => isset($details['provider_status_id'])
                    ? (string) $details['provider_status_id']
                    : null,
                'external_event_id' => $details['external_event_id'] ?? null,
                'reason' => $details['reason'] ?? null,
                'metadata' => $details['metadata'] ?? null,
                'occurred_at' => $occurredAt instanceof CarbonInterface
                    ? $occurredAt
                    : null,
            ],
        );
    }

    private function methodValue(FulfillmentMethod|string|null $method): ?string
    {
        return $method instanceof FulfillmentMethod ? $method->value : $method;
    }
}
