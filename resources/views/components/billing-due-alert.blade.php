@props(['summary', 'progress', 'project', 'canManageTerms' => false, 'canCreateBilling' => false])

@php
    /** @var \App\Support\ProjectBillingSummary $summary */
    $dueTerms = $summary->dueTerms((float) $progress);
@endphp

@if ($dueTerms->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'rounded-xl border-2 border-maroon-700 bg-maroon-50 p-4 shadow-sm']) }} role="alert">
        <p class="text-sm font-bold text-maroon-950">
            Lakukan penagihan: {{ $dueTerms->count() }} termin sudah memenuhi syarat penagihan.
        </p>
        <ul class="mt-3 space-y-2">
            @foreach ($dueTerms as $term)
                <li class="flex flex-col gap-2 rounded-lg border border-maroon-200 bg-white px-3 py-2 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm">
                        <p class="font-semibold text-maroon-950">{{ $term->displayName() }} — <x-money :amount="$summary->termRemaining($term)" /> belum ditagih</p>
                        <p class="text-xs text-maroon-700">
                            @if ($term->trigger_type === \App\Enums\ProjectTermTriggerType::Progress)
                                Progress project {{ percent_format($progress, 1) }} sudah mencapai syarat {{ percent_format($term->trigger_progress, 2) }}.
                            @else
                                Target tanggal {{ date_id($term->due_date) }} sudah tiba.
                            @endif
                            @unless ($term->isReady())
                                Tandai termin sebagai READY, lalu buat penagihan.
                            @endunless
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        @if ($term->isReady())
                            @if ($canCreateBilling)
                                <a href="{{ route('client-billings.create', [$project, 'term' => $term->id]) }}"
                                   class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-900">Tagih</a>
                            @endif
                        @elseif ($canManageTerms && auth()->user()->can('update', $term))
                            <form method="POST" action="{{ route('project-terms.readiness', $term) }}">
                                @csrf
                                <input type="hidden" name="ready" value="1">
                                <button type="submit" class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-900">Set Ready</button>
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </div>
@endif
