<div
    x-data="{
        source: '{{ old('source_type', $asset?->source_type?->value ?? \App\Enums\AssetSourceType::Existing->value) }}'
    }"
    class="space-y-4"
>
    <div>
        <x-input-label for="code" value="Code *" />
        <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $asset?->code)" required placeholder="AST-0001" />
        <x-input-error :messages="$errors->get('code')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="name" value="Name *" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $asset?->name)" required placeholder="Laptop Dell Latitude 5450" />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div>
        <x-input-label for="category" value="Category *" />
        <select id="category" name="category" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600" required>
            <option value="">Pilih kategori</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(old('category', $asset?->category) === $category)>{{ $category }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('category')" class="mt-1" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="brand" value="Brand" />
            <x-text-input id="brand" name="brand" class="mt-1 block w-full" :value="old('brand', $asset?->brand)" placeholder="Dell" />
            <x-input-error :messages="$errors->get('brand')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="model" value="Model" />
            <x-text-input id="model" name="model" class="mt-1 block w-full" :value="old('model', $asset?->model)" placeholder="Latitude 5450" />
            <x-input-error :messages="$errors->get('model')" class="mt-1" />
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="unit" value="Unit" />
            <x-text-input id="unit" name="unit" class="mt-1 block w-full" :value="old('unit', $asset?->unit ?? 'Unit')" placeholder="Unit" />
            <x-input-error :messages="$errors->get('unit')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="source_type" value="Source *" />
            <select id="source_type" name="source_type" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600"
                    x-model="source" required @disabled($asset?->status === \App\Enums\AssetStatus::InUse)>
                @foreach ($sources as $sourceOption)
                    <option value="{{ $sourceOption->value }}">{{ $sourceOption->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('source_type')" class="mt-1" />
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="condition" value="Kondisi *" />
            <select id="condition" name="condition" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600" required>
                @foreach ($conditions as $conditionOption)
                    <option value="{{ $conditionOption->value }}"
                        @selected(old('condition', $asset?->condition?->value ?? \App\Enums\AssetCondition::Good->value) === $conditionOption->value)>
                        {{ $conditionOption->label() }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('condition')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="purchase_year" value="Tahun Pembelian" />
            <x-text-input id="purchase_year" name="purchase_year" type="number" min="1990" max="{{ now()->year + 1 }}"
                          class="mt-1 block w-full"
                          :value="old('purchase_year', $asset?->purchase_year)"
                          placeholder="{{ now()->year }}" />
            <x-input-error :messages="$errors->get('purchase_year')" class="mt-1" />
        </div>
    </div>

    @if ($asset)
        <div>
            <x-input-label for="status" value="Status *" />
            <select id="status" name="status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600" required
                    @disabled($asset->status === \App\Enums\AssetStatus::InUse)>
                @foreach ($statuses as $statusOption)
                    @if ($statusOption === \App\Enums\AssetStatus::InUse && $asset->status !== \App\Enums\AssetStatus::InUse)
                        @continue
                    @endif
                    <option value="{{ $statusOption->value }}" @selected(old('status', $asset->status->value) === $statusOption->value)>
                        {{ $statusOption->label() }}
                    </option>
                @endforeach
            </select>
            @if ($asset->status === \App\Enums\AssetStatus::InUse)
                <input type="hidden" name="status" value="{{ \App\Enums\AssetStatus::InUse->value }}">
                <p class="mt-1 text-xs text-maroon-700">Status IN USE hanya berubah melalui release dari project.</p>
            @endif
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>
    @endif

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-maroon-300 shadow-sm focus:border-maroon-600 focus:ring-maroon-600" placeholder="Catatan singkat tentang asset">{{ old('description', $asset?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <div class="rounded-lg border border-maroon-200 bg-maroon-50 p-4" x-show="source === '{{ \App\Enums\AssetSourceType::Purchase->value }}'" x-cloak>
        <h3 class="mb-3 text-sm font-semibold text-maroon-950">Surat Permohonan Pembelian</h3>
        <div class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="request_number" value="Nomor Surat *" />
                    <x-text-input id="request_number" name="request_number" class="mt-1 block w-full"
                                  :value="old('request_number', $asset?->request_number)"
                                  placeholder="SPP-2026-001"
                                  x-bind:required="source === '{{ \App\Enums\AssetSourceType::Purchase->value }}'" />
                    <x-input-error :messages="$errors->get('request_number')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="request_date" value="Tanggal Surat *" />
                    <x-text-input id="request_date" name="request_date" type="date" class="mt-1 block w-full"
                                  :value="old('request_date', $asset?->request_date?->format('Y-m-d'))"
                                  x-bind:required="source === '{{ \App\Enums\AssetSourceType::Purchase->value }}'" />
                    <x-input-error :messages="$errors->get('request_date')" class="mt-1" />
                </div>
            </div>
            @if ($asset)
                <div>
                    <x-input-label for="request_status" value="Status Permohonan" />
                    <select id="request_status" name="request_status" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">—</option>
                        @foreach ($requestStatuses as $requestStatus)
                            <option value="{{ $requestStatus->value }}" @selected(old('request_status', $asset->request_status?->value) === $requestStatus->value)>
                                {{ $requestStatus->label() }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('request_status')" class="mt-1" />
                </div>
            @endif
            <div>
                <x-input-label for="request_description" value="Keterangan Permohonan" />
                <textarea id="request_description" name="request_description" rows="2" class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('request_description', $asset?->request_description) }}</textarea>
                <x-input-error :messages="$errors->get('request_description')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="request_letter_file" value="File Surat (PDF/JPG)" />
                <input id="request_letter_file" type="file" name="request_letter_file" accept=".pdf,.jpg,.jpeg,.png"
                       class="mt-1 block w-full text-sm text-maroon-800 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-800 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white" />
                @if ($asset?->request_letter_file)
                    <p class="mt-1 text-xs text-maroon-700">
                        File saat ini:
                        <a href="{{ route('assets.request-letter', $asset) }}" class="font-medium text-maroon-900 underline">Download</a>
                    </p>
                @endif
                <x-input-error :messages="$errors->get('request_letter_file')" class="mt-1" />
            </div>
        </div>
    </div>
</div>
