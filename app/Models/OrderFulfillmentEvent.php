<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFulfillmentEvent extends Model
{
    protected $fillable = [
        'order_id',
        'shiprocket_shipment_id',
        'actor_user_id',
        'source',
        'event_type',
        'previous_method',
        'new_method',
        'previous_status',
        'new_status',
        'provider_status',
        'provider_status_id',
        'external_event_id',
        'idempotency_key',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShiprocketShipment::class, 'shiprocket_shipment_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
