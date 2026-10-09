<x-app-layout>
    <x-slot name="header">{{ $document->document_number }} — Edit</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => route('projects.documents.index', $project)],
        ['label' => $document->document_number, 'url' => route('project-documents.show', $document)],
        ['label' => 'Edit', 'url' => null],
    ]" />

    <div class="mx-auto max-w-2xl rounded-xl border border-maroon-200 bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-maroon-950">Edit Dokumen</h2>
        <p class="mb-4 mt-1 text-sm text-maroon-700">File tidak diubah di sini. Upload versi baru dari halaman detail dokumen.</p>

        <form method="POST" action="{{ route('project-documents.update', $document) }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <x-input-label for="document_title" value="Judul Dokumen *" />
                    <x-text-input id="document_title" name="document_title" class="mt-1 block w-full text-sm" required maxlength="255"
                                  :value="old('document_title', $document->document_title)" />
                    <x-input-error :messages="$errors->get('document_title')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="description" value="Keterangan" />
                    <textarea id="description" name="description" rows="3"
                              class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('description', $document->description) }}</textarea>
                </div>
            </div>
            <div class="flex justify-end gap-2 pt-6">
                <a href="{{ route('project-documents.show', $document) }}" class="rounded-md border border-maroon-300 px-4 py-2 text-sm text-maroon-800">Cancel</a>
                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900">Simpan</button>
            </div>
        </form>
    </div>
</x-app-layout>
