<?php

namespace App\Actions\SCurve;

use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Models\User;
use App\Support\SCurvePeriods;
use Illuminate\Support\Facades\DB;

class PrepareSCurveBaseline
{
    public function handle(User $actor, Project $project, SCurveKind $kind, ?SCurveGranularity $granularity, ?string $reason = null): SCurveBaseline
    {
        if ($project->start_date === null || $project->target_end_date === null) {
            throw BusinessRuleException::make('Tanggal mulai dan selesai proyek perlu diisi sebelum rencana Kurva S dapat disiapkan.');
        }

        return DB::transaction(function () use ($actor, $project, $kind, $granularity, $reason): SCurveBaseline {
            $project->load('sCurveBaselines.periods');

            if ($project->sCurveBaselines->contains(fn (SCurveBaseline $baseline) => $baseline->status === SCurveBaselineStatus::Draft)) {
                throw BusinessRuleException::make('Selesaikan draf rencana yang masih terbuka sebelum membuat rencana baru.');
            }

            $initial = $project->sCurveBaselines->first(
                fn (SCurveBaseline $baseline) => $baseline->kind === SCurveKind::Initial && $baseline->isEstablished()
            );

            if ($kind === SCurveKind::Initial) {
                if ($initial !== null) {
                    throw BusinessRuleException::make('Rencana awal sudah ditetapkan dan tidak diubah. Buat rebaseline untuk rencana baru.');
                }

                if ($granularity === null) {
                    throw BusinessRuleException::make('Pilih periode mingguan atau bulanan.');
                }

                $periods = SCurvePeriods::between($project->start_date, $project->target_end_date, $granularity);
                $version = 1;
                $copied = array_fill(0, count($periods), 0);
            } else {
                if ($initial === null) {
                    throw BusinessRuleException::make('Rebaseline dapat dibuat setelah rencana awal ditetapkan.');
                }

                $granularity = $initial->granularity;
                $periods = $initial->periods
                    ->sortBy(fn ($period) => $period->period_start->toDateString())
                    ->map(fn ($period) => [
                        'period_start' => $period->period_start->toDateString(),
                        'period_end' => $period->period_end->toDateString(),
                    ])
                    ->values()
                    ->all();
                $copied = $initial->periods
                    ->sortBy(fn ($period) => $period->period_start->toDateString())
                    ->map(fn ($period) => (float) $period->planned_percent)
                    ->values()
                    ->all();
                $version = ((int) $project->sCurveBaselines->max('version')) + 1;
            }

            $baseline = SCurveBaseline::query()->create([
                'project_id' => $project->id,
                'version' => $version,
                'kind' => $kind,
                'status' => SCurveBaselineStatus::Draft,
                'granularity' => $granularity,
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            foreach ($periods as $index => $period) {
                $baseline->periods()->create([
                    'period_start' => $period['period_start'],
                    'period_end' => $period['period_end'],
                    'planned_percent' => $copied[$index] ?? 0,
                ]);
            }

            return $baseline->load('periods');
        });
    }
}
