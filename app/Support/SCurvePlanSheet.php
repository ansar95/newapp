<?php

namespace App\Support;

use App\Models\BoqItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Excel rows for the Kurva S item-date plan. Weight stays on the BOQ; the file only carries dates.
 */
final class SCurvePlanSheet
{
    /**
     * @var list<string>
     */
    public const HEADERS = [
        'item_code',
        'cogs_name',
        'item_name',
        'planned_start',
        'planned_finish',
    ];

    /**
     * @param  list<array{cogs: string, items: list<array{code: string, name: string, start: string|null, finish: string|null}>}>  $groups
     * @return list<list<string>>
     */
    public static function rows(array $groups): array
    {
        $rows = [self::HEADERS];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $rows[] = [
                    $item['code'],
                    $group['cogs'],
                    $item['name'],
                    filled($item['start']) ? Format::date($item['start']) : '',
                    filled($item['finish']) ? Format::date($item['finish']) : '',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  Collection<int, BoqItem>  $items
     * @return array<int, array{start: string, finish: string}>
     */
    public static function dates(array $rows, Collection $items): array
    {
        $header = null;
        $headerIndex = null;

        foreach ($rows as $index => $row) {
            if (SpreadsheetFile::rowIsEmpty($row)) {
                continue;
            }

            $header = array_map(fn ($value) => strtolower(trim((string) $value)), $row);
            $headerIndex = $index;
            break;
        }

        foreach (['item_code', 'planned_start', 'planned_finish'] as $column) {
            if ($header === null || ! in_array($column, $header, true)) {
                throw new InvalidArgumentException('Kolom wajib hilang: '.$column.'. Gunakan template yang disediakan.');
            }
        }

        $codeColumn = array_search('item_code', $header, true);
        $startColumn = array_search('planned_start', $header, true);
        $finishColumn = array_search('planned_finish', $header, true);
        $byCode = $items->keyBy(fn (BoqItem $item) => strtoupper(trim($item->code)));
        $dates = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            if ($headerIndex === $index || SpreadsheetFile::rowIsEmpty($row)) {
                continue;
            }

            $line = $index + 1;
            $code = strtoupper(trim((string) ($row[$codeColumn] ?? '')));

            if ($code === '') {
                throw new InvalidArgumentException('Baris '.$line.': kode item wajib diisi.');
            }

            $item = $byCode->get($code);

            if ($item === null) {
                throw new InvalidArgumentException('Baris '.$line.': kode item '.$code.' tidak ada pada BOQ approved.');
            }

            if (isset($seen[$code])) {
                throw new InvalidArgumentException('Baris '.$line.': kode item '.$code.' ditulis lebih dari sekali.');
            }

            $seen[$code] = true;
            $start = self::dateCell((string) ($row[$startColumn] ?? ''));
            $finish = self::dateCell((string) ($row[$finishColumn] ?? ''));

            if ($start === '') {
                throw new InvalidArgumentException('Baris '.$line.': tanggal mulai harus berformat dd/mm/yyyy.');
            }

            if ($finish === '') {
                throw new InvalidArgumentException('Baris '.$line.': tanggal selesai harus berformat dd/mm/yyyy.');
            }

            $dates[$item->id] = ['start' => $start, 'finish' => $finish];
        }

        foreach ($items as $item) {
            if (! isset($dates[$item->id])) {
                throw new InvalidArgumentException('Item '.$item->code.' belum ada di file.');
            }
        }

        return $dates;
    }

    private static function dateCell(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $iso = Format::isoDate($value);

        if ($iso !== '' && self::isRealDate($iso)) {
            return $iso;
        }

        if (preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $serial = (int) floor((float) $value);

            if ($serial > 20000 && $serial < 80000) {
                $base = Carbon::create(1899, 12, 30);

                if ($base === false || $base === null) {
                    return '';
                }

                $iso = $base->addDays($serial)->format('Y-m-d');

                if (self::isRealDate($iso)) {
                    return $iso;
                }
            }
        }

        return '';
    }

    private static function isRealDate(string $iso): bool
    {
        $parsed = Carbon::createFromFormat('!Y-m-d', $iso);

        return $parsed !== false && $parsed->format('Y-m-d') === $iso;
    }
}
