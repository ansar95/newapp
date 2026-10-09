<x-app-layout>
    <x-slot name="header">Laporan Finance</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Reports', 'url' => route('reports.index')],
        ['label' => 'Finance', 'url' => null],
    ]" />

    <x-page-header title="Laporan Finance" subtitle="Payment plan, terbayar, dan remaining. Tidak termasuk asset purchase.">
        <a href="{{ route('reports.finance.export', request()->query()) }}"
           class="inline-flex items-center justify-center rounded-md border border-maroon-300 bg-white px-4 py-2 text-sm font-medium text-maroon-800 hover:bg-maroon-50">
            Export CSV
        </a>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Payment Plans" :value="(string) $summary['plans']" />
        <x-stat-card label="Planned" value=""><p class="mt-2 text-2xl font-semibold text-maroon-950"><x-money :amount="$summary['total_planned']" /></p></x-stat-card>
        <x-stat-card label="Paid" value=""><p class="mt-2 text-2xl font-semibold text-maroon-950"><x-money :amount="$summary['total_paid']" /></p></x-stat-card>
        <x-stat-card label="Remaining" value=""><p class="mt-2 text-2xl font-semibold text-maroon-950"><x-money :amount="$summary['total_remaining']" /></p></x-stat-card>
    </div>

    <form method="GET" action="{{ route('reports.finance') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="w-full sm:w-64">
            <x-input-label for="project_id" value="Project" />
            <select id="project_id" name="project_id" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua project</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) $filters['project_id'] === (string) $project->id)>
                        {{ $project->code }} — {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="w-full sm:w-48">
            <x-input-label for="payment_status" value="Status Payment" />
            <select id="payment_status" name="payment_status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Semua</option>
                @foreach ($paymentStatuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($filters['payment_status'] === $statusOption->value)>{{ $statusOption->value }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Filter</button>
    </form>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm">
        @if ($rows->isEmpty())
            <div class="p-2"><x-empty-state title="Belum ada payment plan untuk filter ini." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">BOQ</th>
                        <th class="px-4 py-3">Reference</th>
                        <th class="px-4 py-3">Planned</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Remaining</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-maroon-950">{{ $row['project_code'] }}</p>
                                <p class="text-xs text-maroon-700">{{ $row['project_name'] }}</p>
                            </td>
                            <td class="px-4 py-3">{{ $row['boq_code'] }}</td>
                            <td class="px-4 py-3">{{ $row['reference_code'] }}</td>
                            <td class="px-4 py-3"><x-money :amount="$row['planned_amount']" /></td>
                            <td class="px-4 py-3"><x-money :amount="$row['paid_amount']" /></td>
                            <td class="px-4 py-3"><x-money :amount="$row['remaining']" /></td>
                            <td class="px-4 py-3"><x-status-badge :status="$row['status']" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
