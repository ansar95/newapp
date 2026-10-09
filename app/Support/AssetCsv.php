<?php

namespace App\Support;

use App\Enums\AssetCondition;
use App\Enums\AssetSourceType;
use App\Models\Asset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AssetCsv
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'code',
        'name',
        'category',
        'brand',
        'model',
        'unit',
        'source_type',
        'condition',
        'purchase_year',
        'description',
        'request_number',
        'request_date',
        'request_description',
    ];

    public static function templateCsv(): string
    {
        $rows = [
            self::HEADERS,
            [
                'AST-0100',
                'Laptop Dell Latitude 5450',
                'IT Equipment',
                'Dell',
                'Latitude 5450',
                'Unit',
                AssetSourceType::Existing->value,
                AssetCondition::Good->value,
                '2024',
                'Laptop existing gudang',
                '',
                '',
                '',
            ],
            [
                'AST-0101',
                'Portable Generator',
                'Machinery',
                'Honda',
                'EU70is',
                'Unit',
                AssetSourceType::Purchase->value,
                AssetCondition::Good->value,
                '2026',
                'Generator untuk site',
                'SPP-2026-100',
                '2026-01-15',
                'Permohonan pembelian generator',
            ],
        ];

        return self::rowsToCsv($rows);
    }

    public static function fromAssets(): string
    {
        $rows = [self::HEADERS];

        Asset::query()
            ->orderBy('code')
            ->get()
            ->each(function (Asset $asset) use (&$rows) {
                $rows[] = [
                    $asset->code,
                    $asset->name,
                    $asset->category,
                    (string) ($asset->brand ?? ''),
                    (string) ($asset->model ?? ''),
                    (string) ($asset->unit ?? 'Unit'),
                    $asset->source_type instanceof AssetSourceType
                        ? $asset->source_type->value
                        : (string) $asset->source_type,
                    $asset->condition instanceof AssetCondition
                        ? $asset->condition->value
                        : AssetCondition::Good->value,
                    $asset->purchase_year !== null ? (string) $asset->purchase_year : '',
                    (string) ($asset->description ?? ''),
                    (string) ($asset->request_number ?? ''),
                    $asset->request_date?->format('Y-m-d') ?? '',
                    (string) ($asset->request_description ?? ''),
                ];
            });

        return self::rowsToCsv($rows);
    }

    /**
     * @return Collection<int, array<string, mixed>>
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

            foreach (['code', 'name', 'category', 'source_type'] as $column) {
                if (! in_array($column, $header, true)) {
                    throw new InvalidArgumentException(
                        'Kolom CSV wajib hilang: '.$column.'. Gunakan template yang disediakan.'
                    );
                }
            }

            $index = array_flip($header);
            $rows = collect();
            $line = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $line++;

                if ($data === [null] || $data === [] || self::rowIsEmpty($data)) {
                    continue;
                }

                $rows->push([
                    'code' => trim((string) ($data[$index['code']] ?? '')),
                    'name' => trim((string) ($data[$index['name']] ?? '')),
                    'category' => trim((string) ($data[$index['category']] ?? '')),
                    'brand' => isset($index['brand']) ? self::nullableTrim($data[$index['brand']] ?? null) : null,
                    'model' => isset($index['model']) ? self::nullableTrim($data[$index['model']] ?? null) : null,
                    'unit' => isset($index['unit'])
                        ? (self::nullableTrim($data[$index['unit']] ?? null) ?? 'Unit')
                        : 'Unit',
                    'source_type' => self::normalizeSourceType(trim((string) ($data[$index['source_type']] ?? ''))),
                    'condition' => self::normalizeCondition(
                        isset($index['condition']) ? trim((string) ($data[$index['condition']] ?? '')) : ''
                    ),
                    'purchase_year' => isset($index['purchase_year'])
                        ? self::nullableTrim($data[$index['purchase_year']] ?? null)
                        : null,
                    'description' => isset($index['description'])
                        ? self::nullableTrim($data[$index['description']] ?? null)
                        : null,
                    'request_number' => isset($index['request_number'])
                        ? self::nullableTrim($data[$index['request_number']] ?? null)
                        : null,
                    'request_date' => isset($index['request_date'])
                        ? self::nullableTrim($data[$index['request_date']] ?? null)
                        : null,
                    'request_description' => isset($index['request_description'])
                        ? self::nullableTrim($data[$index['request_description']] ?? null)
                        : null,
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

    public static function normalizeSourceType(string $value): string
    {
        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($value)));

        return match ($normalized) {
            'PURCHASE', 'PEMBELIAN', 'BELI' => AssetSourceType::Purchase->value,
            'EXISTING', 'EXIST', 'SUDAH_ADA', 'SUDAHADA' => AssetSourceType::Existing->value,
            default => $normalized,
        };
    }

    public static function normalizeCondition(string $value): string
    {
        $normalized = strtoupper(str_replace([' ', '-'], '_', trim($value)));

        return match ($normalized) {
            '', 'GOOD', 'BAIK' => AssetCondition::Good->value,
            'FAIR', 'CUKUP', 'CUKUP_BAIK' => AssetCondition::Fair->value,
            'POOR', 'KURANG', 'KURANG_BAIK' => AssetCondition::Poor->value,
            'DAMAGED', 'RUSAK' => AssetCondition::Damaged->value,
            default => $normalized === '' ? AssetCondition::Good->value : $normalized,
        };
    }

    private static function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;

        return strtolower(trim($value));
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

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
}
