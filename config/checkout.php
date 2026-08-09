<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cash on Delivery fee
    |--------------------------------------------------------------------------
    |
    | Flat handling charge (INR) added only when payment_method is cod.
    | Not applied to online (Razorpay) payments. Not included in GST base.
    |
    */
    'cod_fee' => 99,
];
