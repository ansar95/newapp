<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Payment</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Payment', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-next-action :action="$context->nextAction" />
        </div>
        <x-workflow-steps :steps="$context->workflowSteps" />
    </div>

    @if (! $approvedBoq && $plans->isEmpty())
        <x-empty-state
            title="Belum ada payment plan."
            description="Payment planning tersedia setelah BOQ disetujui."
            action-label="View BOQ"
            :action-url="route('projects.boq.index', $project)"
        />
    @else
        @unless ($approvedBoq)
            <div role="status" class="mb-4 rounded-xl border border-maroon-300 bg-maroon-100 p-4 text-sm text-maroon-900">
                <p class="font-semibold">BOQ sedang direvisi.</p>
                <p class="mt-1">Selama BOQ direvisi, daftar payment plan hanya dapat dilihat. Plan baru dan proses pembayaran dilanjutkan setelah BOQ disetujui kembali.</p>
            </div>
        @endunless

        <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
            <p class="text-sm font-medium text-maroon-900">Payment adalah actual cost yang benar-benar dibayarkan.</p>
            <x-helper-text class="mt-1" text="Nilai rencana dan nilai bayar boleh berbeda dari BOQ, bahkan melebihi BOQ. Setiap pembayaran wajib dialokasikan ke item BOQ. Progress pekerjaan tetap diupdate terpisah." />
        </div>

        <div class="mb-6 grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Nilai BOQ</p>
                <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalBoq" /></p>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Payment Keluar</p>
                <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalPaid" /></p>
                <p class="mt-1 text-xs text-maroon-700">Payment berstatus PAID</p>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sisa BOQ</p>
                <p class="mt-2 text-xl font-semibold"><x-money :amount="$remaining" /></p>
                @if ($totalPaid > $totalBoq)
                    <p class="mt-1 text-xs font-semibold text-red-700">Lebih bayar <x-money :amount="$totalPaid - $totalBoq" /></p>
                @endif
            </div>
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Actual Cost Total</p>
                <p class="mt-2 text-xl font-semibold"><x-money :amount="$context->totalActual" /></p>
                <p class="mt-1 text-xs text-maroon-700">BMHP <x-money :amount="$context->bmhpActual" /> + Sewa Alat <x-money :amount="$context->rentalActual" /> + Payment <x-money :amount="$context->paymentActual" /></p>
            </div>
        </div>

        @if ($approvedBoq)
        @can('create', [\App\Models\PaymentPlan::class, $project])
            @php
                $oldItems = collect(old('items', []));
                $planningRows = $planningCogs
                    ->flatMap(fn ($group) => $group['items'])
                    ->mapWithKeys(function ($row) use ($oldItems) {
                        $id = (string) $row['item']->id;
                        $old = $oldItems->get($id, []);

                        return [$id => [
                            'selected' => ! empty($old['selected']),
                            'amount' => (string) ($old['planned_amount'] ?? ($row['suggested'] > 0 ? $row['suggested'] : '')),
                        ]];
                    });
            @endphp
            <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm"
                 x-data="{
                    submitting: false,
                    rows: @js($planningRows),
                    get selectedCount() {
                        return Object.values(this.rows).filter((row) => row.selected).length;
                    },
                    get total() {
                        return Object.values(this.rows).reduce((sum, row) => sum + (row.selected ? (Number(row.amount) || 0) : 0), 0);
                    },
                    formatMoney(value) {
                        return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value || 0);
                    }
                 }">
                <h3 class="mb-1 font-semibold text-maroon-950">Buat Rencana Pembayaran</h3>
                <x-helper-text class="mb-4" text="Pilih item BOQ dan isi nilai rencana per item. Nilai awal = sisa BOQ item (BOQ − actual − rencana terbuka). Item yang sudah dibayar tetap bisa dipilih lagi." />

                @if ($planningCogs->isEmpty())
                    <p class="rounded-md border border-maroon-300 bg-maroon-100 px-3 py-2 text-sm text-maroon-900">
                        BOQ belum memiliki item.
                    </p>
                @else
                    <form method="POST" action="{{ route('payment-plans.store', $project) }}" class="space-y-4"
                          @submit="if (selectedCount === 0) { $event.preventDefault(); return; } submitting = true">
                        @csrf
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <x-input-label value="Kode Pembayaran *" />
                                <input type="text" name="reference_code" value="{{ old('reference_code') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                <x-input-error :messages="$errors->get('reference_code')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label value="Jenis *" />
                                <select name="type" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-maroon-700">Direncanakan = tanggal tertentu. Bisa Dibayar Langsung = siap diproses.</p>
                            </div>
                            <div>
                                <x-input-label value="Planned Date" />
                                <input type="date" name="planned_date" value="{{ old('planned_date') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                            </div>
                            <div>
                                <x-input-label value="Total Terpilih" />
                                <p class="mt-2 text-lg font-semibold text-maroon-950" x-text="formatMoney(total)"></p>
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Pilih Item BOQ & Nilai Rencana *" />
                            <x-input-error :messages="$errors->get('items')" class="mt-1" />
                            <x-input-error :messages="collect($errors->get('items.*'))->flatten()->unique()->all()" class="mt-1" />
                            <x-input-error :messages="$errors->get('item_ids')" class="mt-1" />
                            <div class="mt-2 max-h-96 space-y-4 overflow-y-auto rounded-lg border border-maroon-200 p-3">
                                @foreach ($planningCogs as $group)
                                    <div>
                                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-maroon-700">
                                            {{ $group['code'] }} — {{ $group['name'] }}
                                        </p>
                                        <div class="space-y-2">
                                            @foreach ($group['items'] as $row)
                                                @php($item = $row['item'])
                                                <div class="flex flex-wrap items-start gap-3 rounded-md border border-maroon-100 px-3 py-2"
                                                     :class="rows['{{ $item->id }}'].selected ? 'border-maroon-300 bg-maroon-50' : ''">
                                                    <input type="hidden" name="items[{{ $item->id }}][boq_item_id]" value="{{ $item->id }}">
                                                    <input type="checkbox"
                                                           id="plan-item-{{ $item->id }}"
                                                           name="items[{{ $item->id }}][selected]"
                                                           value="1"
                                                           x-model="rows['{{ $item->id }}'].selected"
                                                           class="mt-1 rounded border-maroon-300 text-maroon-950 focus:ring-maroon-600">
                                                    <label for="plan-item-{{ $item->id }}" class="min-w-0 flex-1 cursor-pointer">
                                                        <span class="block text-sm font-medium text-maroon-950">{{ $item->code }} — {{ $item->name }}</span>
                                                        <span class="block text-xs text-maroon-700">
                                                            BOQ <x-money :amount="$item->total_price" />
                                                            · Actual <x-money :amount="$row['actual']" />
                                                            @if ($row['open_planned'] > 0)
                                                                · Rencana terbuka <x-money :amount="$row['open_planned']" />
                                                            @endif
                                                        </span>
                                                        @if ($row['actual'] > (float) $item->total_price)
                                                            <span class="block text-xs font-semibold text-red-700">Actual sudah melebihi BOQ</span>
                                                        @endif
                                                    </label>
                                                    <div class="w-44">
                                                        <input type="number" step="0.01" min="0.01"
                                                               name="items[{{ $item->id }}][planned_amount]"
                                                               x-model="rows['{{ $item->id }}'].amount"
                                                               :disabled="! rows['{{ $item->id }}'].selected"
                                                               :required="rows['{{ $item->id }}'].selected"
                                                               placeholder="Nilai rencana"
                                                               aria-label="Nilai rencana {{ $item->code }}"
                                                               class="w-full rounded-md border-maroon-300 text-sm disabled:bg-maroon-50 disabled:text-maroon-400">
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Notes" />
                            <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="rounded-md bg-maroon-800 px-4 py-2 text-sm text-white disabled:opacity-60"
                                    :disabled="submitting || selectedCount === 0"
                                    x-text="submitting ? 'Processing...' : 'Save Plan'">
                                Save Plan
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @endcan
        @endif

        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm" x-data="{ payPlan: null, payAmount: null, payUrl: null }">
            @if ($plans->isEmpty())
                <div class="p-2">
                    <x-empty-state
                        title="Belum ada payment plan."
                        description="Buat payment plan untuk memproses pembayaran."
                    />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                        <tr>
                            <th class="px-4 py-3">Payment Code</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Items</th>
                            <th class="px-4 py-3">Rencana</th>
                            <th class="px-4 py-3">Dibayar</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-maroon-100">
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $plan->reference_code }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $plan->type->label() }}</div>
                                    <div class="text-xs text-maroon-700">{{ $plan->type->description() }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($plan->items->isEmpty())
                                        <span class="text-maroon-400">—</span>
                                    @else
                                        <ul class="space-y-0.5 text-xs text-maroon-700">
                                            @foreach ($plan->items->take(3) as $planItem)
                                                <li>{{ $planItem->code }}</li>
                                            @endforeach
                                            @if ($plan->items->count() > 3)
                                                <li class="text-maroon-400">+{{ $plan->items->count() - 3 }} lainnya</li>
                                            @endif
                                        </ul>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$plan->amount" /></td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($plan->activePayment())
                                        <x-money :amount="$plan->paidAmount()" />
                                        @if ($plan->paidAmount() > (float) $plan->amount)
                                            <div class="text-xs font-semibold text-red-700">+<x-money :amount="$plan->paidAmount() - (float) $plan->amount" /> dari rencana</div>
                                        @endif
                                    @else
                                        <span class="text-maroon-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$plan->planned_date" /></td>
                                <td class="px-4 py-3"><x-status-badge :status="$plan->status" /></td>
                                <td class="px-4 py-3 space-x-2 whitespace-nowrap">
                                    @unless ($approvedBoq)
                                        <span class="text-maroon-400">—</span>
                                    @else
                                    @if ($plan->status === \App\Enums\PaymentPlanStatus::Planned)
                                        @can('markReady', $plan)
                                            <form method="POST" action="{{ route('payment-plans.ready', $plan) }}" class="inline" x-data="{ submitting: false }" @submit="submitting = true">
                                                @csrf
                                                <button class="font-medium text-maroon-900 hover:underline disabled:opacity-60" :disabled="submitting"
                                                        x-text="submitting ? 'Processing...' : 'Siapkan Pembayaran'">Siapkan Pembayaran</button>
                                            </form>
                                        @endcan
                                    @endif
                                    @if ($plan->status === \App\Enums\PaymentPlanStatus::ReadyToPay)
                                        @can('process', [\App\Models\Payment::class, $plan])
                                            <button type="button"
                                                    class="font-medium text-maroon-900 hover:underline"
                                                    @click="payPlan = '{{ $plan->reference_code }}'; payAmount = '{{ rupiah($plan->amount) }}'; payUrl = '{{ route('payments.pay', $plan) }}'">
                                                Process Payment
                                            </button>
                                        @endcan
                                    @endif
                                    @if ($plan->status === \App\Enums\PaymentPlanStatus::Paid)
                                        <span class="text-maroon-700">Paid</span>
                                        @if (auth()->user()?->hasPermission(\App\Enums\Permission::ProgressUpdate))
                                            <a href="{{ route('projects.progress.index', $project) }}" class="font-medium text-maroon-900 hover:underline">Update Progress</a>
                                        @elseif (auth()->user()?->can('viewProgress', $project))
                                            <a href="{{ route('projects.progress.index', $project) }}" class="font-medium text-maroon-900 hover:underline">View Progress</a>
                                        @endif
                                    @endif
                                    @endunless
                                </td>
                            </tr>
                            @if ($plan->payments->isNotEmpty())
                                <tr class="bg-maroon-50/40">
                                    <td colspan="8" class="px-4 pb-4 pt-1">
                                        <div class="space-y-2">
                                            @foreach ($plan->payments as $payment)
                                                <div @class([
                                                    'rounded-lg border bg-white p-3 text-xs',
                                                    'border-maroon-200' => $payment->countsAsActual(),
                                                    'border-dashed border-maroon-300 opacity-75' => ! $payment->countsAsActual(),
                                                ])>
                                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="font-semibold text-maroon-950">{{ $payment->payment_code }}</span>
                                                            <x-status-badge :status="$payment->status" />
                                                            <span class="text-maroon-700"><x-date-display :value="$payment->payment_date" /></span>
                                                            <span class="font-semibold text-maroon-950"><x-money :amount="$payment->amount" /></span>
                                                        </div>
                                                        @if ($approvedBoq)
                                                        @can('void', $payment)
                                                            <details class="text-right">
                                                                <summary class="cursor-pointer font-medium text-maroon-900 hover:underline">Void</summary>
                                                                <form method="POST" action="{{ route('payments.void', $payment) }}" class="mt-2 flex flex-wrap items-start justify-end gap-2"
                                                                      x-data="{ submitting: false }" @submit="submitting = true">
                                                                    @csrf
                                                                    <input type="text" name="void_reason" required minlength="3" placeholder="Alasan pembatalan"
                                                                           aria-label="Alasan pembatalan {{ $payment->payment_code }}"
                                                                           class="w-64 rounded-md border-maroon-300 text-xs">
                                                                    <button class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-60"
                                                                            :disabled="submitting" x-text="submitting ? 'Processing...' : 'Void Payment'">Void Payment</button>
                                                                </form>
                                                            </details>
                                                        @endcan
                                                        @endif
                                                    </div>
                                                    @if ($payment->allocations->isNotEmpty())
                                                        <ul class="mt-2 grid gap-1 sm:grid-cols-2">
                                                            @foreach ($payment->allocations as $allocation)
                                                                <li class="flex justify-between gap-2 rounded bg-maroon-50 px-2 py-1">
                                                                    <span class="truncate">{{ $allocation->boqItem?->code }} — {{ $allocation->boqItem?->name }}</span>
                                                                    <span class="shrink-0 font-medium"><x-money :amount="$allocation->allocated_amount" /></span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                    @if ($payment->overrun_reason)
                                                        <p class="mt-2 text-red-700"><span class="font-semibold">Alasan melebihi BOQ:</span> {{ $payment->overrun_reason }}</p>
                                                    @endif
                                                    @if (! $payment->countsAsActual())
                                                        <p class="mt-2 text-maroon-700">
                                                            Dibatalkan {{ $payment->voider?->name ? 'oleh '.$payment->voider->name : '' }}
                                                            <x-date-display :value="$payment->voided_at" /> — {{ $payment->void_reason }}
                                                        </p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div x-show="payPlan" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-800/50 p-4" role="dialog" aria-modal="true">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-maroon-950">Confirm Payment</h3>
                    <p class="mt-3 text-sm text-maroon-700">
                        Anda akan memproses pembayaran
                        <span class="font-semibold text-maroon-950" x-text="payPlan"></span>
                        sebesar
                        <span class="font-semibold text-maroon-950" x-text="payAmount"></span>.
                    </p>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-maroon-300 px-4 py-2 text-sm" @click="payPlan = null">Cancel</button>
                        <a :href="payUrl" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900">Confirm Payment</a>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
