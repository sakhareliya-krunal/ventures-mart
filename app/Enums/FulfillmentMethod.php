<?php

namespace App\Enums;

enum FulfillmentMethod: string
{
    case Shiprocket = 'shiprocket';
    case Manual = 'manual';
}
