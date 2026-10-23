@php
    $signedPercent = function (float|int|null $value): string {
        if ($value === null) {
            return '—';
        }

        return ($value > 0 ? '+' : '').percent_format($value, 2);
    };
    $curveQuery = fn (string $periode) => $returnTo === 'menu'
        ? route('s-curve.index', ['project' => $project->id, 'periode' => $periode])
        : route('projects.s-curve.show', ['project' => $project, 'periode' => $periode]);
    $summary = $series['summary'];
    $draft = $series['draft'];
@endphp

<div class="space-y-6">
    <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">{{ $project->code }}</p>
        <h3 class="text-xl font-semibold text-maroon-950">{{ $project->name }}</h3>
        <p class="mt-1 text-sm text-maroon-700">
            Periode proyek:
            @if ($project->start_date && $project->target_end_date)
                <x-date-display :value="$project->start_date" /> – <x-date-display :value="$project->target_end_date" />
            @else
                belum lengkap
            @endif
        </p>
    </div>

    @if ($series['notices'] !== [])
        <div class="rounded-lg border border-maroon-200 bg-maroon-50 px-4 py-3 text-sm text-maroon-900">
            <ul class="list-disc space-y-1 pl-4">
                @foreach ($series['notices'] as $notice)
                    <li>{{ $notice }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-maroon-200 bg-white p-4">
            <p class="text-xs font-medium text-maroon-700">Rencana awal kumulatif</p>
            <p class="mt-1 text-2xl font-semibold text-maroon-950">{{ $summary['p0'] === null ? '—' : percent_format($summary['p0'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-4">
            <p class="text-xs font-medium text-maroon-700">Rebaseline kumulatif</p>
            <p class="mt-1 text-2xl font-semibold text-maroon-950">{{ $summary['p1'] === null ? '—' : percent_format($summary['p1'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-4">
            <p class="text-xs font-medium text-maroon-700">Aktual kumulatif</p>
            <p class="mt-1 text-2xl font-semibold text-maroon-950">{{ $summary['actual'] === null ? '—' : percent_format($summary['actual'], 2) }}</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-4">
            <p class="text-xs font-medium text-maroon-700">Deviasi</p>
            <p class="mt-1 text-2xl font-semibold text-maroon-950">{{ $signedPercent($summary['deviation']) }}</p>
        </div>
    </div>
    @if ($summary['history_note'])
        <p class="text-sm text-maroon-700">{{ $summary['history_note'] }}</p>
    @endif

    <div class="rounded-xl border border-maroon-200 bg-white p-4 sm:p-5">
        <div class="mb-4 flex flex-wrap gap-2">
            <a href="{{ $curveQuery('week') }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium {{ $series['display']->value === 'week' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800' }}">
                Mingguan
            </a>
            <a href="{{ $curveQuery('month') }}"
               class="rounded-md px-3 py-1.5 text-sm font-medium {{ $series['display']->value === 'month' ? 'bg-maroon-800 text-white' : 'bg-maroon-50 text-maroon-800' }}">
                Bulanan
            </a>
        </div>
        <x-s-curve-chart :points="$series['points']" />
        <p class="mt-3 text-xs text-maroon-700">Rencana awal menyebar bobot nilai item BOQ menurut tanggal kerjanya. Aktual mengikuti progress fisik. Deviasi = aktual kumulatif − rebaseline kumulatif.</p>
    </div>

    @if ($series['history']->isNotEmpty())
        <div class="overflow-x-auto rounded-xl border border-maroon-200 bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Versi</th>
                        <th class="px-4 py-2">Jenis</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">Periode</th>
                        <th class="px-4 py-2">Ditetapkan</th>
                        <th class="px-4 py-2">Alasan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($series['history'] as $baseline)
                        <tr class="border-t border-maroon-100">
                            <td class="px-4 py-2">{{ $baseline->version }}</td>
                            <td class="px-4 py-2">{{ $baseline->kind->label() }}</td>
                            <td class="px-4 py-2">{{ $baseline->status->label() }}</td>
                            <td class="px-4 py-2">{{ $baseline->granularity->label() }}</td>
                            <td class="px-4 py-2"><x-date-display :value="$baseline->established_on" /></td>
                            <td class="px-4 py-2">{{ $baseline->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($planItems === [])
        <p class="text-sm text-maroon-700">Belum ada item pada BOQ berstatus APPROVED. Rencana awal memakai item tersebut.</p>
    @else
        @include('s-curve._item-plan', [
            'project' => $project,
            'planItems' => $planItems,
            'canManage' => $canManage,
            'returnTo' => $returnTo,
            'locked' => $series['initial'] !== null,
        ])
    @endif

    @if ($canManage && $draft)
        <div class="rounded-xl border border-maroon-200 bg-white p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="font-semibold text-maroon-950">{{ $draft->kind->label() }} — draf versi {{ $draft->version }}</h3>
                        <p class="mt-1 text-sm text-maroon-700">Isi persen rencana tiap periode. Jumlah harus 100% saat ditetapkan. Rencana yang sudah ditetapkan tidak berubah.</p>
                    </div>
                    <a href="{{ route('s-curve-baselines.template', $draft) }}" class="text-sm font-medium text-maroon-800 underline">Download template</a>
                </div>
                <form method="POST" action="{{ route('s-curve-baselines.import', $draft) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <div class="min-w-0 flex-1">
                        <x-input-label for="kurva-file" value="Impor Excel atau CSV" />
                        <input id="kurva-file" type="file" name="file" accept=".xlsx,.csv,.txt" class="mt-1 block w-full text-sm text-maroon-900">
                    </div>
                    <x-primary-button>Impor dan tetapkan</x-primary-button>
                </form>
                <form method="POST" action="{{ route('s-curve-baselines.update', $draft) }}" class="mt-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                    <div class="max-h-96 overflow-auto rounded-lg border border-maroon-100">
                        <table class="min-w-full text-sm">
                            <thead class="sticky top-0 bg-white text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                                <tr>
                                    <th class="px-3 py-2">Mulai</th>
                                    <th class="px-3 py-2">Selesai</th>
                                    <th class="px-3 py-2">Rencana %</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($draft->periods as $period)
                                    <tr class="border-t border-maroon-100">
                                        <td class="px-3 py-2"><x-date-display :value="$period->period_start" /></td>
                                        <td class="px-3 py-2"><x-date-display :value="$period->period_end" /></td>
                                        <td class="px-3 py-2">
                                            <input type="number" name="periods[{{ $period->id }}]" min="0" max="100" step="0.0001" required
                                                   value="{{ old('periods.'.$period->id, (float) $period->planned_percent) }}"
                                                   class="w-32 rounded-md border-maroon-300 text-sm text-maroon-950 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4">
                        <x-input-label for="kurva-reason" value="Alasan" />
                        <textarea id="kurva-reason" name="reason" rows="2" class="mt-1 block w-full rounded-md border-maroon-300 text-sm text-maroon-950 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('reason', $draft->reason) }}</textarea>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <button type="submit" name="establish" value="0" class="rounded-md border border-maroon-300 px-4 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Simpan draf</button>
                        <button type="submit" name="establish" value="1" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Tetapkan</button>
                    </div>
                </form>
            </div>
    @endif
</div>
