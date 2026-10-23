<?php

namespace App\Support;

use App\Models\Boq;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BoqCogsCsv
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'cogs_name',
        'cogs_description',
        'item_code',
        'item_name',
        'item_description',
        'quantity',
        'unit',
        'material_price',
        'service_price',
        'level_2',
        'level_3',
    ];

    /**
     * Item codes are generated per COGS on import (ITEM-001, ITEM-002, ...), so the template has no item_code column.
     *
     * @var list<string>
     */
    public const TEMPLATE_HEADERS = [
        'cogs_name',
        'cogs_description',
        'item_name',
        'item_description',
        'quantity',
        'unit',
        'material_price',
        'service_price',
        'level_2',
        'level_3',
    ];

    /**
     * @return list<list<string|int|float>>
     */
    public static function templateRows(): array
    {
        return [
            self::TEMPLATE_HEADERS,
            ['Engineering', 'Design works', 'Design Drawing', '', 1, 'ls', 0, 2000000, '', ''],
            ['Engineering', 'Design works', 'Structural Calculation', '', 1, 'ls', 0, 3000000, '', ''],
            ['Installation', 'Site works', 'Cable Tray', '', 10, 'm', 75000, 25000, 'Termasuk aksesoris', ''],
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public static function rowsFromBoq(Boq $boq): array
    {
        $boq->loadMissing('cogs.items');

        $rows = [self::HEADERS];

        foreach ($boq->cogs->sortBy('sort_order') as $cogs) {
            foreach ($cogs->items->sortBy('sort_order') as $item) {
                $rows[] = [
                    $cogs->name,
                    (string) ($cogs->description ?? ''),
                    $item->code,
                    $item->name,
                    (string) ($item->description ?? ''),
                    self::formatNumber($item->quantity),
                    $item->unit,
                    self::formatNumber($item->material_unit_price),
                    self::formatNumber($item->service_unit_price),
                    (string) ($item->notes ?? ''),
                    (string) ($item->level_3 ?? ''),
                ];
            }
        }

        return $rows;
    }

    /**
     * @return Collection<int, array{
     *     cogs_name: string,
     *     cogs_description: string|null,
     *     item_code: string,
     *     item_name: string,
     *     item_description: string|null,
     *     quantity: string,
     *     unit: string,
     *     material_price: string,
     *     service_price: string,
     *     level_2: string|null,
     *     level_3: string|null,
     *     _line: int
     * }>
     */
    public static function parse(UploadedFile|string $source): Collection
    {
        $lines = SpreadsheetFile::rows($source);
        $header = array_shift($lines);

        if ($header === null || SpreadsheetFile::rowIsEmpty($header)) {
            throw new InvalidArgumentException('File kosong atau tidak valid.');
        }

        $header = array_map(fn ($value) => self::normalizeHeader((string) $value), $header);

        $required = [
            'cogs_name',
            'cogs_description',
            'item_name',
            'item_description',
            'quantity',
            'unit',
        ];

        foreach ($required as $column) {
            if (! in_array($column, $header, true)) {
                throw new InvalidArgumentException(
                    'Kolom wajib hilang: '.$column.'. Gunakan template yang disediakan.'
                );
            }
        }

        $index = array_flip($header);
        $materialColumn = isset($index['material_price']) ? 'material_price' : 'unit_price';

        if (! isset($index[$materialColumn])) {
            throw new InvalidArgumentException(
                'Kolom wajib hilang: material_price. Gunakan template yang disediakan.'
            );
        }

        $rows = collect();

        foreach ($lines as $offset => $data) {
            if (SpreadsheetFile::rowIsEmpty($data)) {
                continue;
            }

            $rows->push([
                'cogs_name' => trim((string) ($data[$index['cogs_name']] ?? '')),
                'cogs_description' => self::nullableTrim($data[$index['cogs_description']] ?? null),
                'item_code' => isset($index['item_code']) ? trim((string) ($data[$index['item_code']] ?? '')) : '',
                'item_name' => trim((string) ($data[$index['item_name']] ?? '')),
                'item_description' => self::nullableTrim($data[$index['item_description']] ?? null),
                'quantity' => SpreadsheetFile::normalizeNumber((string) ($data[$index['quantity']] ?? '')),
                'unit' => trim((string) ($data[$index['unit']] ?? '')),
                'material_price' => SpreadsheetFile::normalizeNumber((string) ($data[$index[$materialColumn]] ?? '')),
                'service_price' => isset($index['service_price'])
                    ? SpreadsheetFile::normalizeNumber((string) ($data[$index['service_price']] ?? ''))
                    : '',
                'level_2' => self::levelValue($data, $index, 'level_2', 'item_notes'),
                'level_3' => isset($index['level_3'])
                    ? self::nullableTrim($data[$index['level_3']] ?? null)
                    : null,
                '_line' => $offset + 2,
            ]);
        }

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('File tidak berisi baris data.');
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     */
    public static function rowsToCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new InvalidArgumentException('Tidak dapat menulis CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    private static function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    /**
     * @param  list<string|null>  $data
     * @param  array<string, int>  $index
     */
    private static function levelValue(array $data, array $index, string $column, string $legacyColumn): ?string
    {
        if (isset($index[$column])) {
            return self::nullableTrim($data[$index[$column]] ?? null);
        }

        if (isset($index[$legacyColumn])) {
            return self::nullableTrim($data[$index[$legacyColumn]] ?? null);
        }

        return null;
    }

    private static function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function formatNumber(mixed $value): int|float
    {
        $number = (float) $value;

        if (abs($number - round($number)) < 0.00001) {
            return (int) round($number);
        }

        return round($number, 4);
    }
}
