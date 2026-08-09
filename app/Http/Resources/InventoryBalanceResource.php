<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'sku' => $this->product?->sku,
                'image' => $this->product?->image,
            ],
            'on_hand' => $this->on_hand,
            'reserved' => $this->reserved,
            'committed' => $this->committed,
            'available' => $this->available(),
            'version' => $this->version,
            'low_stock_threshold' => $this->low_stock_threshold,
            'reorder_point' => $this->reorder_point,
            'is_low_stock' => $this->available() <= ($this->low_stock_threshold ?? 0),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
