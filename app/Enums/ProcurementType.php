<?php

namespace App\Enums;

enum ProcurementType: string
{
    case Bmhp = 'BMHP';
    case Asset = 'ASSET';
    case Rental = 'RENTAL';

    public function label(): string
    {
        return match ($this) {
            self::Bmhp => 'BMHP',
            self::Asset => 'Aset',
            self::Rental => 'Sewa Alat',
        };
    }

    /**
     * BMHP and rental costs are allocated to BOQ items; purchased assets are not a BOQ cost.
     */
    public function isAllocatable(): bool
    {
        return $this !== self::Asset;
    }
}
