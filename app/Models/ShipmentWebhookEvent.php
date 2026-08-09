<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentWebhookEvent extends Model
{
    protected $fillable = [
        'provider',
        'external_event_key',
        'order_id',
        'shiprocket_shipment_id',
        'awb',
        'remote_order_id',
        'event_type',
        'provider_status_id',
        'provider_occurred_at',
        'status',
        'attempts',
        'payload',
        'last_error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_occurred_at' => 'datetime',
            'payload' => 'array',
            'processed_at' => 'datetime',
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
}
