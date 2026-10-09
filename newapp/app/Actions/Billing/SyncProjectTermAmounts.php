<?php

namespace App\Actions\Billing;

use App\Enums\ClientBillingStatus;
use App\Enums\ProjectTermReadiness;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientBilling;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Support\ProjectBillingSummary;
use Illuminate\Support\Collection;

class SyncProjectTermAmounts
{
    private const FULL_PERCENTAGE_UNITS = 1_000_000;

    /**
     * Recalculate every active term against the contract value, validate the totals, and persist.
     * Percentage terms follow the contract; when percentages total exactly 100% the last term absorbs rounding.
     * Must run inside a transaction.
     *
     * @param  ProjectTerm|null  $pending  a new or edited term that is not saved yet
     * @param  int|null  $removedTermId  a term that is being deleted or cancelled
     */
    public function handle(Project $project, float|int|string|null $contractValue, ?ProjectTerm $pending = null, ?int $removedTermId = null): void
    {
        $terms = ProjectTerm::query()
            ->where('project_id', $project->id)
            ->where('readiness', '!=', ProjectTermReadiness::Cancelled)
            ->when($removedTermId !== null, fn ($query) => $query->whereKeyNot($removedTermId))
            ->orderBy('term_number')
            ->lockForUpdate()
            ->get();

        if ($pending !== null) {
            $terms = $terms->reject(fn (ProjectTerm $term) => $pending->exists && $term->id === $pending->id)
                ->push($pending)
                ->sortBy('term_number')
                ->values();
        }

        if ($terms->isEmpty()) {
            return;
        }

        $contractCents = ProjectBillingSummary::cents($contractValue);

        if ($contractCents <= 0) {
            throw BusinessRuleException::make('Nilai kontrak wajib diisi karena project sudah memiliki termin.');
        }

        $percentageUnits = $terms
            ->filter(fn (ProjectTerm $term) => $term->isPercentage())
            ->sum(fn (ProjectTerm $term) => self::percentageUnits($term->percentage));

        if ($percentageUnits > self::FULL_PERCENTAGE_UNITS) {
            throw BusinessRuleException::make(BillingMessages::TERM_PERCENTAGE_EXCEEDS);
        }

        $amounts = $this->amountsInCents($terms, $contractCents, $percentageUnits);

        if (array_sum($amounts) > $contractCents) {
            throw BusinessRuleException::make(BillingMessages::TERM_AMOUNT_EXCEEDS);
        }

        $this->assertNotBelowBilled($terms, $amounts);

        foreach ($terms as $index => $term) {
            $term->amount = number_format($amounts[$index] / 100, 2, '.', '');

            if ($term->isDirty() || ! $term->exists) {
                $term->save();
            }
        }
    }

    /**
     * @param  Collection<int, ProjectTerm>  $terms
     * @return array<int, int>
     */
    private function amountsInCents(Collection $terms, int $contractCents, int $percentageUnits): array
    {
        $amounts = $terms->map(fn (ProjectTerm $term) => $term->isPercentage()
            ? (int) round($contractCents * self::percentageUnits($term->percentage) / self::FULL_PERCENTAGE_UNITS)
            : ProjectBillingSummary::cents($term->amount))
            ->all();

        $allPercentage = $terms->every(fn (ProjectTerm $term) => $term->isPercentage());

        if ($allPercentage && $percentageUnits === self::FULL_PERCENTAGE_UNITS) {
            $lastIndex = array_key_last($amounts);
            $amounts[$lastIndex] = $contractCents - (array_sum($amounts) - $amounts[$lastIndex]);
        }

        return $amounts;
    }

    /**
     * @param  Collection<int, ProjectTerm>  $terms
     * @param  array<int, int>  $amounts
     */
    private function assertNotBelowBilled(Collection $terms, array $amounts): void
    {
        $existingIds = $terms->filter(fn (ProjectTerm $term) => $term->exists)->pluck('id');

        if ($existingIds->isEmpty()) {
            return;
        }

        $reserved = ClientBilling::query()
            ->whereIn('project_term_id', $existingIds)
            ->where('status', '!=', ClientBillingStatus::Cancelled->value)
            ->groupBy('project_term_id')
            ->selectRaw('project_term_id, SUM(billing_amount) as reserved_amount')
            ->pluck('reserved_amount', 'project_term_id');

        foreach ($terms as $index => $term) {
            $reservedCents = ProjectBillingSummary::cents($reserved[$term->id] ?? 0);

            if ($term->exists && $amounts[$index] < $reservedCents) {
                throw BusinessRuleException::make(
                    'Nilai '.$term->displayName().' tidak boleh lebih kecil dari nilai yang sudah ditagihkan ('.rupiah($reservedCents / 100).').'
                );
            }
        }
    }

    private static function percentageUnits(float|int|string|null $percentage): int
    {
        return (int) round((float) $percentage * 10000);
    }
}
