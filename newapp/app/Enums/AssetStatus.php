<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Requested = 'REQUESTED';
    case Available = 'AVAILABLE';
    case InUse = 'IN_USE';
    case Inactive = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Available => 'Available',
            self::InUse => 'In Use',
            self::Inactive => 'Inactive',
        };
    }

    public function canBeAssigned(): bool
    {
        return $this === self::Available;
    }
}
