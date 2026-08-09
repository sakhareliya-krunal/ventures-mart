<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryOutboxMessage extends Model
{
    protected $fillable = [
        'uuid',
        'event_type',
        'aggregate_type',
        'aggregate_id',
        'idempotency_key',
        'payload',
        'attempts',
        'available_at',
        'processed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'aggregate_id' => 'integer',
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
