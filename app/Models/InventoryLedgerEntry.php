<?php

namespace App\Models;

use App\Enums\InventoryLedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLedgerEntry extends Model
{
    protected $fillable = [
        'uuid',
        'product_id',
        'order_id',
        'order_item_id',
        'actor_id',
        'type',
        'on_hand_delta',
        'reserved_delta',
        'committed_delta',
        'on_hand_balance',
        'reserved_balance',
        'committed_balance',
        'idempotency_key',
        'correlation_id',
        'reason',
        'metadata',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InventoryLedgerType::class,
            'on_hand_delta' => 'integer',
            'reserved_delta' => 'integer',
            'committed_delta' => 'integer',
            'on_hand_balance' => 'integer',
            'reserved_balance' => 'integer',
            'committed_balance' => 'integer',
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
