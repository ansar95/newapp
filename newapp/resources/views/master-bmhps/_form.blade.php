<div class="space-y-4">
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="code" value="Code *" />
            <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code', $masterBmhp?->code ?? $suggestedCode)" required maxlength="50" />
            <x-input-error :messages="$errors->get('code')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="unit" value="Satuan *" />
            <x-text-input id="unit" name="unit" class="mt-1 block w-full" :value="old('unit', $masterBmhp?->unit)" required maxlength="50" placeholder="Zak, Lembar, Batang, Box" />
            <x-input-error :messages="$errors->get('unit')" class="mt-1" />
        </div>
    </div>
    <div>
        <x-input-label for="name" value="Nama BMHP *" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', $masterBmhp?->name)" required placeholder="Gypsum 9mm" />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="category" value="Kategori *" />
            <select id="category" name="category" required class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                <option value="">Pilih kategori</option>
                @foreach ($categories as $categoryOption)
                    <option value="{{ $categoryOption }}" @selected(old('category', $masterBmhp?->category) === $categoryOption)>{{ $categoryOption }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="status" value="Status *" />
            <select id="status" name="status" required class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                @foreach ($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected(old('status', $masterBmhp?->status?->value ?? \App\Enums\MasterBmhpStatus::Active->value) === $statusOption->value)>
                        {{ $statusOption->label() }}
                    </option>
                @endforeach
            </select>
            <x-helper-text class="mt-1" text="BMHP INACTIVE tidak bisa dipilih pada pembelian baru, tetapi riwayat tetap tersimpan." />
            <x-input-error :messages="$errors->get('status')" class="mt-1" />
        </div>
    </div>
    <div>
        <x-input-label for="description" value="Deskripsi" />
        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-maroon-300 shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('description', $masterBmhp?->description) }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>
</div>
