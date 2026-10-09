<?php

namespace App\Actions\Payment;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreatePaymentPlan
{
    /**
     * A BOQ item may appear in many plans and the planned amount is free (it may exceed the BOQ value).
     * When only item_ids are given, each item is planned at its BOQ value.
     *
     * @param  array{
     *     reference_code: string,
     *     type: string,
     *     planned_date?: string|null,
     *     items?: list<array{boq_item_id: int|string, planned_amount: float|int|string}>,
     *     item_ids?: list<int|string>,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, Boq $boq, array $data): PaymentPlan
    {
        if ($boq->status !== BoqStatus::Approved) {
            throw BusinessRuleException::make(
                'Payment Plan tidak dapat dibuat karena BOQ belum APPROVED.'
            );
        }

        if (! array_key_exists('items', $data) && array_key_exists('item_ids', $data)) {
            $data['items'] = $this->itemsAtBoqValue((array) $data['item_ids']);
        }

        $validated = Validator::make($data, [
            'reference_code' => ['required', 'string', 'max:50', 'unique:payment_plans,reference_code'],
            'type' => ['required', Rule::enum(PaymentPlanType::class)],
            'planned_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.boq_item_id' => ['required', 'integer', 'distinct', 'exists:boq_items,id'],
            'items.*.planned_amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999999'],
            'notes' => ['nullable', 'string'],
        ], [
            'items.required' => 'Pilih minimal satu item BOQ untuk dibayar.',
            'items.min' => 'Pilih minimal satu item BOQ untuk dibayar.',
            'items.*.boq_item_id.distinct' => 'Item BOQ yang sama tidak boleh dipilih dua kali.',
            'items.*.planned_amount.required' => 'Nilai rencana per item wajib diisi.',
            'items.*.planned_amount.gt' => 'Nilai rencana per item harus lebih dari 0.',
            'reference_code.unique' => 'Reference code payment plan sudah digunakan.',
        ])->validate();

        $type = $validated['type'] instanceof PaymentPlanType
            ? $validated['type']
            : PaymentPlanType::from($validated['type']);

        if ($type === PaymentPlanType::Planned && empty($validated['planned_date'])) {
            throw BusinessRuleException::make(
                'Payment Plan bertipe PLANNED harus memiliki planned date.'
            );
        }

        $boq->loadMissing('cogs.items');
        $boqItemIds = $boq->cogs->flatMap->items->pluck('id')->map(fn ($id) => (int) $id);

        $plannedItems = collect($validated['items'])->mapWithKeys(fn (array $row) => [
            (int) $row['boq_item_id'] => number_format((float) $row['planned_amount'], 2, '.', ''),
        ]);

        if ($plannedItems->keys()->diff($boqItemIds)->isNotEmpty()) {
            throw BusinessRuleException::make(
                'Item BOQ tidak valid untuk BOQ ini.'
            );
        }

        $amountCents = $plannedItems->sum(fn (string $amount) => PaymentAllocationMath::toCents($amount));

        $status = match ($type) {
            PaymentPlanType::Planned => PaymentPlanStatus::Planned,
            PaymentPlanType::ReadyToPay => PaymentPlanStatus::ReadyToPay,
        };

        return DB::transaction(function () use ($actor, $boq, $validated, $type, $status, $amountCents, $plannedItems): PaymentPlan {
            $plan = PaymentPlan::query()->create([
                'boq_id' => $boq->id,
                'reference_code' => $validated['reference_code'],
                'type' => $type,
                'planned_date' => $validated['planned_date'] ?? null,
                'amount' => PaymentAllocationMath::fromCents($amountCents),
                'status' => $status,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $plan->items()->attach(
                $plannedItems->map(fn (string $amount) => ['planned_amount' => $amount])->all()
            );

            return $plan->fresh(['items']);
        });
    }

    /**
     * @param  list<int|string>  $itemIds
     * @return list<array{boq_item_id: int|string, planned_amount: string}>
     */
    private function itemsAtBoqValue(array $itemIds): array
    {
        $prices = BoqItem::query()
            ->whereIn('id', collect($itemIds)->filter(fn ($id) => is_numeric($id))->all())
            ->pluck('total_price', 'id');

        return collect($itemIds)
            ->map(fn ($id) => [
                'boq_item_id' => $id,
                'planned_amount' => (string) ($prices[(int) $id] ?? ''),
            ])
            ->values()
            ->all();
    }
}
