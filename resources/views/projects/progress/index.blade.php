<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Progress</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Progress', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-maroon-700">Overall Project Progress</p>
                <p class="mb-3 text-3xl font-semibold tabular-nums text-maroon-950"><x-percent :value="$overallProgress" :decimals="1" /></p>
                <x-progress-bar :percent="$overallProgress" />
                <x-helper-text class="mt-3" text="Progress project dihitung berdasarkan nilai masing-masing item BOQ. Progress = Total Nilai Selesai ÷ Total BOQ × 100%." />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-maroon-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-maroon-700">Total BOQ</p>
                    <p class="mt-1 font-semibold"><x-money :amount="$totalBoqValue" /></p>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-maroon-700">Nilai Selesai</p>
                    <p class="mt-1 font-semibold"><x-money :amount="$earnedValue" /></p>
                </div>
                <div class="rounded-xl border border-maroon-200 bg-white p-4 shadow-sm">
                    <p class="text-sm text-maroon-700">Progress</p>
                    <p class="mt-1 font-semibold"><x-percent :value="$overallProgress" :decimals="1" /></p>
                </div>
            </div>
            <div class="rounded-xl border border-maroon-200 bg-maroon-50 p-4 text-sm text-maroon-700">
                Payment selesai tidak berarti progress 100%. Progress pekerjaan diperbarui berdasarkan Item BOQ, bukan berdasarkan jumlah pembayaran.
            </div>
        </div>
        <div class="space-y-4">
            <x-next-action :action="$context->nextAction" />
            <x-workflow-steps :steps="$context->workflowSteps" />
        </div>
    </div>

    @if (! $approvedBoq)
        <x-empty-state
            title="Belum ada progress yang dapat ditampilkan."
            description="Progress tersedia setelah BOQ disetujui."
            action-label="View BOQ"
            :action-url="route('projects.boq.index', $project)"
        />
    @else
        @if (! $canUpdate)
            <div class="mb-4 rounded-xl border border-maroon-300 bg-maroon-100 p-4 text-sm text-maroon-900">
                Anda hanya dapat melihat progress. Update progress dilakukan oleh role yang berwenang.
            </div>
        @endif

        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-maroon-700">Progress per COGS</h3>
        <p class="mb-3 text-sm text-maroon-700">Progress COGS berasal dari item pekerjaan di dalam kelompok tersebut.</p>
        <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($cogsProgress as $row)
                <div class="rounded-xl border border-maroon-200 bg-white p-4 shadow-sm">
                    <p class="text-sm font-medium text-maroon-900">{{ $row['cogs']->name }}</p>
                    <x-progress-bar class="mt-2" :percent="$row['progress']" />
                </div>
            @endforeach
        </div>

        <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-maroon-700">Progress Item Pekerjaan</h3>
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm"
             x-data="{
                modalOpen: false,
                itemId: null,
                itemName: '',
                currentProgress: 0,
                newProgress: 0,
                actionUrl: '',
                open(id, name, progress, url) {
                    this.itemId = id;
                    this.itemName = name;
                    this.currentProgress = progress;
                    this.newProgress = progress;
                    this.actionUrl = url;
                    this.modalOpen = true;
                },
                close() { this.modalOpen = false; }
             }">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs uppercase text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">COGS</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Nilai</th>
                        <th class="px-4 py-3">Bobot</th>
                        <th class="px-4 py-3">Progress</th>
                        <th class="px-4 py-3">Nilai Selesai</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($cogsProgress as $row)
                        @foreach ($row['items'] as $itemRow)
                            @php $item = $itemRow['model']; @endphp
                            <tr>
                                <td class="px-4 py-3">{{ $row['cogs']->name }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $item->name }}</div>
                                    <div class="text-xs text-maroon-700">{{ $item->code }}</div>
                                </td>
                                <td class="px-4 py-3"><x-money :amount="$itemRow['value']" /></td>
                                <td class="px-4 py-3"><x-percent :value="$itemRow['weight']" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span><x-percent :value="$itemRow['progress']" :decimals="1" /></span>
                                        <x-status-badge :status="$item->progressStatus()" />
                                    </div>
                                </td>
                                <td class="px-4 py-3"><x-money :amount="$itemRow['earned']" /></td>
                                <td class="px-4 py-3">
                                    @if ($canUpdate)
                                        @can('updateProgress', $item)
                                            <button type="button" class="font-medium text-maroon-900 hover:underline focus:outline-none focus:ring-2 focus:ring-maroon-600"
                                                    @click="open({{ $item->id }}, @js($item->name), {{ (float) $item->progress_percentage }}, @js(route('progress.update', $item)))">
                                                Update Progress
                                            </button>
                                        @else
                                            <span class="text-maroon-400">—</span>
                                        @endcan
                                    @else
                                        <span class="text-maroon-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-maroon-800/50 p-4" role="dialog" aria-modal="true">
                <div class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" @keydown.escape.window="close()">
                    <h3 class="text-lg font-semibold text-maroon-950">Update Progress</h3>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-maroon-700">Item</dt>
                            <dd class="font-medium text-maroon-950" x-text="itemName"></dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-maroon-700">Current</dt>
                            <dd class="font-medium" x-text="currentProgress + '%'"></dd>
                        </div>
                    </dl>
                    <form method="POST" :action="actionUrl" class="mt-4 space-y-4" x-data="{ submitting: false }" @submit="submitting = true">
                        @csrf
                        @method('PATCH')
                        <div>
                            <x-input-label value="New Progress (%) *" />
                            <input type="number" step="0.01" min="0" max="100" name="progress_percentage"
                                   x-model="newProgress"
                                   class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                            <p class="mt-1 text-xs text-maroon-700">Progress harus berada antara 0 dan 100.</p>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="rounded-md border border-maroon-300 px-4 py-2 text-sm" @click="close()">Cancel</button>
                            <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                                    :disabled="submitting"
                                    x-text="submitting ? 'Processing...' : 'Update Progress'">
                                Update Progress
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
