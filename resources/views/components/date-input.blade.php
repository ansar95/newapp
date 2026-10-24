@props(['value' => null])

@php
    $iso = \App\Support\Format::isoDate($value);
    $display = $iso === '' ? '' : \Illuminate\Support\Carbon::parse($iso)->format('d/m/Y');
    $textAttributes = $attributes->except(['name', 'min', 'x-model', 'x-bind:name', 'x-bind:min']);
@endphp

<div
    x-data="dateInput(@js($iso))"
    x-modelable="iso"
    {{ $attributes->only('x-model') }}
    class="relative"
    @click.outside="open = false"
>
    <input
        x-ref="text"
        type="text"
        inputmode="numeric"
        autocomplete="off"
        placeholder="dd/mm/yyyy"
        maxlength="10"
        value="{{ $display }}"
        x-model="text"
        @input="mask()"
        @blur="commitText()"
        {{ $textAttributes->merge(['class' => 'w-full border-maroon-300 pr-10 text-maroon-950 placeholder:text-maroon-400 focus:border-maroon-600 focus:ring-maroon-600 rounded-md shadow-sm']) }}
    >
    <input
        type="hidden"
        x-ref="stored"
        value="{{ $iso }}"
        :value="iso"
        @if ($attributes->has('name')) name="{{ $attributes->get('name') }}" @endif
        {{ $attributes->only('x-bind:name') }}
    >
    <button
        type="button"
        x-ref="opener"
        aria-label="Pilih tanggal"
        @click="toggle()"
        {{ $attributes->only(['min', 'x-bind:min']) }}
        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-maroon-700 focus:outline-none focus:ring-2 focus:ring-maroon-600"
    >
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1.5A1.5 1.5 0 0 1 18 5.5v11A1.5 1.5 0 0 1 16.5 18h-13A1.5 1.5 0 0 1 2 16.5v-11A1.5 1.5 0 0 1 3.5 4H5V3a1 1 0 0 1 1-1Zm-2 6v8.5h12V8H4Z" clip-rule="evenodd" />
        </svg>
    </button>
    <div
        x-show="open"
        x-cloak
        x-transition
        @keydown.escape.window="open = false"
        class="fixed z-50 w-72 rounded-lg border border-maroon-200 bg-white p-3 shadow-lg"
        :style="panelStyle"
        role="dialog"
        aria-label="Kalender"
    >
        <div class="mb-2 flex items-center justify-between">
            <button type="button" @click="shiftMonth(-1)" aria-label="Bulan sebelumnya" class="rounded-md px-2 py-1 text-maroon-800 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">‹</button>
            <p class="text-sm font-semibold text-maroon-950" x-text="heading()"></p>
            <button type="button" @click="shiftMonth(1)" aria-label="Bulan berikutnya" class="rounded-md px-2 py-1 text-maroon-800 hover:bg-maroon-50 focus:outline-none focus:ring-2 focus:ring-maroon-600">›</button>
        </div>
        <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-maroon-700">
            <template x-for="name in weekdayNames" :key="name">
                <span x-text="name"></span>
            </template>
        </div>
        <div class="mt-1 grid grid-cols-7 gap-1">
            <template x-for="day in calendarDays" :key="day.iso">
                <button
                    type="button"
                    class="rounded-md py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-maroon-600 disabled:cursor-not-allowed"
                    :class="day.selected ? 'bg-maroon-800 text-white' : (day.disabled || ! day.inMonth ? 'text-maroon-300' : 'text-maroon-950 hover:bg-maroon-50')"
                    :disabled="day.disabled"
                    @click="choose(day.iso)"
                    x-text="day.label"
                ></button>
            </template>
        </div>
    </div>
</div>
