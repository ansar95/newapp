<x-app-layout>
    <x-slot name="header">{{ $project->code }} — BOQ</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'BOQ', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">BOQ adalah daftar biaya project.</p>
        <x-helper-text class="mt-1" text="COGS adalah kelompok biaya/pekerjaan. Setiap COGS berisi Item Pekerjaan yang memiliki quantity, unit, dan unit price." />
    </div>

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-next-action :action="$context->nextAction" />
        </div>
        <x-workflow-steps :steps="$context->workflowSteps" />
    </div>

    @if ($boqs->isEmpty())
        <x-empty-state
            title="Belum ada BOQ."
            description="Buat BOQ untuk menyusun daftar biaya project."
            :action-label="auth()->user()->can('create', \App\Models\Boq::class) ? 'Buat BOQ' : null"
            :action-url="auth()->user()->can('create', \App\Models\Boq::class) ? route('projects.boq.create', $project) : null"
        />
    @else
        @php $boq = $primaryBoq; @endphp

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3"
             @if ($boq->status === \App\Enums\BoqStatus::Approved)
                 x-data="{ showRevision: {{ ($errors->has('reason') || $errors->has('item_ids')) ? 'true' : 'false' }}, submittingRevision: false }"
             @endif>
            <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-lg font-semibold text-maroon-950">{{ $boq->code }}</h3>
                <x-status-badge :status="$boq->status" />
                <span class="text-sm text-maroon-700">Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($boq->status === \App\Enums\BoqStatus::Draft)
                    <a href="{{ route('boqs.show', $boq) }}" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm">Open BOQ</a>
                    @can('submit', $boq)
                        <form method="POST" action="{{ route('boqs.submit', $boq) }}" x-data="{ submitting: false }" @submit="submitting = true">
                            @csrf
                            <button class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-bold text-white disabled:opacity-60" :disabled="submitting"
                                    x-text="submitting ? 'Processing...' : 'Submit'">Submit</button>
                        </form>
                    @endcan
                @elseif ($boq->status === \App\Enums\BoqStatus::WaitingApproval)
                    <a href="{{ route('boqs.show', $boq) }}" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm">View</a>
                    @can('review', $boq)
                        <a href="{{ route('boqs.review', $boq) }}" class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-maroon-900">Review & Approve</a>
                    @endcan
                @elseif ($boq->status === \App\Enums\BoqStatus::RevisionRequired)
                    @can('viewForBoq', [\App\Models\BoqRevision::class, $boq])
                        <a href="{{ route('boqs.revision.show', $boq) }}" class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-bold text-white">Open Revision</a>
                    @endcan
                @elseif ($boq->status === \App\Enums\BoqStatus::Approved)
                    <a href="{{ route('boqs.show', $boq) }}" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm">View BOQ</a>
                    @can('requestRevision', $boq)
                        <button type="button"
                                @click="showRevision = true"
                                class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-bold text-white hover:bg-maroon-900">
                            Revisi BOQ
                        </button>
                    @endcan
                    @can('create', [\App\Models\PaymentPlan::class, $project])
                        <a href="{{ route('projects.payment.index', $project) }}" class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm text-white">Payment Planning</a>
                    @elsecan('viewForProject', [\App\Models\PaymentPlan::class, $project])
                        <a href="{{ route('projects.payment.index', $project) }}" class="rounded-md bg-maroon-800 px-3 py-1.5 text-sm text-white">View Payment</a>
                    @endcan
                @endif
                @can('view', $boq)
                    <a href="{{ route('boqs.cogs.export', $boq) }}" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm">Export Excel</a>
                @endcan
                @can('create', \App\Models\Boq::class)
                    @if ($boqs->count() < 2)
                        <a href="{{ route('projects.boq.create', $project) }}" class="rounded-md border border-maroon-300 px-3 py-1.5 text-sm">Buat BOQ Lain</a>
                    @endif
                @endcan
            </div>

            @if ($boq->status === \App\Enums\BoqStatus::Approved)
                @can('requestRevision', $boq)
                    <div x-show="showRevision" x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-800/50 p-4"
                         role="dialog" aria-modal="true" aria-labelledby="boq-revision-title">
                        <div class="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
                             @keydown.escape.window="showRevision = false">
                            <h3 id="boq-revision-title" class="text-lg font-semibold text-maroon-950">Revisi BOQ</h3>
                            <p class="mt-1 text-sm text-maroon-700">
                                Pilih item yang akan direvisi. Setelah perbaikan, BOQ harus diajukan dan di-approve ulang.
                            </p>
                            <form method="POST" action="{{ route('boqs.request-revision', $boq) }}" class="mt-4 space-y-4"
                                  @submit="submittingRevision = true">
                                @csrf
                                <div>
                                    <x-input-label for="reason" value="Alasan Revisi *" />
                                    <textarea id="reason" name="reason" rows="3" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required
                                              placeholder="Contoh: Harga item perlu disesuaikan.">{{ old('reason') }}</textarea>
                                    <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                                </div>
                                <div>
                                    <p class="mb-2 text-sm font-medium text-maroon-800">Item yang Direvisi *</p>
                                    <x-input-error :messages="$errors->get('item_ids')" class="mt-1" />
                                    <div class="max-h-64 space-y-2 overflow-y-auto rounded-md border border-maroon-200 p-3">
                                        @foreach ($boq->cogs as $cogs)
                                            <p class="pt-2 text-xs font-semibold uppercase text-maroon-700 first:pt-0">{{ $cogs->name }}</p>
                                            @foreach ($cogs->items as $item)
                                                <label class="flex items-start gap-2 text-sm">
                                                    <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                           class="mt-1 rounded border-maroon-300 text-maroon-950 focus:ring-maroon-600"
                                                           @checked(collect(old('item_ids', []))->contains($item->id))>
                                                    <span>
                                                        <span class="font-medium">{{ $item->code }}</span> — {{ $item->name }}
                                                        <span class="block text-xs text-maroon-700"><x-money :amount="$item->total_price" /></span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        @endforeach
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-md border border-maroon-300 px-4 py-2 text-sm"
                                            @click="showRevision = false">Cancel</button>
                                    <button type="submit"
                                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white disabled:opacity-60"
                                            :disabled="submittingRevision"
                                            x-text="submittingRevision ? 'Processing...' : 'Mulai Revisi'">
                                        Mulai Revisi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan
            @endif
        </div>

        <div class="mb-4 grid gap-3 text-sm sm:grid-cols-4">
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Total Value</p>
                <p class="font-semibold"><x-money :amount="$boq->totalAmount()" /></p>
                <p class="text-xs text-maroon-700">Material <x-money :amount="$boq->materialAmount()" /> · Jasa <x-money :amount="$boq->serviceAmount()" /></p>
            </div>
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Submitted</p>
                <p class="font-semibold"><x-date-display :value="$boq->submitted_at" format="d/m/Y H:i" /></p>
            </div>
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Reviewed</p>
                <p class="font-semibold"><x-date-display :value="$boq->reviewed_at" format="d/m/Y H:i" /></p>
            </div>
            <div class="rounded-lg border border-maroon-200 bg-white p-3">
                <p class="text-maroon-700">Revision</p>
                <p class="font-semibold">{{ $boq->revision_number }}</p>
            </div>
        </div>

        @foreach ($boq->cogs as $cogs)
            <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h4 class="font-semibold uppercase tracking-wide text-maroon-950">{{ $cogs->name }}</h4>
                    <p class="text-sm font-medium text-maroon-800">Total: <x-money :amount="$cogs->totalAmount()" /></p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Qty</th>
                            <th class="px-3 py-2">Unit</th>
                            <th class="px-3 py-2">Harga Satuan</th>
                            <th class="px-3 py-2">Total</th>
                            <th class="px-3 py-2">Progress</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-maroon-100">
                        @foreach ($cogs->items as $item)
                            <tr>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="text-xs text-maroon-700">{{ $item->code }}</div>
                                </td>
                                <td class="px-3 py-2">{{ number_format((float) $item->quantity, 2) }}</td>
                                <td class="px-3 py-2">{{ $item->unit }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <x-money :amount="$item->unit_price" />
                                    <div class="text-xs text-maroon-700">M <x-money :amount="$item->material_unit_price" /> · J <x-money :amount="$item->service_unit_price" /></div>
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap">
                                    <span class="font-medium"><x-money :amount="$item->total_price" /></span>
                                    <div class="text-xs text-maroon-700">M <x-money :amount="$item->materialTotal()" /> · J <x-money :amount="$item->serviceTotal()" /></div>
                                </td>
                                <td class="px-3 py-2"><x-percent :value="$item->progress_percentage" :decimals="1" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-right text-sm font-semibold text-maroon-900">COGS TOTAL: <x-money :amount="$cogs->totalAmount()" /></p>
                <p class="text-right text-xs text-maroon-700">Material <x-money :amount="$cogs->materialAmount()" /> · Jasa <x-money :amount="$cogs->serviceAmount()" /></p>
            </div>
        @endforeach

        <div class="mb-6 rounded-xl border border-maroon-900 bg-maroon-800 p-4 text-white">
            <div class="flex items-center justify-between">
                <span class="text-sm font-medium uppercase tracking-wide text-maroon-200">TOTAL BOQ</span>
                <span class="text-xl font-semibold"><x-money :amount="$boq->totalAmount()" /></span>
            </div>
            <div class="mt-2 flex flex-wrap justify-end gap-4 text-sm text-maroon-100">
                <span>Total Material: <strong class="text-white"><x-money :amount="$boq->materialAmount()" /></strong></span>
                <span>Total Jasa: <strong class="text-white"><x-money :amount="$boq->serviceAmount()" /></strong></span>
            </div>
        </div>

        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 font-semibold text-maroon-950">Revision History</h3>
            @php $revisions = $boq->revisions->sortByDesc('revision_number'); @endphp
            @forelse ($revisions as $revision)
                <div class="flex items-start gap-3 border-b border-maroon-100 py-3 last:border-0">
                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full {{ $revision->status === \App\Enums\RevisionStatus::Resolved ? 'bg-maroon-100' : 'bg-maroon-100' }}"></span>
                    <div class="flex flex-1 items-center justify-between gap-3">
                        <div>
                            <p class="font-medium text-maroon-950">REV{{ str_pad((string) $revision->revision_number, 2, '0', STR_PAD_LEFT) }}</p>
                            <p class="text-xs text-maroon-700">{{ $revision->revision_code }} · <x-date-display :value="$revision->created_at" /></p>
                        </div>
                        <x-status-badge :status="$revision->status" />
                    </div>
                </div>
            @empty
                <p class="text-sm text-maroon-700">Belum ada revision history.</p>
            @endforelse
        </div>
    @endif
</x-app-layout>
