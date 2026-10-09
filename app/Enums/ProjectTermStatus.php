<?php

namespace App\Enums;

/**
 * Computed term status. Never stored.
 */
enum ProjectTermStatus: string
{
    case NotReady = 'NOT_READY';
    case Ready = 'READY';
    case PartiallyBilled = 'PARTIALLY_BILLED';
    case FullyBilled = 'FULLY_BILLED';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::NotReady => 'Not Ready',
            self::Ready => 'Ready',
            self::PartiallyBilled => 'Partially Billed',
            self::FullyBilled => 'Fully Billed',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
