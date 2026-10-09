<?php

namespace App\Actions\Payment;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BoqItem;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;
use App\Support\ProjectActualCost;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProcessPayment
{
    /**
     * The paid amount is the real actual cost: it may differ from the plan and may exceed the BOQ,
     * but it must be fully allocated to BOQ items of the plan's BOQ.
     * When allocations are omitted and the amount equals the plan, the plan's item amounts are used.
     *
     * @param  array{
     *     payment_code: string,
     *     payment_date: string,
     *     amount: float|int|string,
     *     payment_method?: string|null,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     overrun_reason?: string|null,
     *     allocations?: list<array{boq_item_id: int|string, allocated_amount: float|int|string, notes?: string|null}>
     * }  $data
     */
    public function handle(User $actor, PaymentPlan $paymentPlan, array $data): Payment
    {
        $paymentPlan->loadMissing('boq.cogs.items', 'boq.project', 'payments', 'items');

        if ($paymentPlan->boq?->status !== BoqStatus::Approved) {
            throw BusinessRuleException::make(
                'Payment tidak dapat diproses karena BOQ belum APPROVED.'
            );
        }

        if ($paymentPlan->status === PaymentPlanStatus::Paid || $paymentPlan->activePayment() !== null) {
            throw BusinessRuleException::make(
                'Payment Plan tidak boleh dibayar dua kali.'
            );
        }

        if ($paymentPlan->status !== PaymentPlanStatus::ReadyToPay) {
            throw BusinessRuleException::make(
                'Payment hanya dapat diproses ketika Payment Plan berstatus READY_TO_PAY.'
            );
        }

        $validated = Validator::make($data, [
            'payment_code' => ['required', 'string', 'max:50', 'unique:payments,payment_code'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'payment_method' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
            'overrun_reason' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.gt' => 'Amount payment harus lebih dari 0.',
        ])->validate();

        $amountCents = PaymentAllocationMath::toCents($validated['amount']);
        $allocations = $this->resolveAllocations($paymentPlan, $data, $amountCents);

        $boqItems = $paymentPlan->boq->cogs->flatMap->items->keyBy('id');

        if ($allocations->keys()->diff($boqItems->keys())->isNotEmpty()) {
            throw BusinessRuleException::make(
                'Alokasi pembayaran hanya boleh ke Item BOQ dari BOQ payment plan ini.'
            );
        }

        if ($allocations->sum(fn (array $row) => PaymentAllocationMath::toCents($row['allocated_amount'])) !== $amountCents) {
            throw BusinessRuleException::make(PaymentAllocationMath::MISMATCH_MESSAGE);
        }

        $overrunItems = $this->overrunItems($paymentPlan, $allocations, $boqItems);

        if ($overrunItems->isNotEmpty() && blank($validated['overrun_reason'] ?? null)) {
            throw ValidationException::withMessages([
                'overrun_reason' => PaymentAllocationMath::OVERRUN_REASON_MESSAGE.' Item: '.$overrunItems->implode(', ').'.',
            ]);
        }

        return DB::transaction(function () use ($actor, $paymentPlan, $validated, $allocations, $overrunItems): Payment {
            PaymentPlan::query()->whereKey($paymentPlan->id)->lockForUpdate()->first();

            $payment = $paymentPlan->payments()->create([
                'payment_code' => $validated['payment_code'],
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'overrun_reason' => $overrunItems->isNotEmpty() ? $validated['overrun_reason'] : null,
                'status' => PaymentStatus::Paid,
                'paid_by' => $actor->id,
            ]);

            foreach ($allocations as $itemId => $row) {
                $payment->allocations()->create([
                    'boq_item_id' => $itemId,
                    'allocated_amount' => $row['allocated_amount'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            $paymentPlan->status = PaymentPlanStatus::Paid;
            $paymentPlan->save();

            return $payment->refresh()->load('allocations');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{allocated_amount: string, notes: string|null}> keyed by boq_item_id
     */
    private function resolveAllocations(PaymentPlan $paymentPlan, array $data, int $amountCents): Collection
    {
        if (! array_key_exists('allocations', $data)) {
            if ($amountCents !== PaymentAllocationMath::toCents($paymentPlan->amount)) {
                throw BusinessRuleException::make(
                    'Nilai pembayaran berbeda dari Payment Plan. Alokasikan pembayaran ke item BOQ.'
                );
            }

            return $paymentPlan->items->mapWithKeys(fn (BoqItem $item) => [
                (int) $item->id => [
                    'allocated_amount' => number_format((float) $item->pivot->planned_amount, 2, '.', ''),
                    'notes' => null,
                ],
            ]);
        }

        $validated = Validator::make(['allocations' => array_values((array) $data['allocations'])], [
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.boq_item_id' => ['required', 'integer', 'distinct'],
            'allocations.*.allocated_amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*.notes' => ['nullable', 'string', 'max:500'],
        ], [
            'allocations.required' => 'Alokasikan pembayaran ke minimal satu item BOQ.',
            'allocations.min' => 'Alokasikan pembayaran ke minimal satu item BOQ.',
            'allocations.*.boq_item_id.distinct' => 'Item BOQ yang sama tidak boleh dipilih dua kali.',
            'allocations.*.allocated_amount.required' => 'Nilai alokasi wajib diisi.',
            'allocations.*.allocated_amount.gt' => 'Nilai alokasi harus lebih dari 0.',
        ])->validate()['allocations'];

        return collect($validated)->mapWithKeys(fn (array $row) => [
            (int) $row['boq_item_id'] => [
                'allocated_amount' => number_format((float) $row['allocated_amount'], 2, '.', ''),
                'notes' => $row['notes'] ?? null,
            ],
        ]);
    }

    /**
     * Items whose actual cost (BMHP + PAID payments + this payment) would exceed their BOQ value.
     *
     * @param  Collection<int, array{allocated_amount: string, notes: string|null}>  $allocations
     * @param  Collection<int, BoqItem>  $boqItems
     * @return Collection<int, string>
     */
    private function overrunItems(PaymentPlan $paymentPlan, Collection $allocations, Collection $boqItems): Collection
    {
        $actualCost = ProjectActualCost::forProject($paymentPlan->boq->project);

        return $allocations
            ->filter(function (array $row, int $itemId) use ($actualCost, $boqItems): bool {
                $afterCents = PaymentAllocationMath::toCents($actualCost->forItem($itemId))
                    + PaymentAllocationMath::toCents($row['allocated_amount']);

                return $afterCents > PaymentAllocationMath::toCents($boqItems[$itemId]->total_price);
            })
            ->map(fn (array $row, int $itemId) => (string) $boqItems[$itemId]->code)
            ->values();
    }
}
