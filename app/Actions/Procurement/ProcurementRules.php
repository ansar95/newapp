<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementType;
use App\Models\Asset;
use Illuminate\Validation\Rule;

class ProcurementRules
{
    public const MAX_ASSET_UNITS_PER_ITEM = 50;

    /**
     * @return array<string, mixed>
     */
    public static function request(?ProcurementType $type): array
    {
        $rules = [
            'request_date' => ['required', 'date'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['required', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];

        return match ($type) {
            ProcurementType::Asset => [
                ...$rules,
                'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ASSET_UNITS_PER_ITEM],
                'items.*.asset_category' => ['required', 'string', Rule::in(Asset::categoryOptions())],
                'items.*.brand' => ['nullable', 'string', 'max:255'],
                'items.*.model' => ['nullable', 'string', 'max:255'],
            ],
            ProcurementType::Rental => [
                ...$rules,
                'items.*.rental_start' => ['required', 'date'],
                'items.*.rental_end' => ['required', 'date', 'after_or_equal:items.*.rental_start'],
            ],
            default => $rules,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'type.required' => 'Jenis permintaan wajib dipilih.',
            'type.enum' => 'Jenis permintaan tidak valid.',
            'request_date.required' => 'Tanggal permintaan wajib diisi.',
            'items.required' => 'Permintaan harus memiliki minimal satu item.',
            'items.min' => 'Permintaan harus memiliki minimal satu item.',
            'items.*.item_name.required' => 'Nama barang wajib diisi.',
            'items.*.unit.required' => 'Satuan wajib diisi.',
            'items.*.quantity.required' => 'Quantity wajib diisi.',
            'items.*.quantity.gt' => 'Quantity harus lebih dari 0.',
            'items.*.quantity.integer' => 'Quantity aset harus bilangan bulat (1 unit = 1 aset).',
            'items.*.quantity.min' => 'Quantity aset minimal 1.',
            'items.*.quantity.max' => 'Quantity aset maksimal '.self::MAX_ASSET_UNITS_PER_ITEM.' unit per baris.',
            'items.*.unit_price.required' => 'Harga satuan wajib diisi.',
            'items.*.unit_price.min' => 'Harga satuan tidak boleh kurang dari 0.',
            'items.*.asset_category.required' => 'Kategori aset wajib dipilih.',
            'items.*.asset_category.in' => 'Kategori aset tidak valid.',
            'items.*.rental_start.required' => 'Tanggal mulai sewa wajib diisi.',
            'items.*.rental_end.required' => 'Tanggal selesai sewa wajib diisi.',
            'items.*.rental_end.after_or_equal' => 'Tanggal selesai sewa tidak boleh sebelum tanggal mulai.',
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    public static function itemAttributes(ProcurementType $type, array $item): array
    {
        return [
            'item_name' => $item['item_name'],
            'unit' => $item['unit'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'notes' => $item['notes'] ?? null,
            'asset_category' => $type === ProcurementType::Asset ? $item['asset_category'] : null,
            'brand' => $type === ProcurementType::Asset ? ($item['brand'] ?? null) : null,
            'model' => $type === ProcurementType::Asset ? ($item['model'] ?? null) : null,
            'rental_start' => $type === ProcurementType::Rental ? $item['rental_start'] : null,
            'rental_end' => $type === ProcurementType::Rental ? $item['rental_end'] : null,
        ];
    }
}
