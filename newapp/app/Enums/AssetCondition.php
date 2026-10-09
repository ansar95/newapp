<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Good = 'GOOD';
    case Fair = 'FAIR';
    case Poor = 'POOR';
    case Damaged = 'DAMAGED';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Baik',
            self::Fair => 'Cukup Baik',
            self::Poor => 'Kurang Baik',
            self::Damaged => 'Rusak',
        };
    }
}
