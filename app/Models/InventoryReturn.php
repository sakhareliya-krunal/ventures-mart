<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReturn extends Model
{
    protected $fillable = [
        'uuid',
        'order_id',
        'order_item_id',
        'product_id',
        'actor_id',
        'quantity',
        'disposition',
        'status',
        'reason',
        'idempotency_key',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
