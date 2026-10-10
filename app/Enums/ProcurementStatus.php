<?php

namespace App\Enums;

enum ProcurementStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Approved = 'APPROVED';
    case Paid = 'PAID';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::Approved => 'Disetujui',
            self::Paid => 'Dibayar',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Header and items can only be changed while the request is still a draft.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isCancellable(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Approved], true);
    }
}
