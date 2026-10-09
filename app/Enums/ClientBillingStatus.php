<?php

namespace App\Enums;

/**
 * DRAFT, SUBMITTED, SENT and CANCELLED are stored workflow states.
 * PARTIALLY_PAID and PAID are only ever computed from client payments.
 */
enum ClientBillingStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Sent = 'SENT';
    case PartiallyPaid = 'PARTIALLY_PAID';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Sent => 'Sent',
            self::PartiallyPaid => 'Partially Paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * A billing counts as billed to the client once it has been sent.
     */
    public function countsAsBilled(): bool
    {
        return $this === self::Sent;
    }

    /**
     * Non-cancelled billings reserve part of the term so it cannot be billed twice.
     */
    public function reservesTermAmount(): bool
    {
        return $this !== self::Cancelled;
    }
}
