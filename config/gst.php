<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seller / place of supply state
    |--------------------------------------------------------------------------
    |
    | Used to decide CGST+SGST (intra-state) vs IGST (inter-state).
    |
    */

    'seller_state' => env('GST_SELLER_STATE', 'Gujarat'),

    'rate' => 0.05,

];
