<x-app-layout>
    <x-slot name="header">Approval BOQ</x-slot>

    <x-page-header
        title="Approval BOQ"
        subtitle="Daftar BOQ yang sudah diajukan dan menunggu review serta approval. Yang paling lama menunggu tampil paling atas."
    />

    <div class="overflow-hidden rounded-2xl border border-maroon-200 bg-white shadow-sm">
        @if ($pendingBoqs->isEmpty())
            <div class="p-2">
                <x-empty-state
                    title="Tidak ada BOQ yang menunggu approval."
                    description="BOQ akan muncul di sini setelah diajukan (Submit) oleh tim BOQ / Cost Control."
                />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">BOQ</th>
                        <th class="px-4 py-3">Project</th>
                        <th class="px-4 py-3">Nilai BOQ</th>
                        <th class="px-4 py-3">Diajukan</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-maroon-100">
                    @foreach ($pendingBoqs as $boq)
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-maroon-950">{{ $boq->code }}</p>
                                <p class="text-xs text-maroon-700">Revision {{ str_pad((string) $boq->revision_number, 2, '0', STR_PAD_LEFT) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('projects.show', $boq->project) }}" class="font-medium text-maroon-900 hover:underline">
                                    {{ $boq->project->code }} — {{ $boq->project->name }}
                                </a>
                                <p class="text-xs text-maroon-700">{{ $boq->project->client_name }}</p>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap"><x-money :amount="$boq->totalAmount()" /></td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <x-date-display :value="$boq->submitted_at" />
                                @if ($boq->submitter)
                                    <p class="text-xs text-maroon-700">oleh {{ $boq->submitter->name }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <a href="{{ route('boqs.review', $boq) }}"
                                   class="inline-flex items-center rounded-md bg-maroon-800 px-3 py-1.5 text-sm font-semibold text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
                                    Review & Approve
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
