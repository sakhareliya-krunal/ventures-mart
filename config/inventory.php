<?php

return [
    'default_low_stock_threshold' => (int) env('INVENTORY_DEFAULT_LOW_STOCK_THRESHOLD', 5),

    'default_reorder_point' => (int) env('INVENTORY_DEFAULT_REORDER_POINT', 10),

    'payment_reservation_ttl_minutes' => (int) env('INVENTORY_PAYMENT_RESERVATION_TTL', 15),

    'send_low_stock_email' => (bool) env('INVENTORY_SEND_LOW_STOCK_EMAIL', false),
];
