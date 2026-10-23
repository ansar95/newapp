@props(['points' => []])

@php
    $count = count($points);
    $width = 760;
    $height = 280;
    $padLeft = 46;
    $padRight = 12;
    $padTop = 16;
    $padBottom = 36;
    $innerWidth = $width - $padLeft - $padRight;
    $innerHeight = $height - $padTop - $padBottom;
    $xAt = function (int $index) use ($count, $padLeft, $innerWidth): float {
        if ($count <= 1) {
            return $padLeft + ($innerWidth / 2);
        }

        return $padLeft + ($innerWidth * ($index / ($count - 1)));
    };
    $yAt = function (float|int|string|null $value) use ($padTop, $innerHeight): ?float {
        if ($value === null || $value === '') {
            return null;
        }

        $clamped = max(0, min(100, (float) $value));

        return $padTop + ($innerHeight * (1 - ($clamped / 100)));
    };
    $segments = function (string $key) use ($points, $xAt, $yAt): array {
        $drawn = [];
        $current = [];

        foreach ($points as $index => $point) {
            $y = $yAt($point[$key] ?? null);

            if ($y === null) {
                if ($current !== []) {
                    $drawn[] = implode(' ', $current);
                    $current = [];
                }

                continue;
            }

            $current[] = round($xAt($index), 1).','.round($y, 1);
        }

        if ($current !== []) {
            $drawn[] = implode(' ', $current);
        }

        return $drawn;
    };
    $labelStep = $count > 12 ? (int) ceil($count / 8) : 1;
    $selected = 0;

    foreach ($points as $index => $point) {
        if (($point['timing'] ?? null) === 'in_progress') {
            $selected = $index;
            break;
        }
    }

    $series = [
        'p0' => ['label' => 'Rencana awal', 'color' => '#b45309'],
        'p1' => ['label' => 'Rebaseline', 'color' => '#7f1d1d'],
        'actual' => ['label' => 'Aktual', 'color' => '#0f766e'],
    ];
@endphp

@if ($count === 0)
    <p class="text-sm text-maroon-700">Grafik muncul setelah rencana ditetapkan.</p>
@else
    <div x-data="{ selected: {{ $selected }}, points: {{ \Illuminate\Support\Js::from($points) }} }">
        <div class="mb-3 flex flex-wrap gap-4 text-xs font-medium text-maroon-800">
            @foreach ($series as $item)
                <span class="inline-flex items-center gap-2">
                    <span class="inline-block h-0.5 w-6" style="background-color: {{ $item['color'] }}"></span>
                    {{ $item['label'] }}
                </span>
            @endforeach
        </div>
        <svg viewBox="0 0 {{ $width }} {{ $height }}" class="h-auto w-full" role="img" aria-label="Kurva S rencana awal, rebaseline, dan aktual">
            @for ($tick = 0; $tick <= 100; $tick += 25)
                @php $y = $yAt($tick); @endphp
                <line x1="{{ $padLeft }}" y1="{{ $y }}" x2="{{ $width - $padRight }}" y2="{{ $y }}" stroke="#f3d6d6" stroke-width="1"></line>
                <text x="{{ $padLeft - 8 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="11" fill="#7f1d1d">{{ $tick }}%</text>
            @endfor
            @foreach ($series as $key => $item)
                @foreach ($segments($key) as $segment)
                    <polyline fill="none" stroke="{{ $item['color'] }}" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="{{ $segment }}"></polyline>
                @endforeach
            @endforeach
            @foreach ($points as $index => $point)
                @foreach ($series as $key => $item)
                    @php $y = $yAt($point[$key] ?? null); @endphp
                    @if ($y !== null)
                        <circle cx="{{ round($xAt($index), 1) }}" cy="{{ round($y, 1) }}" r="7" fill="transparent" class="cursor-pointer"
                                @click="selected = {{ $index }}"
                                aria-label="{{ $item['label'] }} {{ $point['label'] }}"></circle>
                        <circle cx="{{ round($xAt($index), 1) }}" cy="{{ round($y, 1) }}" r="3.5" fill="{{ $item['color'] }}" pointer-events="none"></circle>
                    @endif
                @endforeach
                @if ($index % $labelStep === 0 || $index === $count - 1)
                    <text x="{{ round($xAt($index), 1) }}" y="{{ $height - 10 }}" text-anchor="middle" font-size="11" fill="#7f1d1d">{{ $point['label'] }}</text>
                @endif
            @endforeach
        </svg>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <label class="block text-sm font-medium text-maroon-800">
                Periode
                <select x-model.number="selected" class="mt-1 block w-full rounded-md border-maroon-300 text-sm text-maroon-950 shadow-sm focus:border-maroon-600 focus:ring-maroon-600 sm:w-48">
                    @foreach ($points as $index => $point)
                        <option value="{{ $index }}">{{ $point['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-maroon-700">Rencana awal</dt>
                    <dd class="font-semibold text-maroon-950" x-text="points[selected].p0 === null ? '—' : Number(points[selected].p0).toFixed(2) + '%'"></dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Rebaseline</dt>
                    <dd class="font-semibold text-maroon-950" x-text="points[selected].p1 === null ? '—' : Number(points[selected].p1).toFixed(2) + '%'"></dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Aktual</dt>
                    <dd class="font-semibold text-maroon-950" x-text="points[selected].actual === null ? '—' : Number(points[selected].actual).toFixed(2) + '%'"></dd>
                </div>
                <div>
                    <dt class="text-maroon-700">Deviasi</dt>
                    <dd class="font-semibold text-maroon-950" x-text="points[selected].deviation === null ? '—' : (points[selected].deviation > 0 ? '+' : '') + Number(points[selected].deviation).toFixed(2) + '%'"></dd>
                </div>
            </dl>
        </div>
    </div>
@endif
