<?php

namespace App\Actions\SCurve;

use App\Enums\SCurveBaselineStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SCurveBaseline;
use App\Models\SCurvePeriod;
use App\Models\User;
use App\Support\SCurvePeriods;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SaveSCurveBaseline
{
    /**
     * @param  array<int|string, float|int|string>  $plannedPercents
     */
    public function handle(User $actor, SCurveBaseline $baseline, array $plannedPercents, ?string $reason, bool $establish): SCurveBaseline
    {
        if ($baseline->isEstablished()) {
            throw BusinessRuleException::make('Rencana yang sudah ditetapkan tidak dapat diubah. Buat rebaseline untuk versi baru.');
        }

        $baseline->loadMissing('periods');
        $periods = $baseline->periods->keyBy('id');

        if ($periods->isEmpty()) {
            throw BusinessRuleException::make('Rencana ini belum memiliki periode.');
        }

        $normalized = [];

        foreach ($periods as $period) {
            if (! array_key_exists($period->id, $plannedPercents) && ! array_key_exists((string) $period->id, $plannedPercents)) {
                throw ValidationException::withMessages([
                    'periods' => 'Setiap periode wajib diisi.',
                ]);
            }

            $value = $plannedPercents[$period->id] ?? $plannedPercents[(string) $period->id];
            $validated = Validator::make(
                ['planned_percent' => $value],
                ['planned_percent' => ['required', 'numeric', 'min:0', 'max:100']],
                [
                    'planned_percent.required' => 'Persen rencana wajib diisi.',
                    'planned_percent.numeric' => 'Persen rencana harus berupa angka.',
                    'planned_percent.min' => 'Persen rencana berada di antara 0 dan 100.',
                    'planned_percent.max' => 'Persen rencana berada di antara 0 dan 100.',
                ]
            );

            if ($validated->fails()) {
                throw ValidationException::withMessages([
                    'periods' => $validated->errors()->first('planned_percent'),
                ]);
            }

            $normalized[$period->id] = round((float) $validated->validated()['planned_percent'], 4);
        }

        $sum = round(array_sum($normalized), 4);

        if ($establish && ! SCurvePeriods::sumIsComplete($sum)) {
            throw ValidationException::withMessages([
                'periods' => 'Jumlah rencana harus 100%. Saat ini '.number_format($sum, 2, '.', '').'%.',
            ]);
        }

        return DB::transaction(function () use ($baseline, $periods, $normalized, $reason, $establish, $actor): SCurveBaseline {
            foreach ($periods as $period) {
                $period->planned_percent = $normalized[$period->id];
                $period->save();
            }

            $baseline->reason = $reason;
            $baseline->created_by = $baseline->created_by ?? $actor->id;

            if ($establish) {
                $first = $periods->sortBy(fn (SCurvePeriod $period) => $period->period_start->toDateString())->first();
                $baseline->status = SCurveBaselineStatus::Established;
                $baseline->established_on = today()->toDateString();
                $baseline->effective_from = $first?->period_start->toDateString();
            }

            $baseline->save();

            return $baseline->refresh()->load('periods');
        });
    }
}
