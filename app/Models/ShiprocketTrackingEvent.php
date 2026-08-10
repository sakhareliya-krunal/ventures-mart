<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiprocketTrackingEvent extends Model
{
    protected $fillable = [
        'shiprocket_shipment_id',
        'event_hash',
        'status',
        'status_id',
        'location',
        'source',
        'raw',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(ShiprocketShipment::class, 'shiprocket_shipment_id');
    }
}
