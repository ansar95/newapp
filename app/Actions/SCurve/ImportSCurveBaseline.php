<?php

namespace App\Actions\SCurve;

use App\Models\SCurveBaseline;
use App\Models\User;
use App\Support\SpreadsheetFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportSCurveBaseline
{
    public function __construct(private SaveSCurveBaseline $saveSCurveBaseline) {}

    /**
     * Replaces planned percents on a draft from period_start and planned_percent, then establishes it when the total is 100%.
     */
    public function handle(User $actor, SCurveBaseline $baseline, mixed $file): SCurveBaseline
    {
        Validator::make(
            ['file' => $file],
            ['file' => SpreadsheetFile::UPLOAD_RULES],
            SpreadsheetFile::uploadMessages(),
        )->validate();

        $baseline->loadMissing('periods');

        try {
            $rows = SpreadsheetFile::rows($file);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $planned = $this->plannedPercents($baseline, $rows);

        return $this->saveSCurveBaseline->handle($actor, $baseline, $planned, $baseline->reason, true);
    }

    /**
     * @param  list<list<string>>  $rows
     * @return array<int, float>
     */
    private function plannedPercents(SCurveBaseline $baseline, array $rows): array
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

        if ($header === null || ! in_array('period_start', $header, true) || ! in_array('planned_percent', $header, true)) {
            throw ValidationException::withMessages([
                'file' => 'File harus memiliki kolom period_start dan planned_percent.',
            ]);
        }

        $startColumn = array_search('period_start', $header, true);
        $percentColumn = array_search('planned_percent', $header, true);
        $byDate = [];

        foreach ($rows as $index => $row) {
            if ($headerIndex === $index || SpreadsheetFile::rowIsEmpty($row)) {
                continue;
            }

            $line = $index + 1;
            $date = trim((string) ($row[$startColumn] ?? ''));
            $percent = SpreadsheetFile::normalizeNumber((string) ($row[$percentColumn] ?? ''));

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
                throw ValidationException::withMessages([
                    'file' => 'Baris '.$line.': tanggal periode harus berformat YYYY-MM-DD.',
                ]);
            }

            if (isset($byDate[$date])) {
                throw ValidationException::withMessages([
                    'file' => 'Baris '.$line.': tanggal '.$date.' muncul lebih dari sekali.',
                ]);
            }

            if (! is_numeric($percent)) {
                throw ValidationException::withMessages([
                    'file' => 'Baris '.$line.': persen rencana harus berupa angka.',
                ]);
            }

            $byDate[$date] = (float) $percent;
        }

        $planned = [];

        foreach ($baseline->periods as $period) {
            $date = $period->period_start->toDateString();

            if (! array_key_exists($date, $byDate)) {
                throw ValidationException::withMessages([
                    'file' => 'Periode '.$date.' tidak ada di file.',
                ]);
            }

            $planned[$period->id] = $byDate[$date];
            unset($byDate[$date]);
        }

        if ($byDate !== []) {
            throw ValidationException::withMessages([
                'file' => 'File berisi tanggal yang tidak ada pada rencana ini: '.implode(', ', array_keys($byDate)).'.',
            ]);
        }

        return $planned;
    }
}
