<?php

namespace App\Enums;

enum BmhpPurchaseStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Confirmed = 'CONFIRMED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Confirmed => 'Confirmed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Header, detail, and allocation can only be changed while the purchase is still a draft.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function countsAsActual(): bool
    {
        return $this === self::Confirmed;
    }
}
