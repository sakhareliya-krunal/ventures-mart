<?php

namespace App\Enums;

enum InventoryLedgerType: string
{
    case OpeningBalance = 'opening_balance';
    case ManualReceipt = 'manual_receipt';
    case ManualCorrection = 'manual_correction';
    case RazorpayReserved = 'razorpay_reserved';
    case ReservationReleased = 'reservation_released';
    case ReservationExpired = 'reservation_expired';
    case OrderCommitted = 'order_committed';
    case CourierHandoff = 'courier_handoff';
    case CancellationReleased = 'cancellation_released';
    case ReturnRestocked = 'return_restocked';
    case DamagedWriteoff = 'damaged_writeoff';
    case ReconciliationCorrection = 'reconciliation_correction';
}
