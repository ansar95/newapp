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
    ];

    public static function templateCsv(): string
    {
        $rows = [
            self::HEADERS,
            ['Engineering', 'Design works', 'ITEM-E01', 'Design Drawing', '', '1', 'ls', '0', '2000000'],
            ['Engineering', 'Design works', 'ITEM-E02', 'Structural Calculation', '', '1', 'ls', '0', '3000000'],
            ['Installation', 'Site works', 'ITEM-I01', 'Cable Tray', '', '10', 'm', '75000', '25000'],
        ];

        return self::rowsToCsv($rows);
    }

    public static function fromBoq(Boq $boq): string
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
                ];
            }
        }

        return self::rowsToCsv($rows);
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
     *     _line: int
     * }>
     */
    public static function parse(UploadedFile|string $source): Collection
    {
        $handle = is_string($source)
            ? fopen('php://temp', 'r+')
            : fopen($source->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('File CSV tidak dapat dibaca.');
        }

        if (is_string($source)) {
            fwrite($handle, $source);
            rewind($handle);
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === [null] || $header === []) {
                throw new InvalidArgumentException('File CSV kosong atau tidak valid.');
            }

            $header = array_map(fn ($value) => self::normalizeHeader((string) $value), $header);

            $required = [
                'cogs_name',
                'cogs_description',
                'item_code',
                'item_name',
                'item_description',
                'quantity',
                'unit',
            ];

            foreach ($required as $column) {
                if (! in_array($column, $header, true)) {
                    throw new InvalidArgumentException(
                        'Kolom CSV wajib hilang: '.$column.'. Gunakan template yang disediakan.'
                    );
                }
            }

            $index = array_flip($header);
            $materialColumn = isset($index['material_price']) ? 'material_price' : 'unit_price';

            if (! isset($index[$materialColumn])) {
                throw new InvalidArgumentException(
                    'Kolom CSV wajib hilang: material_price. Gunakan template yang disediakan.'
                );
            }
            $rows = collect();
            $line = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $line++;

                if ($data === [null] || $data === [] || self::rowIsEmpty($data)) {
                    continue;
                }

                $rows->push([
                    'cogs_name' => trim((string) ($data[$index['cogs_name']] ?? '')),
                    'cogs_description' => self::nullableTrim($data[$index['cogs_description']] ?? null),
                    'item_code' => trim((string) ($data[$index['item_code']] ?? '')),
                    'item_name' => trim((string) ($data[$index['item_name']] ?? '')),
                    'item_description' => self::nullableTrim($data[$index['item_description']] ?? null),
                    'quantity' => trim((string) ($data[$index['quantity']] ?? '')),
                    'unit' => trim((string) ($data[$index['unit']] ?? '')),
                    'material_price' => trim((string) ($data[$index[$materialColumn]] ?? '')),
                    'service_price' => isset($index['service_price'])
                        ? trim((string) ($data[$index['service_price']] ?? ''))
                        : '',
                    '_line' => $line,
                ]);
            }

            if ($rows->isEmpty()) {
                throw new InvalidArgumentException('File CSV tidak berisi baris data.');
            }

            return $rows;
        } finally {
            fclose($handle);
        }
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

    private static function nullableTrim(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  list<string|null>  $data
     */
    private static function rowIsEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private static function formatNumber(mixed $value): string
    {
        $number = (float) $value;

        if (abs($number - round($number)) < 0.00001) {
            return (string) (int) round($number);
        }

        return rtrim(rtrim(number_format($number, 4, '.', ''), '0'), '.');
    }
}
