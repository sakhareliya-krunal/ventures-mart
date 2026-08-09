<?php

namespace App\Enums;

enum OrderInventoryStatus: string
{
    case Unallocated = 'unallocated';
    case Reserved = 'reserved';
    case Committed = 'committed';
    case Consumed = 'consumed';
    case Released = 'released';
    case Exception = 'exception';
}
