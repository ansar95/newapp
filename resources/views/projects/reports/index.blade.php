<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Laporan</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Laporan', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    @php
        $periodQuery = array_filter(['from' => $filters['from'], 'to' => $filters['to']]);
        $sectionQuery = array_filter([
            'section' => $section->value,
            ...$periodQuery,
            'type' => $section === \App\Enums\ProjectReportSection::Procurement ? $filters['type'] : null,
            'status' => $section === \App\Enums\ProjectReportSection::Procurement ? $filters['status'] : null,
        ]);
    @endphp

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Jenis laporan">
        @foreach ($sections as $sectionOption)
            <a href="{{ route('projects.reports.index', [$project, 'section' => $sectionOption->value, ...$periodQuery]) }}"
               @class(['rounded-md px-3 py-1.5 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-maroon-600', 'bg-maroon-800 text-white' => $section === $sectionOption, 'border border-maroon-200 bg-white text-maroon-800 hover:bg-maroon-50' => $section !== $sectionOption])>
                {{ $sectionOption->label() }}
            </a>
        @endforeach
    </nav>

    <x-page-header :title="'Laporan '.$section->label()" :subtitle="$section->description()">
        <a href="{{ route('projects.reports.export', [$project, ...$sectionQuery]) }}"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Export CSV
        </a>
        <a href="{{ route('projects.reports.print', [$project, ...$sectionQuery]) }}" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Cetak / PDF
        </a>
        <a href="{{ route('projects.reports.print', [$project, 'section' => 'all', ...$periodQuery]) }}" target="_blank" rel="noopener"
           class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
            Cetak Laporan Lengkap
        </a>
    </x-page-header>

    @if ($section->usesPeriod())
        <form method="GET" action="{{ route('projects.reports.index', $project) }}"
              class="mb-6 flex flex-col gap-3 rounded-xl border border-maroon-200 bg-white p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <input type="hidden" name="section" value="{{ $section->value }}">
            <div class="w-full sm:w-44">
                <x-input-label for="from" value="Dari Tanggal" />
                <x-text-input id="from" name="from" type="date" class="mt-1 block w-full text-sm" :value="$filters['from']" />
            </div>
            <div class="w-full sm:w-44">
                <x-input-label for="to" value="Sampai Tanggal" />
                <x-text-input id="to" name="to" type="date" class="mt-1 block w-full text-sm" :value="$filters['to']" />
            </div>
            @if ($section === \App\Enums\ProjectReportSection::Procurement)
                <div class="w-full sm:w-40">
                    <x-input-label for="type" value="Jenis" />
                    <select id="type" name="type" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">Semua</option>
                        @foreach (\App\Enums\ProcurementType::cases() as $typeOption)
                            <option value="{{ $typeOption->value }}" @selected($filters['type'] === $typeOption->value)>{{ $typeOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-40">
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">Semua</option>
                        @foreach (\App\Enums\ProcurementStatus::cases() as $statusOption)
                            <option value="{{ $statusOption->value }}" @selected($filters['status'] === $statusOption->value)>{{ $statusOption->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="flex gap-2">
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Terapkan</button>
                @if ($report->hasPeriod() || $filters['type'] !== '' || $filters['status'] !== '')
                    <a href="{{ route('projects.reports.index', [$project, 'section' => $section->value]) }}"
                       class="rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">Reset</a>
                @endif
            </div>
            <x-helper-text class="w-full" :text="'Periode: '.$report->periodLabel().'. Filter tanggal berlaku untuk laporan Permintaan & Pembelian, Pembayaran, Penagihan, dan Aset.'" />
        </form>
    @else
        <x-helper-text class="mb-6" text="Laporan ini menampilkan posisi terkini (snapshot), tidak dipengaruhi filter periode." />
    @endif

    @include('projects.reports.sections.'.$section->value, ['report' => $report])
</x-app-layout>
