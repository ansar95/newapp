<?php

namespace App\Support;

class BoqItemPriceInput
{
    /**
     * Maps legacy single-price input (unit_price) onto material_unit_price so older forms, CSV files and callers keep working.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function normalize(array $item): array
    {
        if (! array_key_exists('material_unit_price', $item) && array_key_exists('unit_price', $item)) {
            $item['material_unit_price'] = $item['unit_price'];
            $item['service_unit_price'] ??= 0;
        }

        if (array_key_exists('service_unit_price', $item) && blank($item['service_unit_price'])) {
            $item['service_unit_price'] = 0;
        }

        unset($item['unit_price']);

        return $item;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $prefix = ''): array
    {
        return [
            $prefix.'material_unit_price.required' => 'Harga material wajib diisi (isi 0 jika tidak ada).',
            $prefix.'material_unit_price.min' => 'Harga material tidak boleh kurang dari 0.',
            $prefix.'service_unit_price.min' => 'Harga jasa tidak boleh kurang dari 0.',
        ];
    }
}
