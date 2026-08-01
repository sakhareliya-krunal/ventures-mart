<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'number',
        'invoice_number',
        'invoice_issued_at',
        'user_id',
        'full_name',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'postal_code',
        'seller_state',
        'subtotal',
        'shipping',
        'cod_fee',
        'cgst',
        'sgst',
        'igst',
        'tax',
        'total',
        'status',
        'payment_status',
        'payment_method',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'paid_at',
        'courier_partner',
        'awb_number',
        'tracking_number',
        'dispatched_at',
        'expected_delivery_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'float',
            'shipping' => 'float',
            'cod_fee' => 'float',
            'cgst' => 'float',
            'sgst' => 'float',
            'igst' => 'float',
            'tax' => 'float',
            'total' => 'float',
            'paid_at' => 'datetime',
            'invoice_issued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }
}
