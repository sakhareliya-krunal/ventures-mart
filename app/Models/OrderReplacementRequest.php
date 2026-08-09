<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReplacementRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'order_item_id',
        'status',
        'reason',
        'notes',
        'photo_paths',
        'reviewed_by_user_id',
        'reviewed_at',
        'rejection_reason',
        'replacement_order_id',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_paths' => 'array',
            'reviewed_at' => 'datetime',
            'requested_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function replacementOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['requested', 'under_review', 'approved'], true);
    }
}
