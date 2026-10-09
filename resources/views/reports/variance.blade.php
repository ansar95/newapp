<x-app-layout>
    <x-slot name="header">Laporan Variance</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Variance', 'url' => null],
    ]" />

    <x-page-header
        title="Laporan Variance Project"
        subtitle="Actual cost (BMHP + Payment PAID) dibandingkan nilai BOQ approved. Project ACTIVE dan COMPLETED, diurutkan dari persentase actual tertinggi."
    />

    @php
        $counts = [
            '' => $summary['total'],
            \App\Enums\ProjectVarianceStatus::OverBudget->value => $summary['over_budget'],
            \App\Enums\ProjectVarianceStatus::Warning->value => $summary['warning'],
            \App\Enums\ProjectVarianceStatus::Normal->value => $summary['normal'],
        ];
        $badgeClasses = [
            \App\Enums\ProjectVarianceStatus::OverBudget->value => 'bg-red-700 text-white ring-red-800',
            \App\Enums\ProjectVarianceStatus::Warning->value => 'bg-maroon-100 text-maroon-950 ring-maroon-400',
            \App\Enums\ProjectVarianceStatus::Normal->value => 'bg-white text-maroon-800 ring-maroon-300',
        ];
    @endphp

    <nav class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filter kategori variance">
        <a href="{{ route('reports.variance') }}"
           @class([
               'rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md',
               'border-maroon-800 ring-2 ring-maroon-800' => $filters['variance'] === '',
               'border-maroon-200' => $filters['variance'] !== '',
           ])>
            <p class="text-xs font-bold uppercase tracking-wide text-maroon-700">Semua Project</p>
            <p class="mt-2 text-2xl font-bold tabular-nums text-maroon-950">{{ $counts[''] }}</p>
            <p class="mt-1 text-xs font-medium text-maroon-700">Dengan BOQ approved</p>
        </a>
        @foreach ($varianceStatuses as $varianceStatus)
            <a href="{{ route('reports.variance', ['variance' => $varianceStatus->value]) }}"
               @class([
                   'rounded-2xl border bg-white p-5 shadow-sm transition hover:shadow-md',
                   'border-maroon-800 ring-2 ring-maroon-800' => $filters['variance'] === $varianceStatus->value,
                   'border-maroon-200' => $filters['variance'] !== $varianceStatus->value,
               ])>
                <p @class([
                    'text-xs font-bold uppercase tracking-wide',
                    'text-red-700' => $varianceStatus === \App\Enums\ProjectVarianceStatus::OverBudget,
                    'text-maroon-700' => $varianceStatus !== \App\Enums\ProjectVarianceStatus::OverBudget,
                ])>{{ $varianceStatus->label() }}</p>
                <p class="mt-2 text-2xl font-bold tabular-nums text-maroon-950">{{ $counts[$varianceStatus->value] }}</p>
                <p class="mt-1 text-xs font-medium text-maroon-700">{{ $varianceStatus->description() }}</p>
            </a>
        @endforeach
    </nav>

    <div class="mb-4 grid gap-3 rounded-xl border border-maroon-200 bg-white p-4 text-sm sm:grid-cols-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total BOQ</p>
            <p class="mt-1 font-semibold text-maroon-950"><x-money :amount="$summary['total_boq']" /></p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Actual Cost</p>
            <p class="mt-1 font-semibold text-maroon-950"><x-money :amount="$summary['total_actual']" /></p>
        </div>
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Variance</p>
            <p @class(['mt-1 font-semibold', 'text-red-700' => $summary['total_variance'] < 0, 'text-maroon-950' => $summary['total_variance'] >= 0])>
                <x-money :amount="$summary['total_variance']" />
            </p>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm">
        @if ($rows->isEmpty())
            <div class="p-2">
                <x-empty-state
                    :title="$filters['variance'] === '' ? 'Belum ada project ACTIVE/COMPLETED dengan BOQ approved.' : 'Tidak ada project dalam kategori ini.'"
                    description="Variance dihitung dari BOQ approved dikurangi actual cost (BMHP terkonfirmasi + Payment PAID)." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="min-w-56 px-4 py-3">Project</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Nilai BOQ</th>
                        <th class="px-4 py-3 text-right">Actual Cost</th>
                        <th class="px-4 py-3 text-right">Variance</th>
                        <th class="min-w-40 px-4 py-3">Actual % BOQ</th>
                        <th class="px-4 py-3 text-right">Progress</th>
                        <th class="px-4 py-3">Kategori</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($rows as $row)
                        @php($isOver = $row['variance_status'] === \App\Enums\ProjectVarianceStatus::OverBudget)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $row['project']) }}" class="font-medium text-maroon-950 hover:underline">{{ $row['code'] }}</a>
                                <span class="block text-xs text-maroon-700">{{ $row['name'] }}</span>
                                <span class="block text-xs text-maroon-700">{{ $row['client_name'] }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-status-badge :status="$row['status']" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$row['boq_value']" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <x-money :amount="$row['actual_cost']" />
                                <span class="block text-xs text-maroon-700">BMHP <x-money :amount="$row['bmhp_actual']" /></span>
                                <span class="block text-xs text-maroon-700">Payment <x-money :amount="$row['payment_actual']" /></span>
                            </td>
                            <td @class(['px-4 py-3 text-right whitespace-nowrap font-semibold', 'text-red-700' => $isOver, 'text-maroon-950' => ! $isOver])>
                                <x-money :amount="$row['variance']" />
                            </td>
                            <td class="px-4 py-3">
                                <x-progress-bar :percent="min(100, $row['actual_percentage'])" :show-label="false" />
                                <p @class(['mt-1 text-xs font-semibold', 'text-red-700' => $isOver, 'text-maroon-800' => ! $isOver])>
                                    <x-percent :value="$row['actual_percentage']" :decimals="1" />
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap"><x-percent :value="$row['progress']" :decimals="1" /></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold ring-1 ring-inset {{ $badgeClasses[$row['variance_status']->value] }}">
                                    {{ $row['variance_status']->label() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
