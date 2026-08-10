<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiprocketShipment extends Model
{
    protected $fillable = [
        'order_id',
        'sync_status',
        'stage',
        'shiprocket_order_id',
        'shipment_id',
        'courier_company_id',
        'courier_name',
        'awb_code',
        'pickup_status',
        'shipment_status',
        'shipment_status_id',
        'tracking_url',
        'label_url',
        'manifest_url',
        'request_fingerprint',
        'attempts',
        'last_error',
        'order_created_at',
        'awb_assigned_at',
        'pickup_scheduled_at',
        'last_synced_at',
        'last_provider_event_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'order_created_at' => 'datetime',
            'awb_assigned_at' => 'datetime',
            'pickup_scheduled_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_provider_event_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillmentEvents(): HasMany
    {
        return $this->hasMany(OrderFulfillmentEvent::class);
    }

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(ShiprocketTrackingEvent::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(ShipmentWebhookEvent::class);
    }
}
