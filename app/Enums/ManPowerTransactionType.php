<?php

namespace App\Enums;

enum ManPowerTransactionType: string
{
    case Kasbon = 'KASBON';
    case Pelunasan = 'PELUNASAN';

    public function label(): string
    {
        return match ($this) {
            self::Kasbon => 'Kasbon',
            self::Pelunasan => 'Pelunasan',
        };
    }
}
