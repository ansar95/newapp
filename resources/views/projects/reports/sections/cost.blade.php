@php
    $cost = $report->cost();
@endphp

<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card label="Nilai BOQ" :value="rupiah($cost['summary']['boq_value'])" />
        <x-stat-card label="Total Actual Cost" :value="rupiah($cost['summary']['actual'])"
                     :hint="'BMHP '.rupiah($cost['summary']['bmhp']).' · Sewa '.rupiah($cost['summary']['rental']).' · Payment '.rupiah($cost['summary']['payment'])" />
        <x-stat-card label="Sisa Budget" :value="rupiah($cost['summary']['variance'])"
                     :hint="percent_format($cost['summary']['percentage'], 1).' terpakai'" />
        <x-stat-card label="Item Over Budget" :value="(string) $cost['summary']['over_budget']" />
    </div>

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm print:shadow-none">
        <h3 class="border-b border-maroon-100 px-4 py-3 text-base font-semibold text-maroon-950">Biaya per Item BOQ</h3>
        @if ($cost['rows']->isEmpty())
            <div class="p-2"><x-empty-state title="Belum ada BOQ approved." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-2">Item</th>
                        <th class="px-4 py-2 text-right">Nilai BOQ</th>
                        <th class="px-4 py-2 text-right">BMHP</th>
                        <th class="px-4 py-2 text-right">Sewa Alat</th>
                        <th class="px-4 py-2 text-right">Payment</th>
                        <th class="px-4 py-2 text-right">Actual</th>
                        <th class="px-4 py-2 text-right">Sisa</th>
                        <th class="px-4 py-2 text-right">Terpakai</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Progress</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($cost['rows'] as $row)
                        <tr class="break-inside-avoid">
                            <td class="px-4 py-2">
                                <div class="font-medium text-maroon-950">{{ $row['item']->name }}</div>
                                <div class="text-xs text-maroon-700">{{ $row['boq_code'] }} · {{ $row['cogs']?->code }} · {{ $row['item']->code }}</div>
                            </td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$row['boq_value']" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$row['bmhp']" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$row['rental']" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$row['payment']" /></td>
                            <td class="px-4 py-2 text-right whitespace-nowrap font-semibold"><x-money :amount="$row['actual']" /></td>
                            <td @class(['px-4 py-2 text-right whitespace-nowrap', 'font-semibold text-red-700' => $row['variance'] < 0])><x-money :amount="$row['variance']" /></td>
                            <td class="px-4 py-2 text-right"><x-percent :value="$row['percentage']" :decimals="1" /></td>
                            <td class="px-4 py-2"><x-status-badge :status="$row['status']" /></td>
                            <td class="px-4 py-2 text-right"><x-percent :value="$row['progress']" :decimals="1" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="bg-maroon-50 font-semibold text-maroon-950">
                    <tr>
                        <td class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['boq_value']" /></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['bmhp']" /></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['rental']" /></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['payment']" /></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['actual']" /></td>
                        <td class="px-4 py-2 text-right whitespace-nowrap"><x-money :amount="$cost['summary']['variance']" /></td>
                        <td class="px-4 py-2 text-right"><x-percent :value="$cost['summary']['percentage']" :decimals="1" /></td>
                        <td class="px-4 py-2" colspan="2"></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</div>
