<?php

namespace App\Enums;

enum ProjectAssetStatus: string
{
    case InUse = 'IN_USE';
    case Released = 'RELEASED';

    public function label(): string
    {
        return match ($this) {
            self::InUse => 'In Use',
            self::Released => 'Released',
        };
    }
}
