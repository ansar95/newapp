<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Upload Document</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => route('projects.documents.index', $project)],
        ['label' => 'Upload Document', 'url' => null],
    ]" />

    @php
        $initialTypeId = old('document_type_id', $types->count() === 1 ? $types->first()->id : null);
        $initialBillingId = old('client_billing_id', $selectedBillingId);
    @endphp

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Upload Dokumen Baru</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Dokumen dibuat sebagai DRAFT dengan file pertama sebagai V1, lalu disubmit ke client.</p>

        <form method="POST" action="{{ route('project-documents.store', $project) }}" enctype="multipart/form-data"
              x-data="{
                  submitting: false,
                  billingTypeIds: @js($types->filter->requiresBilling()->pluck('id')->map(fn ($id) => (string) $id)->values()),
                  billings: @js(collect($billingOptions)->keyBy('id')),
                  typeId: @js($initialTypeId !== null ? (string) $initialTypeId : ''),
                  billingId: @js($initialBillingId !== null ? (string) $initialBillingId : ''),
                  title: @js((string) old('document_title', '')),
                  titleTouched: @js(old('document_title') !== null),
                  get needsBilling() { return this.billingTypeIds.includes(this.typeId); },
                  suggestTitle() {
                      if (! this.titleTouched && this.billings[this.billingId]) { this.title = this.billings[this.billingId].title; }
                  }
              }"
              x-init="suggestTitle()"
              @submit="submitting = true">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="document_type_id" value="Tipe Dokumen *" />
                    <select id="document_type_id" name="document_type_id" x-model="typeId" required
                            class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">Pilih tipe dokumen</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('document_type_id')" class="mt-1" />
                </div>

                <div x-show="needsBilling" x-cloak>
                    <x-input-label for="client_billing_id" value="Penagihan *" />
                    <select id="client_billing_id" name="client_billing_id" x-model="billingId" @change="suggestTitle()" :required="needsBilling" :disabled="! needsBilling"
                            class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                        <option value="">Pilih penagihan</option>
                        @foreach ($billingOptions as $option)
                            <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </select>
                    @if (count($billingOptions) === 0)
                        <p class="mt-1 text-xs text-maroon-900">Semua penagihan sudah memiliki dokumen aktif, atau belum ada penagihan.</p>
                    @endif
                    <x-input-error :messages="$errors->get('client_billing_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="document_title" value="Judul Dokumen *" />
                    <input id="document_title" name="document_title" type="text" required maxlength="255" x-model="title" @input="titleTouched = true"
                           class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                    <x-input-error :messages="$errors->get('document_title')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="file" value="File Dokumen (PDF, maks 10 MB) *" />
                    <input id="file" type="file" name="file" accept=".pdf,application/pdf" required
                           class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                    <x-input-error :messages="$errors->get('file')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="version_notes" value="Catatan Versi" />
                    <x-text-input id="version_notes" name="version_notes" class="mt-1 block w-full text-sm" maxlength="2000" :value="old('version_notes')" />
                </div>

                <div>
                    <x-input-label for="description" value="Keterangan" />
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('projects.documents.index', $project) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900 disabled:opacity-60"
                        :disabled="submitting"
                        x-text="submitting ? 'Processing...' : 'Simpan Draft'">
                    Simpan Draft
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
