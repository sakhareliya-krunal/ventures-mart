<?php

namespace App\Models;

use App\Enums\InventoryReservationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    protected $fillable = [
        'order_item_id',
        'order_id',
        'product_id',
        'quantity',
        'state',
        'expires_at',
        'committed_at',
        'consumed_at',
        'released_at',
        'expired_at',
        'release_reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'state' => InventoryReservationState::class,
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'consumed_at' => 'datetime',
            'released_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
