<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'product_sku',
        'hsn',
        'product_slug',
        'product_image',
        'unit_price',
        'quantity',
        'shipped_quantity',
        'returned_quantity',
        'restocked_quantity',
        'weight_kg',
        'length_cm',
        'breadth_cm',
        'height_cm',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'float',
            'line_total' => 'float',
            'weight_kg' => 'float',
            'length_cm' => 'float',
            'breadth_cm' => 'float',
            'height_cm' => 'float',
            'quantity' => 'integer',
            'shipped_quantity' => 'integer',
            'returned_quantity' => 'integer',
            'restocked_quantity' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function inventoryReservation(): HasOne
    {
        return $this->hasOne(InventoryReservation::class);
    }

    public function inventoryLedgerEntries(): HasMany
    {
        return $this->hasMany(InventoryLedgerEntry::class);
    }
}
