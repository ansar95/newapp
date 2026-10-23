<?php

namespace App\Enums;

enum SCurveGranularity: string
{
    case Week = 'week';
    case Month = 'month';

    public function label(): string
    {
        return match ($this) {
            self::Week => 'Mingguan',
            self::Month => 'Bulanan',
        };
    }
}
