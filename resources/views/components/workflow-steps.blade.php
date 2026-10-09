@props(['steps'])

@php
    /** @var list<array{key: string, label: string, state: string, detail: string|null}> $steps */
@endphp

<div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-maroon-700">Workflow</h3>

    {{-- Desktop horizontal --}}
    <ol class="hidden items-stretch gap-0 lg:flex" aria-label="Project workflow stages">
        @foreach ($steps as $index => $step)
            <li class="flex min-w-0 flex-1 items-center">
                <div class="flex min-w-0 flex-col items-center text-center">
                    @if ($step['state'] === 'done')
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-maroon-800 text-xs font-bold text-white" aria-label="{{ $step['label'] }} completed">✓</span>
                    @elseif ($step['state'] === 'current')
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-maroon-800 text-xs font-bold text-white ring-4 ring-maroon-200" aria-current="step">{{ $index + 1 }}</span>
                    @else
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-maroon-300 text-xs font-semibold text-maroon-400">{{ $index + 1 }}</span>
                    @endif
                    <p class="mt-2 text-xs font-semibold text-maroon-950">{{ $step['label'] }}</p>
                    @if ($step['detail'])
                        <p class="mt-0.5 text-[11px] text-maroon-700">{{ str_replace('_', ' ', $step['detail']) }}</p>
                    @endif
                </div>
                @if ($index < count($steps) - 1)
                    <div class="mx-2 mb-6 h-0.5 flex-1 {{ $step['state'] === 'done' ? 'bg-maroon-100' : 'bg-maroon-200' }}" aria-hidden="true"></div>
                @endif
            </li>
        @endforeach
    </ol>

    {{-- Mobile vertical --}}
    <ol class="flex flex-col gap-0 lg:hidden" aria-label="Project workflow stages">
        @foreach ($steps as $index => $step)
            <li class="flex items-start gap-3">
                <div class="flex w-8 flex-col items-center">
                    @if ($step['state'] === 'done')
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-maroon-800 text-xs font-bold text-white">✓</span>
                    @elseif ($step['state'] === 'current')
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-maroon-800 text-xs font-bold text-white">{{ $index + 1 }}</span>
                    @else
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-maroon-300 text-xs text-maroon-400">{{ $index + 1 }}</span>
                    @endif
                    @if ($index < count($steps) - 1)
                        <span class="my-1 h-5 w-px bg-maroon-200"></span>
                    @endif
                </div>
                <div class="pb-4 pt-1">
                    <p class="text-sm font-semibold text-maroon-950">{{ $step['label'] }}</p>
                    @if ($step['detail'])
                        <p class="text-xs text-maroon-700">{{ str_replace('_', ' ', $step['detail']) }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</div>
