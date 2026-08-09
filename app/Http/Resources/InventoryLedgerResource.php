<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryLedgerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'product_id' => $this->product_id,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'actor_id' => $this->actor_id,
            'type' => $this->type->value,
            'deltas' => [
                'on_hand' => $this->on_hand_delta,
                'reserved' => $this->reserved_delta,
                'committed' => $this->committed_delta,
            ],
            'balances' => [
                'on_hand' => $this->on_hand_balance,
                'reserved' => $this->reserved_balance,
                'committed' => $this->committed_balance,
            ],
            'reason' => $this->reason,
            'correlation_id' => $this->correlation_id,
            'metadata' => $this->metadata,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
