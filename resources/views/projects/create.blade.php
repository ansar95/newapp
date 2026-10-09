<x-app-layout>
    <x-slot name="header">New Project</x-slot>

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <form method="POST" action="{{ route('projects.store') }}" class="space-y-4"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <div>
                <x-input-label for="code" value="Project Code *" />
                <x-text-input id="code" name="code" class="mt-1 block w-full" :value="old('code')" required placeholder="PRJ-010" />
                <x-input-error :messages="$errors->get('code')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="name" value="Project Name *" />
                <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required placeholder="Engineering & Installation Project" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="client_name" value="Client Name *" />
                <x-text-input id="client_name" name="client_name" class="mt-1 block w-full" :value="old('client_name')" required placeholder="PT Nusantara Teknik" />
                <x-input-error :messages="$errors->get('client_name')" class="mt-1" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="contract_number" value="Nomor Kontrak" />
                    <x-text-input id="contract_number" name="contract_number" class="mt-1 block w-full" :value="old('contract_number')" maxlength="100" placeholder="001/KTR/PRJ/X/2026" />
                    <x-input-error :messages="$errors->get('contract_number')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="contract_value" value="Nilai Kontrak (Rp)" />
                    <x-text-input id="contract_value" name="contract_value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('contract_value')" placeholder="1500000000" />
                    <x-input-error :messages="$errors->get('contract_value')" class="mt-1" />
                </div>
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-maroon-300 shadow-sm focus:border-maroon-600 focus:ring-maroon-600" placeholder="Ringkasan singkat lingkup pekerjaan">{{ old('description') }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="start_date" value="Start Date" />
                    <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" />
                </div>
                <div>
                    <x-input-label for="target_end_date" value="Target End Date" />
                    <x-text-input id="target_end_date" name="target_end_date" type="date" class="mt-1 block w-full" :value="old('target_end_date')" />
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('projects.index') }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Save Project'">
                    Save Project
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
