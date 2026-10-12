<?php

namespace App\Support;

use App\Models\ManPower;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ManPowerCsv
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'code',
        'name',
        'position',
        'daily_wage',
        'phone',
        'is_active',
        'notes',
    ];

    /**
     * @return list<list<string|int>>
     */
    public static function templateRows(): array
    {
        return [
            self::HEADERS,
            ['', 'Budi Santoso', 'Tukang', 150000, '081234567890', 'AKTIF', 'Kode kosong = dibuat otomatis'],
            ['MP-001', 'Andi Pratama', 'Mandor', 200000, '', 'AKTIF', 'Kode terisi = data MP-001 diperbarui'],
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public static function rowsFromManPowers(): array
    {
        $rows = [self::HEADERS];

        ManPower::query()
            ->orderBy('code')
            ->get()
            ->each(function (ManPower $manPower) use (&$rows) {
                $rows[] = [
                    $manPower->code,
                    $manPower->name,
                    $manPower->position,
                    (float) $manPower->daily_wage == (int) $manPower->daily_wage
                        ? (int) $manPower->daily_wage
                        : (float) $manPower->daily_wage,
                    (string) ($manPower->phone ?? ''),
                    $manPower->is_active ? 'AKTIF' : 'NONAKTIF',
                    (string) ($manPower->notes ?? ''),
                ];
            });

        return $rows;
    }

    /**
     * @return Collection<int, array{code: string|null, name: string, position: string, daily_wage: string, phone: string|null, is_active: bool|string|null, notes: string|null, _line: int}>
     */
    public static function parse(UploadedFile|string $source): Collection
    {
        $lines = SpreadsheetFile::rows($source);
        $header = array_shift($lines);

        if ($header === null || SpreadsheetFile::rowIsEmpty($header)) {
            throw new InvalidArgumentException('File kosong atau tidak valid.');
        }

        $header = array_map(
            fn ($value) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $value) ?? '')),
            $header
        );

        foreach (['name', 'position', 'daily_wage'] as $column) {
            if (! in_array($column, $header, true)) {
                throw new InvalidArgumentException('Kolom wajib hilang: '.$column.'. Gunakan template yang disediakan.');
            }
        }

        $index = array_flip($header);
        $value = fn (array $data, string $column): ?string => isset($index[$column])
            ? self::nullableTrim($data[$index[$column]] ?? null)
            : null;

        $rows = collect();

        foreach ($lines as $offset => $data) {
            if (SpreadsheetFile::rowIsEmpty($data)) {
                continue;
            }

            $rows->push([
                'code' => $value($data, 'code'),
                'name' => (string) $value($data, 'name'),
                'position' => (string) $value($data, 'position'),
                'daily_wage' => SpreadsheetFile::normalizeNumber((string) $value($data, 'daily_wage')),
                'phone' => $value($data, 'phone'),
                'is_active' => self::normalizeActive($value($data, 'is_active')),
                'notes' => $value($data, 'notes'),
                '_line' => $offset + 2,
            ]);
        }

        if ($rows->isEmpty()) {
            throw new InvalidArgumentException('File tidak berisi baris data.');
        }

        return $rows;
    }

    /**
     * Blank means "keep as is" for existing rows and active for new rows.
     */
    public static function normalizeActive(?string $value): bool|string|null
    {
        if ($value === null) {
            return null;
        }

        return match (strtoupper($value)) {
            'AKTIF', 'ACTIVE', 'YA', 'Y', '1', 'TRUE' => true,
            'NONAKTIF', 'INACTIVE', 'TIDAK', 'N', '0', 'FALSE' => false,
            default => $value,
        };
    }

    private static function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
