@php
    /** @var \App\Models\ProjectTerm|null $term */
    $formKey = $term ? (string) $term->id : 'new';
    $useOld = (string) old('term_form') === $formKey;
    $value = fn (string $field, mixed $default = null) => $useOld ? old($field, $default) : $default;
    $idPrefix = 'term-'.$formKey.'-';
    $initialType = $value('calculation_type', $term?->calculation_type?->value ?? \App\Enums\ProjectTermCalculationType::Percentage->value);
    $initialTrigger = $value('trigger_type', $term?->trigger_type?->value ?? \App\Enums\ProjectTermTriggerType::Manual->value);
    $progressTrigger = \App\Enums\ProjectTermTriggerType::Progress->value;
    $dateTrigger = \App\Enums\ProjectTermTriggerType::Date->value;
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6"
     x-data="{
         type: @js($initialType),
         trigger: @js($initialTrigger),
         percentage: @js((string) $value('percentage', $term?->percentage !== null ? (float) $term->percentage : '')),
         contract: {{ (float) ($project->contract_value ?? 0) }},
         get preview() { return Math.round(this.contract * (parseFloat(this.percentage) || 0)) / 100; },
         rupiah(value) { return 'Rp ' + value.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 }); }
     }">
    <input type="hidden" name="term_form" value="{{ $formKey }}">

    <div class="lg:col-span-2">
        <x-input-label :for="$idPrefix.'term_name'" value="Nama Termin *" />
        <x-text-input :id="$idPrefix.'term_name'" name="term_name" class="mt-1 block w-full text-sm" required maxlength="255"
                      :value="$value('term_name', $term?->term_name)" placeholder="Contoh: Uang Muka" />
        @if ($useOld) <x-input-error :messages="$errors->get('term_name')" class="mt-1" /> @endif
    </div>

    <div>
        <x-input-label :for="$idPrefix.'calculation_type'" value="Tipe *" />
        <select id="{{ $idPrefix }}calculation_type" name="calculation_type" x-model="type" required
                class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
            @foreach ($calculationTypes as $calculationType)
                <option value="{{ $calculationType->value }}">{{ $calculationType->label() }}</option>
            @endforeach
        </select>
    </div>

    <div x-show="type === '{{ \App\Enums\ProjectTermCalculationType::Percentage->value }}'">
        <x-input-label :for="$idPrefix.'percentage'" value="Persentase (%) *" />
        <input id="{{ $idPrefix }}percentage" type="number" name="percentage" step="0.0001" min="0.0001" max="100" x-model="percentage"
               :disabled="type !== '{{ \App\Enums\ProjectTermCalculationType::Percentage->value }}'"
               :required="type === '{{ \App\Enums\ProjectTermCalculationType::Percentage->value }}'"
               class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
        <p class="mt-1 text-xs text-maroon-700">= <span x-text="rupiah(preview)"></span></p>
        @if ($useOld) <x-input-error :messages="$errors->get('percentage')" class="mt-1" /> @endif
    </div>

    <div x-show="type === '{{ \App\Enums\ProjectTermCalculationType::FixedAmount->value }}'" x-cloak>
        <x-input-label :for="$idPrefix.'amount'" value="Nilai (Rp) *" />
        <input id="{{ $idPrefix }}amount" type="number" name="amount" step="0.01" min="0.01"
               value="{{ $value('amount', $term && ! $term->isPercentage() ? (float) $term->amount : '') }}"
               :disabled="type !== '{{ \App\Enums\ProjectTermCalculationType::FixedAmount->value }}'"
               :required="type === '{{ \App\Enums\ProjectTermCalculationType::FixedAmount->value }}'"
               class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
        @if ($useOld) <x-input-error :messages="$errors->get('amount')" class="mt-1" /> @endif
    </div>

    <div>
        <x-input-label :for="$idPrefix.'due_date'" x-text="trigger === '{{ $dateTrigger }}' ? 'Target Tanggal *' : 'Target Tanggal'" value="Target Tanggal" />
        <x-date-input :id="$idPrefix.'due_date'" name="due_date" class="mt-1 block w-full text-sm"
                      x-bind:required="trigger === '{{ $dateTrigger }}'"
                      :value="$value('due_date', $term?->due_date?->format('Y-m-d'))" />
        @if ($useOld) <x-input-error :messages="$errors->get('due_date')" class="mt-1" /> @endif
    </div>

    <div>
        <x-input-label :for="$idPrefix.'trigger_type'" value="Syarat Penagihan" />
        <select id="{{ $idPrefix }}trigger_type" name="trigger_type" x-model="trigger"
                class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
            @foreach ($triggerTypes as $triggerType)
                <option value="{{ $triggerType->value }}">{{ $triggerType->label() }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-maroon-700" x-show="trigger === '{{ $dateTrigger }}'" x-cloak>Pemberitahuan muncul saat Target Tanggal tiba.</p>
    </div>

    <div x-show="trigger === '{{ $progressTrigger }}'" x-cloak>
        <x-input-label :for="$idPrefix.'trigger_progress'" value="Progress Minimal (%) *" />
        <input id="{{ $idPrefix }}trigger_progress" type="number" name="trigger_progress" step="0.01" min="0.01" max="100"
               value="{{ $value('trigger_progress', $term?->trigger_progress !== null ? (float) $term->trigger_progress : '') }}"
               :disabled="trigger !== '{{ $progressTrigger }}'"
               :required="trigger === '{{ $progressTrigger }}'"
               class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
        <p class="mt-1 text-xs text-maroon-700">Pemberitahuan muncul saat progress project mencapai angka ini.</p>
        @if ($useOld) <x-input-error :messages="$errors->get('trigger_progress')" class="mt-1" /> @endif
    </div>

    <div class="lg:col-span-2">
        <x-input-label :for="$idPrefix.'trigger_value'" value="Keterangan Syarat" />
        <x-text-input :id="$idPrefix.'trigger_value'" name="trigger_value" class="mt-1 block w-full text-sm" maxlength="255"
                      :value="$value('trigger_value', $term?->trigger_value)" placeholder="Contoh: progress fisik 50%" />
    </div>

    <div class="lg:col-span-3">
        <x-input-label :for="$idPrefix.'notes'" value="Catatan" />
        <x-text-input :id="$idPrefix.'notes'" name="notes" class="mt-1 block w-full text-sm"
                      :value="$value('notes', $term?->notes)" />
    </div>
</div>
