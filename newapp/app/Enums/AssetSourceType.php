<?php

namespace App\Enums;

enum AssetSourceType: string
{
    case Purchase = 'PURCHASE';
    case Existing = 'EXISTING';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Pembelian',
            self::Existing => 'Existing',
        };
    }
}
