<?php

namespace App\Enums;

enum InventoryReservationState: string
{
    case Reserved = 'reserved';
    case Committed = 'committed';
    case Consumed = 'consumed';
    case Released = 'released';
    case Expired = 'expired';
    case Exception = 'exception';
}
