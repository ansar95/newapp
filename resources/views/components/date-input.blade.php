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
    <button type="button" class="absolute inset-y-0 right-0 flex items-center px-2 text-maroon-700" @click="openPicker()" tabindex="-1" aria-label="Pilih tanggal">
        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M6 2a1 1 0 0 1 1 1v1h6V3a1 1 0 1 1 2 0v1h1.5A1.5 1.5 0 0 1 18 5.5v11A1.5 1.5 0 0 1 16.5 18h-13A1.5 1.5 0 0 1 2 16.5v-11A1.5 1.5 0 0 1 3.5 4H5V3a1 1 0 0 1 1-1Zm-2 6v8.5h12V8H4Z" clip-rule="evenodd" />
        </svg>
    </button>
    <input
        x-ref="picker"
        type="date"
        tabindex="-1"
        aria-hidden="true"
        class="pointer-events-none absolute h-0 w-0 opacity-0"
        :value="iso"
        @change="applyIso($event.target.value)"
        {{ $attributes->only(['min', 'x-bind:min']) }}
    >
</div>
