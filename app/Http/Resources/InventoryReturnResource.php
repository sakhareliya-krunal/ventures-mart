<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'order_id' => $this->order_id,
            'order_item_id' => $this->order_item_id,
            'product_id' => $this->product_id,
            'quantity' => $this->quantity,
            'disposition' => $this->disposition,
            'status' => $this->status,
            'reason' => $this->reason,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
