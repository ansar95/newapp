<?php

namespace App\Support;

use App\Enums\ClientPaymentStatus;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermStatus;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\Project;
use App\Models\ProjectTerm;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only client billing figures of a project. Every amount is derived from billing and payment records:
 * billed = SENT billings, received = RECEIVED payments, outstanding = billed - received.
 */
class ProjectBillingSummary
{
    /**
     * @param  Collection<int, ProjectTerm>  $terms
     * @param  Collection<int, ClientBilling>  $billings
     */
    private function __construct(
        public readonly Project $project,
        public readonly Collection $terms,
        public readonly Collection $billings,
    ) {}

    public static function forProject(Project $project): self
    {
        $terms = $project->terms()->get();
        $billings = $project->clientBillings()
            ->with(['payments', 'term'])
            ->orderBy('billing_date')
            ->orderBy('id')
            ->get();

        return new self($project, $terms, $billings);
    }

    public function contractValue(): float
    {
        return round((float) ($this->project->contract_value ?? 0), 2);
    }

    /**
     * @return Collection<int, ProjectTerm>
     */
    public function activeTerms(): Collection
    {
        return $this->terms->reject(fn (ProjectTerm $term) => $term->isCancelled())->values();
    }

    public function totalTermAmount(): float
    {
        return $this->money($this->activeTerms()->sum(fn (ProjectTerm $term) => self::cents($term->amount)));
    }

    public function totalTermPercentage(): float
    {
        return ProjectActualCost::percentage($this->contractValue(), $this->totalTermAmount());
    }

    /**
     * @return Collection<int, ClientBilling>
     */
    public function billingsForTerm(ProjectTerm $term): Collection
    {
        return $this->billings->where('project_term_id', $term->id)->values();
    }

    /**
     * Amount already sent to the client for this term.
     */
    public function termBilled(ProjectTerm $term): float
    {
        return $this->money($this->billingsForTerm($term)
            ->filter(fn (ClientBilling $billing) => $billing->status->countsAsBilled())
            ->sum(fn (ClientBilling $billing) => self::cents($billing->billing_amount)));
    }

    /**
     * Amount held by every non-cancelled billing (including drafts) so a term is never billed twice.
     */
    public function termReserved(ProjectTerm $term): float
    {
        return $this->money($this->billingsForTerm($term)
            ->filter(fn (ClientBilling $billing) => $billing->status->reservesTermAmount())
            ->sum(fn (ClientBilling $billing) => self::cents($billing->billing_amount)));
    }

    public function termReceived(ProjectTerm $term): float
    {
        return $this->money($this->billingsForTerm($term)
            ->sum(fn (ClientBilling $billing) => $this->billingReceivedCents($billing)));
    }

    public function termRemaining(ProjectTerm $term): float
    {
        return round(max(0, (float) $term->amount - $this->termReserved($term)), 2);
    }

    /**
     * Active terms whose billing condition is met but still have an amount left to bill.
     *
     * @return Collection<int, ProjectTerm>
     */
    public function dueTerms(float $projectProgress, ?CarbonInterface $today = null): Collection
    {
        return $this->activeTerms()
            ->filter(fn (ProjectTerm $term) => $term->isTriggerReached($projectProgress, $today) && $this->termRemaining($term) > 0)
            ->values();
    }

    public function termOutstanding(ProjectTerm $term): float
    {
        return round(max(0, $this->termBilled($term) - $this->termReceived($term)), 2);
    }

    public function termStatus(ProjectTerm $term): ProjectTermStatus
    {
        if ($term->isCancelled()) {
            return ProjectTermStatus::Cancelled;
        }

        $amountCents = self::cents($term->amount);
        $billedCents = self::cents($this->termBilled($term));
        $receivedCents = self::cents($this->termReceived($term));

        return match (true) {
            $amountCents > 0 && $receivedCents >= $amountCents => ProjectTermStatus::Paid,
            $amountCents > 0 && $billedCents >= $amountCents && $receivedCents > 0 => ProjectTermStatus::PartiallyPaid,
            $amountCents > 0 && $billedCents >= $amountCents => ProjectTermStatus::FullyBilled,
            $billedCents > 0 => ProjectTermStatus::PartiallyBilled,
            $term->readiness === ProjectTermReadiness::Ready => ProjectTermStatus::Ready,
            default => ProjectTermStatus::NotReady,
        };
    }

    /**
     * @return Collection<int, ClientBilling>
     */
    public function sentBillings(): Collection
    {
        return $this->billings->filter(fn (ClientBilling $billing) => $billing->status->countsAsBilled())->values();
    }

    public function billingCount(): int
    {
        return $this->sentBillings()->count();
    }

    public function totalBilled(): float
    {
        return $this->money($this->sentBillings()->sum(fn (ClientBilling $billing) => self::cents($billing->billing_amount)));
    }

    public function totalReceived(): float
    {
        return $this->money($this->billings->sum(fn (ClientBilling $billing) => $this->billingReceivedCents($billing)));
    }

    public function outstanding(): float
    {
        return round(max(0, $this->totalBilled() - $this->totalReceived()), 2);
    }

    /**
     * Billings being prepared (DRAFT / SUBMITTED): not billed yet, but already holding term value.
     */
    public function inProgressAmount(): float
    {
        return $this->money($this->billings
            ->filter(fn (ClientBilling $billing) => $billing->status->reservesTermAmount() && ! $billing->status->countsAsBilled())
            ->sum(fn (ClientBilling $billing) => self::cents($billing->billing_amount)));
    }

    public function billedPercentage(): float
    {
        return ProjectActualCost::percentage($this->contractValue(), $this->totalBilled());
    }

    public function receivedPercentage(): float
    {
        return ProjectActualCost::percentage($this->contractValue(), $this->totalReceived());
    }

    private function billingReceivedCents(ClientBilling $billing): int
    {
        return $billing->payments
            ->filter(fn (ClientPayment $payment) => $payment->status === ClientPaymentStatus::Received)
            ->sum(fn (ClientPayment $payment) => self::cents($payment->amount));
    }

    public static function cents(float|int|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function money(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
