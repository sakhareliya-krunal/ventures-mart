<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice series
    |--------------------------------------------------------------------------
    */
    'prefix' => env('INVOICE_PREFIX', 'VM'),

    /*
    |--------------------------------------------------------------------------
    | Seller / supplier (GST tax invoice)
    |--------------------------------------------------------------------------
    */
    'legal_name' => env('INVOICE_LEGAL_NAME', 'Neelkanth Emporium'),
    'trade_name' => env('INVOICE_TRADE_NAME', env('APP_NAME', 'Ventures Mart')),
    'gstin' => env('INVOICE_GSTIN', '24EDLPK6446N1ZX'),
    'address_line1' => env('INVOICE_ADDRESS_LINE1', 'Shed no 2, first floor, opposite Patel corporation,'),
    'address_line2' => env('INVOICE_ADDRESS_LINE2', 'Pan business park, Shapar,'),
    'city' => env('INVOICE_CITY', 'Rajkot'),
    'state' => env('INVOICE_STATE', env('GST_SELLER_STATE', 'Gujarat')),
    'state_code' => env('INVOICE_STATE_CODE', '24'),
    'postal_code' => env('INVOICE_POSTAL_CODE', '360024'),
    'country' => env('INVOICE_COUNTRY', 'India'),
    'phone' => env('INVOICE_PHONE', '+91 91732 79323'),
    'email' => env('INVOICE_EMAIL', 'neelkanthventures1804@gmail.com'),
    'website' => env('INVOICE_WEBSITE', 'https://venturesmart.in'),

    /*
    |--------------------------------------------------------------------------
    | Default HSN when product/order item has none
    |--------------------------------------------------------------------------
    */
    'default_hsn' => env('INVOICE_DEFAULT_HSN', '9503'),

    'logo' => env('INVOICE_LOGO', 'images/ventures-mart-logo-invoice.png'),

    /*
    |--------------------------------------------------------------------------
    | Public order URL used for invoice QR
    |--------------------------------------------------------------------------
    */
    'order_url_template' => env('INVOICE_ORDER_URL', 'https://venturesmart.in/orders/{number}'),
];
