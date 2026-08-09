<?php

namespace App\Services\Inventory;

use App\Models\InventoryOutboxMessage;
use Illuminate\Support\Str;

class InventoryOutbox
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        string $aggregateType,
        int $aggregateId,
        string $idempotencyKey,
        array $payload,
    ): InventoryOutboxMessage {
        return InventoryOutboxMessage::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'uuid' => (string) Str::uuid(),
                'event_type' => $eventType,
                'aggregate_type' => $aggregateType,
                'aggregate_id' => $aggregateId,
                'payload' => $payload,
                'available_at' => now(),
            ],
        );
    }
}
