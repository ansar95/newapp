<?php

namespace App\Actions\SCurve;

use App\Enums\SCurveBaselineStatus;
use App\Enums\SCurveGranularity;
use App\Enums\SCurveKind;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\SCurveBaseline;
use App\Models\User;
use App\Support\SCurveItemPlan;
use App\Support\SCurvePeriods;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EstablishSCurveFromItems
{
    /**
     * Builds a baseline by spreading each approved BOQ item's value weight across the dates entered for that item.
     * Actual progress is not read here and is not written into the plan.
     *
     * @param  array<int|string, array{start: mixed, finish: mixed}>  $dates
     */
    public function handle(User $actor, Project $project, SCurveKind $kind, array $dates, ?string $reason = null): SCurveBaseline
    {
        $project->load('approvedBoqs.cogs.items', 'sCurveBaselines');
        $items = $project->approvedBoqs->flatMap(fn ($boq) => $boq->cogs->flatMap->items);
        $total = $project->totalCost();

        if ($items->isEmpty() || $total <= 0) {
            throw BusinessRuleException::make('Rencana awal membutuhkan BOQ berstatus APPROVED yang memiliki nilai.');
        }

        $parsed = $this->datesForEveryItem($items, $dates);

        return DB::transaction(function () use ($actor, $project, $kind, $parsed, $items, $total, $reason): SCurveBaseline {
            $project->load('sCurveBaselines');

            if ($project->sCurveBaselines->contains(fn (SCurveBaseline $baseline) => $baseline->status === SCurveBaselineStatus::Draft)) {
                throw BusinessRuleException::make('Selesaikan draf rencana yang masih terbuka sebelum membuat rencana baru.');
            }

            $initial = $project->sCurveBaselines->first(
                fn (SCurveBaseline $baseline) => $baseline->kind === SCurveKind::Initial && $baseline->isEstablished()
            );

            if ($kind === SCurveKind::Initial && $initial !== null) {
                throw BusinessRuleException::make('Rencana awal sudah ditetapkan dan tidak diubah. Buat rebaseline untuk rencana baru.');
            }

            if ($kind === SCurveKind::Rebaseline && $initial === null) {
                throw BusinessRuleException::make('Rebaseline dapat dibuat setelah rencana awal ditetapkan.');
            }

            $planItems = $items->map(function (BoqItem $item) use ($parsed, $total) {
                return [
                    'start' => $parsed[$item->id]['start'],
                    'finish' => $parsed[$item->id]['finish'],
                    'weight' => ((float) $item->total_price / $total) * 100,
                ];
            })->all();

            $periods = SCurveItemPlan::weekly($planItems);

            if (! SCurvePeriods::sumIsComplete(array_sum(array_column($periods, 'planned_percent')))) {
                throw BusinessRuleException::make('Sebaran bobot item tidak mencapai 100%. Periksa tanggal dan nilai BOQ.');
            }

            $version = $kind === SCurveKind::Initial
                ? 1
                : ((int) $project->sCurveBaselines->max('version')) + 1;

            $baseline = SCurveBaseline::query()->create([
                'project_id' => $project->id,
                'version' => $version,
                'kind' => $kind,
                'status' => SCurveBaselineStatus::Established,
                'granularity' => SCurveGranularity::Week,
                'established_on' => today()->toDateString(),
                'effective_from' => $periods[0]['period_start'],
                'reason' => $reason,
                'created_by' => $actor->id,
            ]);

            foreach ($periods as $period) {
                $baseline->periods()->create($period);
            }

            foreach ($items as $item) {
                $baseline->itemSchedules()->create([
                    'boq_item_id' => $item->id,
                    'planned_start' => $parsed[$item->id]['start']->toDateString(),
                    'planned_finish' => $parsed[$item->id]['finish']->toDateString(),
                ]);
            }

            return $baseline->load('periods', 'itemSchedules');
        });
    }

    /**
     * @param  Collection<int, BoqItem>  $items
     * @param  array<int|string, array{start: mixed, finish: mixed}>  $dates
     * @return array<int, array{start: Carbon, finish: Carbon}>
     */
    private function datesForEveryItem($items, array $dates): array
    {
        $parsed = [];

        foreach ($items as $item) {
            $row = $dates[$item->id] ?? $dates[(string) $item->id] ?? null;
            $start = $row['start'] ?? null;
            $finish = $row['finish'] ?? null;

            if ($start === null || $start === '' || $finish === null || $finish === '') {
                throw ValidationException::withMessages([
                    'items' => 'Setiap item BOQ wajib diisi tanggal mulai dan selesai.',
                ]);
            }

            $startDate = Carbon::parse($start)->startOfDay();
            $finishDate = Carbon::parse($finish)->startOfDay();

            if ($finishDate->lt($startDate)) {
                throw ValidationException::withMessages([
                    'items' => 'Tanggal selesai '.$item->code.' harus sama atau setelah tanggal mulai.',
                ]);
            }

            $parsed[$item->id] = ['start' => $startDate, 'finish' => $finishDate];
        }

        foreach ($dates as $itemId => $row) {
            if (! array_key_exists((int) $itemId, $parsed)) {
                throw ValidationException::withMessages([
                    'items' => 'Item tidak termasuk BOQ approved proyek ini.',
                ]);
            }
        }

        return $parsed;
    }
}
