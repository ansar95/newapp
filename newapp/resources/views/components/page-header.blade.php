@props([
    'title',
    'subtitle' => null,
])

<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h2 class="text-2xl font-bold tracking-tight text-maroon-950">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-1 max-w-3xl text-sm font-medium leading-relaxed text-maroon-700">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
