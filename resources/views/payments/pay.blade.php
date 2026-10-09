<x-app-layout>
    <x-slot name="header">Process Payment</x-slot>
    <x-slot name="subheader">{{ $paymentPlan->reference_code }} · {{ $project->code }}</x-slot>

    @php
        $plannedByItem = $paymentPlan->items->mapWithKeys(fn ($item) => [$item->id => (float) $item->pivot->planned_amount]);
        $hasOldInput = old('payment_code') !== null;
        $oldAllocations = collect(old('allocations', []));
        $cogsGroups = $boq->cogs->sortBy('sort_order')->values();
        $allocationRows = $cogsGroups
            ->flatMap(fn ($cogs) => $cogs->items)
            ->mapWithKeys(function ($item) use ($plannedByItem, $hasOldInput, $oldAllocations, $actualCost) {
                $old = $oldAllocations->get((string) $item->id, []);
                $planned = $plannedByItem->get($item->id);

                return [(string) $item->id => [
                    'code' => $item->code,
                    'boq' => (float) $item->total_price,
                    'actual' => $actualCost->forItem($item),
                    'selected' => $hasOldInput ? ! empty($old['selected']) : $planned !== null,
                    'amount' => $hasOldInput ? (string) ($old['allocated_amount'] ?? '') : ($planned !== null ? (string) $planned : ''),
                ]];
            });
    @endphp

    <form method="POST" action="{{ route('payments.store', $paymentPlan) }}"
          x-data="{
            submitting: false,
            amount: @js((string) old('amount', (float) $paymentPlan->amount)),
            rows: @js($allocationRows),
            cents(value) { return Math.round((Number(value) || 0) * 100); },
            get allocated() {
                return Object.values(this.rows).reduce((sum, row) => sum + (row.selected ? this.cents(row.amount) : 0), 0) / 100;
            },
            get difference() { return (this.cents(this.amount) - Math.round(this.allocated * 100)) / 100; },
            get matches() { return this.cents(this.amount) > 0 && this.difference === 0; },
            get overrunCodes() {
                return Object.values(this.rows)
                    .filter((row) => row.selected && this.cents(row.actual) + this.cents(row.amount) > this.cents(row.boq))
                    .map((row) => row.code);
            },
            isOverrun(id) {
                const row = this.rows[id];
                return row.selected && this.cents(row.actual) + this.cents(row.amount) > this.cents(row.boq);
            },
            fillRemaining(id) {
                const row = this.rows[id];
                row.selected = true;
                row.amount = ((Number(row.amount) || 0) + Math.max(0, this.difference)).toFixed(2);
            },
            rupiah(value) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
            }
          }"
          @submit="if (! matches) { $event.preventDefault(); return; } submitting = true"
          class="grid gap-6 lg:grid-cols-3">
        @csrf

        <div class="space-y-4 rounded-xl border border-maroon-200 bg-white p-6 shadow-sm lg:col-span-1">
            <dl class="space-y-2 border-b border-maroon-100 pb-4 text-sm">
                <div class="flex justify-between"><dt class="text-maroon-700">Payment Plan</dt><dd class="font-medium">{{ $paymentPlan->reference_code }}</dd></div>
                <div class="flex justify-between"><dt class="text-maroon-700">BOQ</dt><dd class="font-medium">{{ $boq->code }}</dd></div>
                <div class="flex justify-between"><dt class="text-maroon-700">Nilai Rencana</dt><dd class="font-semibold"><x-money :amount="$paymentPlan->amount" /></dd></div>
            </dl>

            <x-helper-text text="Nilai bayar boleh berbeda dari rencana dan boleh melebihi BOQ. Total alokasi harus sama dengan nilai bayar." />

            <div>
                <x-input-label for="payment_code" value="Payment Code *" />
                <x-text-input id="payment_code" name="payment_code" class="mt-1 block w-full" :value="old('payment_code', 'PAY-'.$paymentPlan->reference_code)" required />
                <x-input-error :messages="$errors->get('payment_code')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="payment_date" value="Payment Date *" />
                <x-text-input id="payment_date" type="date" name="payment_date" class="mt-1 block w-full" :value="old('payment_date', now()->toDateString())" required />
                <x-input-error :messages="$errors->get('payment_date')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="amount" value="Nilai Bayar (Actual) *" />
                <input id="amount" type="number" step="0.01" min="0.01" name="amount" x-model="amount" required
                       class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="payment_method" value="Payment Method" />
                <x-text-input id="payment_method" name="payment_method" class="mt-1 block w-full" :value="old('payment_method', 'bank_transfer')" />
            </div>
            <div>
                <x-input-label for="reference_number" value="Reference Number" />
                <x-text-input id="reference_number" name="reference_number" class="mt-1 block w-full" :value="old('reference_number')" />
            </div>
            <div>
                <x-input-label for="notes" value="Notes" />
                <textarea id="notes" name="notes" rows="2" class="mt-1 w-full rounded-md border-maroon-300 text-sm">{{ old('notes') }}</textarea>
            </div>

            <div x-show="overrunCodes.length > 0" x-cloak class="rounded-lg border border-red-300 bg-red-50 p-3">
                <p class="text-sm font-semibold text-red-800">Actual cost item akan melebihi nilai BOQ</p>
                <p class="mt-1 text-xs text-red-700">Item: <span x-text="overrunCodes.join(', ')"></span></p>
                <x-input-label for="overrun_reason" value="Alasan kelebihan biaya *" class="mt-2" />
                <textarea id="overrun_reason" name="overrun_reason" rows="2" :required="overrunCodes.length > 0"
                          class="mt-1 w-full rounded-md border-red-300 text-sm">{{ old('overrun_reason') }}</textarea>
            </div>
            <x-input-error :messages="$errors->get('overrun_reason')" class="mt-1" />

            <div class="flex justify-end gap-2 border-t border-maroon-100 pt-4">
                <a href="{{ route('projects.payments.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                        :disabled="submitting || ! matches"
                        x-text="submitting ? 'Processing...' : 'Confirm Payment'">
                    Confirm Payment
                </button>
            </div>
        </div>

        <div class="rounded-xl border border-maroon-200 bg-white p-6 shadow-sm lg:col-span-2">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-maroon-950">Alokasi ke Item BOQ</h3>
                    <p class="text-xs text-maroon-700">Item dari payment plan sudah terpilih. Item lain dari BOQ yang sama boleh ditambahkan.</p>
                </div>
                <div class="text-right text-sm">
                    <p>Dialokasikan <span class="font-semibold" x-text="rupiah(allocated)"></span></p>
                    <p :class="difference === 0 ? 'text-maroon-700' : 'font-semibold text-red-700'">
                        Selisih <span x-text="rupiah(difference)"></span>
                    </p>
                </div>
            </div>

            <x-input-error :messages="collect($errors->get('allocations'))->merge($errors->get('allocations.*'))->flatten()->unique()->all()" class="mb-3" />
            <p class="mb-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm font-semibold text-red-800" x-show="cents(amount) > 0 && difference !== 0" x-cloak role="alert">
                {{ \App\Actions\Payment\PaymentAllocationMath::MISMATCH_MESSAGE }}
            </p>

            <div class="max-h-[32rem] space-y-4 overflow-y-auto">
                @foreach ($cogsGroups as $cogs)
                    <div>
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-maroon-700">{{ $cogs->code }} — {{ $cogs->name }}</p>
                        <div class="space-y-2">
                            @foreach ($cogs->items->sortBy('sort_order') as $item)
                                <div class="flex flex-wrap items-start gap-3 rounded-md border border-maroon-100 px-3 py-2"
                                     :class="isOverrun('{{ $item->id }}') ? 'border-red-300 bg-red-50' : (rows['{{ $item->id }}'].selected ? 'border-maroon-300 bg-maroon-50' : '')">
                                    <input type="hidden" name="allocations[{{ $item->id }}][boq_item_id]" value="{{ $item->id }}">
                                    <input type="checkbox" id="alloc-{{ $item->id }}" name="allocations[{{ $item->id }}][selected]" value="1"
                                           x-model="rows['{{ $item->id }}'].selected"
                                           class="mt-1 rounded border-maroon-300 text-maroon-950 focus:ring-maroon-600">
                                    <label for="alloc-{{ $item->id }}" class="min-w-0 flex-1 cursor-pointer">
                                        <span class="block text-sm font-medium text-maroon-950">
                                            {{ $item->code }} — {{ $item->name }}
                                            @if ($plannedByItem->has($item->id))
                                                <span class="ml-1 rounded bg-maroon-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-maroon-800">di plan</span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-maroon-700">
                                            BOQ <x-money :amount="$item->total_price" />
                                            · Actual saat ini <x-money :amount="$actualCost->forItem($item)" />
                                            · Setelah bayar <span class="font-semibold" x-text="rupiah(rows['{{ $item->id }}'].actual + (rows['{{ $item->id }}'].selected ? Number(rows['{{ $item->id }}'].amount) || 0 : 0))"></span>
                                        </span>
                                        <span class="block text-xs font-semibold text-red-700" x-show="isOverrun('{{ $item->id }}')" x-cloak>Melebihi BOQ</span>
                                    </label>
                                    <div class="flex w-56 items-center gap-1">
                                        <input type="number" step="0.01" min="0.01"
                                               name="allocations[{{ $item->id }}][allocated_amount]"
                                               x-model="rows['{{ $item->id }}'].amount"
                                               :disabled="! rows['{{ $item->id }}'].selected"
                                               :required="rows['{{ $item->id }}'].selected"
                                               placeholder="Nilai alokasi"
                                               aria-label="Nilai alokasi {{ $item->code }}"
                                               class="w-full rounded-md border-maroon-300 text-sm disabled:bg-maroon-50 disabled:text-maroon-400">
                                        <button type="button" class="shrink-0 rounded border border-maroon-300 px-2 py-1 text-[11px] text-maroon-800 hover:bg-maroon-100"
                                                x-show="difference > 0" x-cloak @click="fillRemaining('{{ $item->id }}')"
                                                title="Tambahkan selisih ke item ini">+ Sisa</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </form>
</x-app-layout>
