<?php

namespace App\Support;

use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Models\ProgressSnapshot;
use App\Models\Project;
use App\Models\SCurveBaseline;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SCurveSeries
{
    /**
     * Cumulative plan, actual, and deviation for one project.
     * Actual on a closed period is the latest snapshot on or before that period ends.
     * The open period uses today's physical progress so it stays aligned with Project Detail.
     * Periods before the first snapshot stay empty. Future periods are not filled.
     *
     * @return array{
     *     display: SCurveGranularity,
     *     weekly_available: bool,
     *     notices: list<string>,
     *     points: list<array{
     *         label: string,
     *         period_start: string,
     *         period_end: string,
     *         p0: float|null,
     *         p1: float|null,
     *         actual: float|null,
     *         deviation: float|null,
     *         timing: string,
     *         actual_source: string|null
     *     }>,
     *     summary: array{
     *         p0: float|null,
     *         p1: float|null,
     *         actual: float|null,
     *         deviation: float|null,
     *         updated_on: string|null,
     *         actual_source: string|null,
     *         history_note: string|null
     *     },
     *     initial: SCurveBaseline|null,
     *     rebaseline: SCurveBaseline|null,
     *     draft: SCurveBaseline|null,
     *     has_schedule: bool
     * }
     */
    public static function for(Project $project, SCurveGranularity $display): array
    {
        $project->loadMissing([
            'sCurveBaselines.periods',
            'sCurveBaselines.itemSchedules',
            'progressSnapshots',
        ]);

        $baselines = $project->sCurveBaselines;
        $established = $baselines->where('status', SCurveBaselineStatus::Established);
        $initial = $established->firstWhere('kind', SCurveKind::Initial);
        $rebaseline = $established
            ->where('kind', SCurveKind::Rebaseline)
            ->sortByDesc('version')
            ->first();
        $draft = $baselines->firstWhere('status', SCurveBaselineStatus::Draft);
        $source = $initial ?? $rebaseline;
        $hasSchedule = $project->start_date !== null
            && $project->target_end_date !== null
            && $project->target_end_date->gte($project->start_date);

        $weeklyAvailable = $source === null
            || $source->granularity === SCurveGranularity::Week
            || $display !== SCurveGranularity::Week;

        $notices = [];

        if (! $hasSchedule) {
            $notices[] = 'Tanggal proyek belum lengkap. Sumbu rencana mengikuti tanggal mulai dan selesai item BOQ.';
        }

        if ($source === null) {
            $notices[] = 'Isi tanggal mulai dan selesai setiap item BOQ untuk membentuk rencana awal.';
        } elseif (! $weeklyAvailable) {
            $notices[] = 'Rencana ini disimpan per bulan. Tampilan mingguan tidak dipecah dari data bulanan.';
        }

        $live = $project->progressPercentage();

        if ($project->totalCost() <= 0) {
            $notices[] = 'Belum ada BOQ berstatus APPROVED. Progress aktual mengikuti rumus project dan saat ini 0%.';
        }

        $points = [];

        if ($source !== null && $weeklyAvailable) {
            $p0Rows = $initial !== null ? self::rows($initial, $display) : [];
            $p1Rows = $rebaseline !== null ? self::rows($rebaseline, $display) : [];
            $axis = $p0Rows !== [] ? $p0Rows : $p1Rows;
            $p0ByKey = collect($p0Rows)->keyBy('key');
            $p1ByKey = collect($p1Rows)->keyBy('key');
            $snapshots = $project->progressSnapshots->sortBy(fn (ProgressSnapshot $snapshot) => $snapshot->snapshot_date->toDateString())->values();
            $today = today()->startOfDay();

            foreach ($axis as $row) {
                $p0Row = $p0ByKey->get($row['key']);
                $p1Row = $p1ByKey->get($row['key']);
                $p0 = is_array($p0Row) ? $p0Row['cumulative'] : null;
                $p1 = is_array($p1Row) ? $p1Row['cumulative'] : null;
                $actual = self::actual($row['start'], $row['end'], $snapshots, $live, $today);
                $deviation = $actual['value'] !== null && $p1 !== null
                    ? round($actual['value'] - $p1, 2)
                    : null;

                $points[] = [
                    'label' => $row['label'],
                    'period_start' => $row['start']->toDateString(),
                    'period_end' => $row['end']->toDateString(),
                    'p0' => $p0,
                    'p1' => $p1,
                    'actual' => $actual['value'],
                    'deviation' => $deviation,
                    'timing' => self::timing($row['start'], $row['end'], $today),
                    'actual_source' => $actual['source'],
                ];
            }
        }

        if ($rebaseline === null && $points !== []) {
            $notices[] = 'Deviasi belum dihitung karena rebaseline belum ditetapkan.';
        }

        return [
            'display' => $display,
            'weekly_available' => $weeklyAvailable,
            'notices' => $notices,
            'points' => $points,
            'summary' => self::summaryWithoutPlan($points, $project, $source, $live),
            'initial' => $initial,
            'rebaseline' => $rebaseline,
            'draft' => $draft,
            'history' => $baselines->sortBy('version')->values(),
            'has_schedule' => $hasSchedule,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array{
     *     p0: float|null,
     *     p1: float|null,
     *     actual: float|null,
     *     deviation: float|null,
     *     updated_on: string|null,
     *     actual_source: string|null,
     *     history_note: string|null
     * }
     */
    private static function summaryWithoutPlan(array $points, Project $project, ?SCurveBaseline $source, float $live): array
    {
        $summary = self::summary($points, $project);

        if ($points === [] && $source === null) {
            $summary['actual'] = round($live, 2);
            $summary['actual_source'] = 'live';
            $summary['history_note'] = 'Angka aktual mengikuti progress saat ini. Grafik baru tampil setelah rencana ditetapkan.';
        }

        return $summary;
    }

    /**
     * @param  list<array<string, mixed>>  $points
     * @return array{
     *     p0: float|null,
     *     p1: float|null,
     *     actual: float|null,
     *     deviation: float|null,
     *     updated_on: string|null,
     *     actual_source: string|null,
     *     history_note: string|null
     * }
     */
    private static function summary(array $points, Project $project): array
    {
        $current = collect($points)->firstWhere('timing', 'in_progress')
            ?? collect($points)->reverse()->firstWhere('timing', 'finished');

        $latestSnapshot = $project->progressSnapshots->sortBy(fn (ProgressSnapshot $snapshot) => $snapshot->snapshot_date->toDateString())->last();
        $updatedOn = $latestSnapshot?->snapshot_date->toDateString();
        $source = $current['actual_source'] ?? null;

        $historyNote = match (true) {
            $current === null => null,
            $source === 'snapshot' && $updatedOn !== null => 'Data aktual diperbarui '.date_id($updatedOn).'.',
            $source === 'live' && $updatedOn !== null => 'Periode berjalan memakai progress saat ini. Histori terakhir '.date_id($updatedOn).'.',
            $source === 'live' => 'Periode berjalan memakai progress saat ini. Histori mulai tercatat saat progress disimpan.',
            default => 'Periode ini belum memiliki data aktual.',
        };

        return [
            'p0' => $current['p0'] ?? null,
            'p1' => $current['p1'] ?? null,
            'actual' => $current['actual'] ?? null,
            'deviation' => $current['deviation'] ?? null,
            'updated_on' => $updatedOn,
            'actual_source' => $source,
            'history_note' => $historyNote,
        ];
    }

    /**
     * @param  Collection<int, ProgressSnapshot>  $snapshots
     * @return array{value: float|null, source: string|null}
     */
    private static function actual(CarbonInterface $start, CarbonInterface $end, $snapshots, float $live, CarbonInterface $today): array
    {
        if ($start->gt($today)) {
            return ['value' => null, 'source' => null];
        }

        if ($start->lte($today) && $end->gte($today)) {
            $todaySnapshot = $snapshots->first(fn (ProgressSnapshot $snapshot) => $snapshot->snapshot_date->isSameDay($today));

            if ($todaySnapshot !== null) {
                return ['value' => round((float) $todaySnapshot->progress_percentage, 2), 'source' => 'snapshot'];
            }

            return ['value' => round($live, 2), 'source' => 'live'];
        }

        $snapshot = $snapshots->last(fn (ProgressSnapshot $snapshot) => $snapshot->snapshot_date->lte($end));

        if ($snapshot === null) {
            return ['value' => null, 'source' => null];
        }

        return ['value' => round((float) $snapshot->progress_percentage, 2), 'source' => 'snapshot'];
    }

    private static function timing(CarbonInterface $start, CarbonInterface $end, CarbonInterface $today): string
    {
        if ($start->gt($today)) {
            return 'not_started';
        }

        if ($end->lt($today)) {
            return 'finished';
        }

        return 'in_progress';
    }

    /**
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon, cumulative: float}>
     */
    private static function rows(SCurveBaseline $baseline, SCurveGranularity $display): array
    {
        $running = 0.0;
        $weekly = [];

        foreach ($baseline->periods->sortBy(fn ($period) => $period->period_start->toDateString()) as $period) {
            $running = round($running + (float) $period->planned_percent, 4);
            $start = $period->period_start->copy()->startOfDay();
            $end = $period->period_end->copy()->startOfDay();
            $weekly[] = [
                'key' => $baseline->granularity === SCurveGranularity::Month
                    ? $end->format('Y-m')
                    : $start->toDateString(),
                'label' => $start->format('d/m/Y'),
                'start' => $start,
                'end' => $end,
                'planned' => (float) $period->planned_percent,
                'cumulative' => round($running, 2),
                'month' => $end->format('Y-m'),
            ];
        }

        if ($display === SCurveGranularity::Month && $baseline->granularity === SCurveGranularity::Week) {
            return self::rollUpMonths($weekly);
        }

        return array_map(fn (array $row) => [
            'key' => $row['key'],
            'label' => $row['label'],
            'start' => $row['start'],
            'end' => $row['end'],
            'cumulative' => $row['cumulative'],
        ], $weekly);
    }

    /**
     * @param  list<array{start: Carbon, end: Carbon, planned: float, month: string}>  $weekly
     * @return list<array{key: string, label: string, start: Carbon, end: Carbon, cumulative: float}>
     */
    private static function rollUpMonths(array $weekly): array
    {
        $groups = [];

        foreach ($weekly as $row) {
            $key = $row['month'];

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'start' => $row['start']->copy(),
                    'end' => $row['end']->copy(),
                    'planned' => 0.0,
                ];
            }

            $groups[$key]['end'] = $row['end']->copy();
            $groups[$key]['planned'] += $row['planned'];
        }

        $rolled = [];
        $running = 0.0;

        foreach ($groups as $key => $group) {
            $running = round($running + $group['planned'], 4);
            $rolled[] = [
                'key' => $key,
                'label' => Carbon::parse($key.'-01')->format('d/m/Y'),
                'start' => $group['start'],
                'end' => $group['end'],
                'cumulative' => round($running, 2),
            ];
        }

        return $rolled;
    }
}
