<?php

namespace App\Enums;

enum MasterBmhpStatus: string
{
    case Active = 'ACTIVE';
    case Inactive = 'INACTIVE';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }
}
