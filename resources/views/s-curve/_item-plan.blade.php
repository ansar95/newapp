@php
    $mode = $locked ? 'rebaseline' : 'initial';
    $editable = $canManage;
@endphp

<div class="rounded-xl border border-maroon-200 bg-white p-4 sm:p-5">
    <h3 class="font-semibold text-maroon-950">
        {{ $locked ? 'Jadwal item rencana awal' : 'Rencana awal dari item BOQ' }}
    </h3>
    <p class="mt-1 text-sm text-maroon-700">
        Bobot tiap item adalah nilai item dibagi total BOQ approved. Tanggal mulai dan selesai menyebar bobot itu menjadi rencana awal.         Progress item tidak mengubah rencana.
    </p>

    @if ($locked || ! $editable)
        <div class="mt-4 overflow-x-auto rounded-lg border border-maroon-100">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 bg-white text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-3 py-2">Item</th>
                        <th class="px-3 py-2">Bobot</th>
                        <th class="px-3 py-2">Progress</th>
                        <th class="px-3 py-2">Mulai</th>
                        <th class="px-3 py-2">Selesai</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($planItems as $group)
                        <tr class="bg-maroon-50">
                            <td class="px-3 py-2 font-semibold text-maroon-900" colspan="5">{{ $group['cogs'] }}</td>
                        </tr>
                        @foreach ($group['items'] as $item)
                            <tr class="border-t border-maroon-100">
                                <td class="px-3 py-2">{{ $item['code'] }} — {{ $item['name'] }}</td>
                                <td class="px-3 py-2"><x-percent :value="$item['weight']" /></td>
                                <td class="px-3 py-2"><x-percent :value="$item['progress']" /></td>
                                <td class="px-3 py-2">{{ $item['start'] ? date_id($item['start']) : '—' }}</td>
                                <td class="px-3 py-2">{{ $item['finish'] ? date_id($item['finish']) : '—' }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($editable)
        <form method="POST" action="{{ route('projects.s-curve.plan.store', $project) }}" class="mt-4">
            @csrf
            <input type="hidden" name="kind" value="{{ $mode }}">
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            @if ($locked)
                <h4 class="font-semibold text-maroon-950">Rebaseline dari tanggal item</h4>
                <p class="mt-1 text-sm text-maroon-700">Ubah tanggal kerja item untuk rencana baru. Rencana awal di atas tetap tersimpan.</p>
            @endif
            <div class="mt-4 overflow-x-auto rounded-lg border border-maroon-100">
                <table class="min-w-full text-sm">
                    <thead class="sticky top-0 bg-white text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                        <tr>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Bobot</th>
                            <th class="px-3 py-2">Progress</th>
                            <th class="px-3 py-2">Mulai</th>
                            <th class="px-3 py-2">Selesai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($planItems as $group)
                            <tr class="bg-maroon-50">
                                <td class="px-3 py-2 font-semibold text-maroon-900" colspan="5">{{ $group['cogs'] }}</td>
                            </tr>
                            @foreach ($group['items'] as $item)
                                <tr class="border-t border-maroon-100">
                                    <td class="px-3 py-2">{{ $item['code'] }} — {{ $item['name'] }}</td>
                                    <td class="px-3 py-2"><x-percent :value="$item['weight']" /></td>
                                    <td class="px-3 py-2"><x-percent :value="$item['progress']" /></td>
                                    <td class="px-3 py-2">
                                        <x-date-input name="items[{{ $item['id'] }}][start]" required
                                                     :value="old('items.'.$item['id'].'.start', $item['start'])"
                                                     class="text-sm" />
                                    </td>
                                    <td class="px-3 py-2">
                                        <x-date-input name="items[{{ $item['id'] }}][finish]" required
                                                     :value="old('items.'.$item['id'].'.finish', $item['finish'])"
                                                     class="text-sm" />
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($locked)
                <div class="mt-4">
                    <x-input-label for="rebaseline-reason" value="Alasan perubahan" />
                    <textarea id="rebaseline-reason" name="reason" rows="2" class="mt-1 block w-full rounded-md border-maroon-300 text-sm text-maroon-950 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('reason') }}</textarea>
                </div>
            @endif
            <button type="submit" class="mt-4 rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">
                {{ $locked ? 'Tetapkan rebaseline' : 'Tetapkan rencana awal' }}
            </button>
        </form>
    @endif
</div>
