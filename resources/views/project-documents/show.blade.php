<x-app-layout>
    <x-slot name="header">{{ $document->document_number }}</x-slot>
    <x-slot name="subheader">{{ $project->code }} — Arsip Dokumen</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => route('projects.documents.index', $project)],
        ['label' => $document->document_number, 'url' => null],
    ]" />

    @php
        /** @var \App\Models\ProjectDocument $document */
        $activeVersion = $document->activeVersion();
        $billing = $document->billing();
        $canDownload = auth()->user()->can('download', $document);
        $canUpload = auth()->user()->can('uploadVersion', $document)
            && $document->current_status !== \App\Enums\ProjectDocumentStatus::RevisionRequired;
    @endphp

    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">{{ $document->typeName() }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight text-maroon-950">{{ $document->document_title }}</h2>
                <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-maroon-700">No Dokumen:</dt> <dd class="inline font-medium">{{ $document->document_number }}</dd></div>
                    @if ($billing)
                        <div>
                            <dt class="inline text-maroon-700">Referensi:</dt>
                            <dd class="inline font-medium">
                                @can('view', $billing)
                                    <a href="{{ route('client-billings.show', $billing) }}" class="text-maroon-900 underline">{{ $billing->billing_number }}</a>
                                @else
                                    {{ $billing->billing_number }}
                                @endcan
                            </dd>
                        </div>
                    @endif
                    <div><dt class="inline text-maroon-700">Diunggah oleh:</dt> <dd class="inline font-medium">{{ $document->creator?->name ?? '-' }}</dd></div>
                    <div>
                        <dt class="inline text-maroon-700">Berkas aktif:</dt>
                        <dd class="inline font-medium">{{ $activeVersion?->original_filename ?? '-' }}</dd>
                    </div>
                </dl>
                @if ($document->description)
                    <p class="mt-3 text-sm text-maroon-800">{{ $document->description }}</p>
                @endif
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @can('update', $document)
                    <a href="{{ route('project-documents.edit', $document) }}"
                       class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Edit</a>
                @endcan
                @if ($canDownload && $activeVersion)
                    <a href="{{ route('project-document-versions.view', $activeVersion) }}" target="_blank" rel="noopener"
                       class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Lihat</a>
                    <a href="{{ route('project-document-versions.download', $activeVersion) }}"
                       class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Unduh</a>
                @endif
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">Berkas</h3>
                <p class="text-xs text-maroon-700">Setiap unggahan tersimpan. Berkas lama tidak ditimpa.</p>
            </div>
            <ul class="divide-y divide-maroon-100">
                @foreach ($document->versions->sortByDesc('version_number') as $version)
                    <li class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold text-maroon-950">
                                {{ $version->original_filename }}
                                @if ($version->version_number === $document->current_version)
                                    <span class="ml-1 rounded bg-maroon-100 px-1.5 py-0.5 text-xs font-medium text-maroon-900">terbaru</span>
                                @endif
                            </p>
                            <p class="text-xs text-maroon-700">{{ $version->readableSize() }} · {{ $version->uploader?->name ?? '-' }} · <x-date-display :value="$version->uploaded_at" format="d/m/Y H:i" /></p>
                            @if ($version->notes)
                                <p class="mt-1 text-xs text-maroon-800">{{ $version->notes }}</p>
                            @endif
                        </div>
                        @if ($canDownload)
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <a href="{{ route('project-document-versions.view', $version) }}" target="_blank" rel="noopener" class="text-xs font-medium text-maroon-900 underline">Lihat</a>
                                <a href="{{ route('project-document-versions.download', $version) }}" class="text-xs font-medium text-maroon-900 underline">Unduh</a>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        @if ($canUpload)
            <div class="rounded-xl border border-maroon-200 bg-white p-4 shadow-sm">
                <h3 class="text-base font-semibold text-maroon-950">Tambah berkas</h3>
                <p class="mt-1 text-xs text-maroon-700">PDF, maksimal 10 MB. Berkas sebelumnya tetap ada.</p>
                <form method="POST" action="{{ route('project-document-versions.store', $document) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                    @csrf
                    <div>
                        <x-input-label for="archive_file" value="File (PDF) *" />
                        <input id="archive_file" type="file" name="file" accept=".pdf,application/pdf" required
                               class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                        <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="archive_notes" value="Catatan" />
                        <x-text-input id="archive_notes" name="notes" class="mt-1 block w-full text-sm" maxlength="2000" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </div>
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Simpan berkas</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
