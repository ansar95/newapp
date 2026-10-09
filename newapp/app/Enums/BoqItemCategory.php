<?php

namespace App\Enums;

enum BoqItemCategory: string
{
    case Consumable = 'consumable';
    case MainMaterial = 'main_material';

    public function label(): string
    {
        return match ($this) {
            self::Consumable => 'Barang Consumable',
            self::MainMaterial => 'Material Utama',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
