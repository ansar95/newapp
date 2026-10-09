@props(['items' => []])

@php
    /** @var list<array{label: string, url: string|null}> $items */
@endphp

<nav class="mb-4 text-sm text-maroon-700" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <li class="text-maroon-200">/</li>
            @endif
            <li>
                @if (! empty($item['url']))
                    <a href="{{ $item['url'] }}" class="hover:text-maroon-900 hover:underline">{{ $item['label'] }}</a>
                @else
                    <span class="font-medium text-maroon-900">{{ $item['label'] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
