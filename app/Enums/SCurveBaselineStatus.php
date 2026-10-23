<?php

namespace App\Enums;

enum SCurveBaselineStatus: string
{
    case Draft = 'draft';
    case Established = 'established';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Established => 'Ditetapkan',
        };
    }
}
