<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Unggah Dokumen</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => route('projects.documents.index', $project)],
        ['label' => 'Unggah Dokumen', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Upload Dokumen Baru</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">Berkas langsung disimpan di arsip. Tidak ada proses approval atau revisi.</p>

        <form method="POST" action="{{ route('project-documents.store', $project) }}" enctype="multipart/form-data"
              x-data="{ submitting: false }"
              @submit="submitting = true">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="type_label" value="Tipe Dokumen *" />
                    <x-text-input id="type_label" name="type_label" class="mt-1 block w-full text-sm" required maxlength="255"
                                  :value="old('type_label')" placeholder="Contoh: Kontrak, Berita Acara, Invoice" />
                    <x-input-error :messages="$errors->get('type_label')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="document_title" value="Judul Dokumen *" />
                    <x-text-input id="document_title" name="document_title" class="mt-1 block w-full text-sm" required maxlength="255"
                                  :value="old('document_title')" />
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
                        x-text="submitting ? 'Processing...' : 'Unggah'">
                    Unggah
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
