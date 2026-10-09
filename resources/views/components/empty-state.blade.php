@props([
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionUrl' => null,
])

<div class="rounded-xl border border-dashed border-maroon-300 bg-white px-6 py-12 text-center">
    <h3 class="text-base font-bold text-maroon-950">{{ $title }}</h3>
    @if ($description)
        <p class="mx-auto mt-2 max-w-md text-sm font-medium text-maroon-700">{{ $description }}</p>
    @endif
    @if ($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}"
           class="mt-5 inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-bold text-white hover:bg-maroon-900 focus:outline-none focus:ring-2 focus:ring-maroon-600 focus:ring-offset-2">
            {{ $actionLabel }}
        </a>
    @elseif ($slot->isNotEmpty())
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
