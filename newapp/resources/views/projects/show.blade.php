<x-app-layout>
    <x-slot name="header">{{ $project->code }}</x-slot>
    <x-slot name="subheader">{{ $project->name }}</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @if ($context->primaryBoq)
                <x-project-summary-cards :context="$context" />
                @can('viewForProject', [\App\Models\BmhpPurchase::class, $project])
                    <x-cost-control-summary :context="$context" />
                @endcan
            @else
                <div class="rounded-xl border border-dashed border-maroon-300 bg-white p-6">
                    <h3 class="text-base font-semibold text-maroon-950">Project siap dilanjutkan</h3>
                    <p class="mt-2 text-sm text-maroon-700">
                        Project adalah wadah pekerjaan. Langkah berikutnya: susun BOQ (daftar biaya) beserta COGS dan item pekerjaan.
                    </p>
                </div>
            @endif

            @if ($project->status !== \App\Enums\ProjectStatus::Draft)
                @can('viewForProject', [\App\Models\ClientBilling::class, $project])
                    <x-billing-summary :summary="$context->billingSummary()" :progress="$context->overallProgress" compact />
                @endcan
                @can('viewForProject', [\App\Models\ProjectDocument::class, $project])
                    <x-document-status :monitor="$context->documentMonitor()" :project="$project" />
                @endcan
            @endif

            @if ($project->status === \App\Enums\ProjectStatus::Draft)
                @can('activate', $project)
                    <form method="POST" action="{{ route('projects.activate', $project) }}"
                          x-data="{ submitting: false }"
                          @submit="submitting = true">
                        @csrf
                        <button type="submit"
                                class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900 disabled:opacity-60"
                                :disabled="submitting"
                                x-text="submitting ? 'Processing...' : 'Activate Project'">
                            Activate Project
                        </button>
                    </form>
                @endcan
            @endif
        </div>
        <div class="space-y-4">
            <x-next-action :action="$context->nextAction" />
            @if ($project->status !== \App\Enums\ProjectStatus::Draft)
                @can('viewForProject', [\App\Models\ProjectDocument::class, $project])
                    <x-document-action-alert :monitor="$context->documentMonitor()" :project="$project" />
                @endcan
            @endif
            <x-workflow-steps :steps="$context->workflowSteps" />
        </div>
    </div>

    <div class="grid gap-6 {{ $context->primaryBoq ? 'lg:grid-cols-2' : '' }}">
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-base font-semibold text-maroon-950">Informasi Project</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-maroon-700">Code</dt>
                    <dd class="mt-1 font-medium">{{ $project->code }}</dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Status</dt>
                    <dd class="mt-1"><x-status-badge :status="$project->status" /></dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Client</dt>
                    <dd class="mt-1 font-medium">{{ $project->client_name }}</dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Nomor Kontrak</dt>
                    <dd class="mt-1 font-medium">{{ $project->contract_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Nilai Kontrak</dt>
                    <dd class="mt-1 font-medium">
                        @if ($project->contract_value !== null)
                            <x-money :amount="$project->contract_value" />
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Item Pekerjaan</dt>
                    <dd class="mt-1 font-medium">{{ $context->totalItems }}</dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Start Date</dt>
                    <dd class="mt-1 font-medium"><x-date-display :value="$project->start_date" /></dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Target End</dt>
                    <dd class="mt-1 font-medium"><x-date-display :value="$project->target_end_date" /></dd>
                </div>
                @if ($project->description)
                    <div class="sm:col-span-2">
                        <dt class="text-maroon-700">Description</dt>
                        <dd class="mt-1 font-medium">{{ $project->description }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($context->primaryBoq)
            <div class="space-y-4">
                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-base font-semibold text-maroon-950">Ringkasan BOQ</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Code</dt><dd class="font-medium">{{ $context->primaryBoq->code }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Status</dt><dd><x-status-badge :status="$context->primaryBoq->status" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Total</dt><dd class="font-medium"><x-money :amount="$context->primaryBoq->totalAmount()" /></dd></div>
                    </dl>
                </div>

                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-base font-semibold text-maroon-950">Ringkasan Pembayaran</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Direncanakan</dt><dd class="font-medium"><x-money :amount="$context->totalPlanned" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Payment Keluar</dt><dd class="font-medium"><x-money :amount="$context->totalPaid" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Remaining</dt><dd class="font-medium"><x-money :amount="$context->remaining" /></dd></div>
                    </dl>
                </div>

                <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
                    <h3 class="mb-3 text-base font-semibold text-maroon-950">Ringkasan Progress</h3>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Total BOQ</dt><dd class="font-medium"><x-money :amount="$context->totalBoqValue" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Nilai Selesai</dt><dd class="font-medium"><x-money :amount="$context->earnedValue" /></dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-maroon-700">Progress</dt><dd class="font-medium"><x-percent :value="$context->overallProgress" :decimals="1" /></dd></div>
                    </dl>
                    <x-helper-text class="mt-3" text="Progress dihitung dari item pekerjaan, bukan dari jumlah pembayaran." />
                </div>
            </div>
        @endif
    </div>

    @can('delete', $project)
        <div class="mt-6 rounded-xl border border-red-300 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-base font-semibold text-red-800">Hapus Project</h3>
                    <p class="mt-1 text-sm text-maroon-700">
                        Project beserta BOQ, payment, BMHP, penagihan, dan dokumennya akan disembunyikan dari aplikasi. Data tetap tersimpan di database. Hanya Super Admin yang dapat menghapus project.
                    </p>
                </div>
                <button type="button"
                        x-data=""
                        x-on:click.prevent="$dispatch('open-modal', 'confirm-project-deletion')"
                        class="inline-flex shrink-0 items-center justify-center rounded-md bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2">
                    Hapus Project
                </button>
            </div>
        </div>

        <x-modal name="confirm-project-deletion" :show="$errors->projectDeletion->isNotEmpty()" focusable>
            <form method="POST" action="{{ route('projects.destroy', $project) }}" class="p-6"
                  x-data="{ submitting: false }"
                  @submit="submitting = true">
                @csrf
                @method('DELETE')

                <h2 class="text-lg font-semibold text-maroon-950">Hapus project {{ $project->code }}?</h2>

                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-maroon-800">
                    <li>Project, BOQ, payment, BMHP, penagihan, dan dokumen project ini tidak akan tampil lagi di aplikasi maupun laporan.</li>
                    @if ($activeAssetCount > 0)
                        <li>{{ $activeAssetCount }} asset yang sedang dipakai project ini akan dilepas dan kembali berstatus AVAILABLE.</li>
                    @endif
                    <li>Data tetap tersimpan di database, tetapi tidak dapat dipulihkan dari aplikasi.</li>
                </ul>

                <div class="mt-5">
                    <x-input-label for="confirmation_code">
                        Ketik <span class="font-mono font-bold">{{ $project->code }}</span> untuk konfirmasi
                    </x-input-label>
                    <x-text-input id="confirmation_code" name="confirmation_code" type="text" class="mt-1 block w-full"
                                  autocomplete="off" :value="old('confirmation_code')" required />
                    <x-input-error :messages="$errors->projectDeletion->get('confirmation_code')" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-red-700 px-4 py-2 text-sm font-bold text-white hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-600 focus:ring-offset-2 disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Menghapus...' : 'Hapus Project'">
                        Hapus Project
                    </button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-app-layout>
