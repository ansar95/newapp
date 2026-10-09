<?php

namespace App\Enums;

enum ProjectTermCalculationType: string
{
    case Percentage = 'PERCENTAGE';
    case FixedAmount = 'FIXED_AMOUNT';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Persentase',
            self::FixedAmount => 'Nilai Tetap',
        };
    }
}
