<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryBalance extends Model
{
    protected $fillable = [
        'product_id',
        'on_hand',
        'reserved',
        'committed',
        'version',
        'low_stock_threshold',
        'reorder_point',
        'low_stock_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'on_hand' => 'integer',
            'reserved' => 'integer',
            'committed' => 'integer',
            'version' => 'integer',
            'low_stock_threshold' => 'integer',
            'reorder_point' => 'integer',
            'low_stock_notified_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function available(): int
    {
        return max(0, $this->on_hand - $this->reserved - $this->committed);
    }
}
