<x-app-layout>
    <x-slot name="header">Reports</x-slot>

    <x-page-header
        title="Laporan"
        subtitle="Ringkasan project, keuangan, progress, dan asset. Data bersifat baca-saja dan tidak mengubah workflow BOQ."
    />

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Projects" :value="(string) $summary['projects_total']" :hint="'Active '.$summary['projects_active'].' · Completed '.$summary['projects_completed']" />
        <x-stat-card label="Nilai BOQ" value="">
            <p class="mt-2 text-2xl font-semibold tracking-tight text-maroon-950 tabular-nums"><x-money :amount="$summary['total_boq']" /></p>
            <p class="mt-1 text-xs text-maroon-700">Payment Keluar <x-money :amount="$summary['total_paid']" /></p>
        </x-stat-card>
        <x-stat-card label="Avg Progress" :value="$summary['avg_progress'].'%'" hint="Rata-rata project dengan BOQ approved" />
        <x-stat-card label="Assets" :value="(string) ($summary['assets_available'] + $summary['assets_in_use'] + $summary['assets_requested'])" :hint="'Available '.$summary['assets_available'].' · In Use '.$summary['assets_in_use']" />
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <a href="{{ route('reports.projects') }}" class="group rounded-2xl border border-maroon-200 bg-white p-6 shadow-sm transition hover:border-maroon-300 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Laporan</p>
            <h3 class="mt-2 text-lg font-semibold text-maroon-950 group-hover:underline">Projects</h3>
            <p class="mt-2 text-sm text-maroon-700">Status project, progress, dan nilai BOQ. Filter + export CSV.</p>
        </a>
        <a href="{{ route('reports.finance') }}" class="group rounded-2xl border border-maroon-200 bg-white p-6 shadow-sm transition hover:border-maroon-300 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Laporan</p>
            <h3 class="mt-2 text-lg font-semibold text-maroon-950 group-hover:underline">Finance</h3>
            <p class="mt-2 text-sm text-maroon-700">Payment plan, terbayar, dan remaining per project/BOQ.</p>
        </a>
        <a href="{{ route('reports.progress') }}" class="group rounded-2xl border border-maroon-200 bg-white p-6 shadow-sm transition hover:border-maroon-300 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Laporan</p>
            <h3 class="mt-2 text-lg font-semibold text-maroon-950 group-hover:underline">Progress</h3>
            <p class="mt-2 text-sm text-maroon-700">Progress keseluruhan, earned value, dan distribusi capaian.</p>
        </a>
        <a href="{{ route('reports.variance') }}" class="group rounded-2xl border border-maroon-200 bg-white p-6 shadow-sm transition hover:border-maroon-300 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Laporan</p>
            <h3 class="mt-2 text-lg font-semibold text-maroon-950 group-hover:underline">Variance</h3>
            <p class="mt-2 text-sm text-maroon-700">Project Over Budget, Mendekati, dan Normal berdasarkan actual cost terhadap BOQ.</p>
        </a>
        <a href="{{ route('reports.assets') }}" class="group rounded-2xl border border-maroon-200 bg-white p-6 shadow-sm transition hover:border-maroon-300 hover:shadow-md">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Laporan</p>
            <h3 class="mt-2 text-lg font-semibold text-maroon-950 group-hover:underline">Assets</h3>
            <p class="mt-2 text-sm text-maroon-700">Status master asset, kondisi, dan project yang sedang memakai.</p>
        </a>
    </div>
</x-app-layout>
