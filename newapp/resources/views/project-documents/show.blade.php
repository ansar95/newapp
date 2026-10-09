<x-app-layout>
    <x-slot name="header">{{ $document->document_number }}</x-slot>
    <x-slot name="subheader">{{ $project->code }} — Monitoring Dokumen</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Dokumen', 'url' => route('projects.documents.index', $project)],
        ['label' => $document->document_number, 'url' => null],
    ]" />

    @php
        /** @var \App\Models\ProjectDocument $document */
        $status = $document->current_status;
        $activeVersion = $document->activeVersion();
        $billing = $document->billing();
        $revisionRequest = $document->latestRevisionRequest();
        $hasRevisionVersion = $document->hasVersionAfterRevisionRequest();
        $canDownload = auth()->user()->can('download', $document);
        $canUpload = auth()->user()->can('uploadVersion', $document);
        $waiting = $document->waitingSeconds();
        $statusOrder = [
            \App\Enums\ProjectDocumentStatus::Draft,
            \App\Enums\ProjectDocumentStatus::Submitted,
            \App\Enums\ProjectDocumentStatus::UnderReview,
            \App\Enums\ProjectDocumentStatus::RevisionRequired,
            \App\Enums\ProjectDocumentStatus::Resubmitted,
        ];
    @endphp

    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6"
         x-data="{
             showApprove: {{ $errors->has('approval_notes') ? 'true' : 'false' }},
             showRevision: {{ $errors->has('revision_reason') ? 'true' : 'false' }},
             showCancel: {{ $errors->has('cancel_reason') ? 'true' : 'false' }},
             showUpload: {{ $errors->hasAny(['file', 'notes']) && $status === \App\Enums\ProjectDocumentStatus::Draft ? 'true' : 'false' }}
         }">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">{{ $document->documentType?->name }}</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-maroon-950">{{ $document->document_title }}</h2>
                    <x-status-badge :status="$status" />
                </div>
                <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-maroon-700">No Dokumen:</dt> <dd class="inline font-medium">{{ $document->document_number }}</dd></div>
                    <div>
                        <dt class="inline text-maroon-700">Referensi:</dt>
                        <dd class="inline font-medium">
                            @if ($billing)
                                @can('view', $billing)
                                    <a href="{{ route('client-billings.show', $billing) }}" class="text-maroon-900 underline">{{ $billing->billing_number }}</a>
                                @else
                                    {{ $billing->billing_number }}
                                @endcan
                                <span class="text-maroon-700">· {{ $document->referenceLabel() }}</span>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div><dt class="inline text-maroon-700">Versi Aktif:</dt> <dd class="inline font-medium">{{ $activeVersion?->label() ?? '-' }}</dd></div>
                    <div>
                        <dt class="inline text-maroon-700">Status sejak:</dt>
                        <dd class="inline font-medium">
                            @if ($document->statusSince()) <x-date-display :value="$document->statusSince()" format="d F Y H:i" /> @else - @endif
                            @if ($waiting !== null) <span class="text-maroon-700">({{ \App\Support\ProjectDocumentMonitor::formatDuration($waiting) }})</span> @endif
                        </dd>
                    </div>
                    <div><dt class="inline text-maroon-700">Revisi:</dt> <dd class="inline font-medium">{{ $document->revisionCount() }} kali</dd></div>
                    <div><dt class="inline text-maroon-700">Dibuat oleh:</dt> <dd class="inline font-medium">{{ $document->creator?->name ?? '-' }}</dd></div>
                </dl>
                @if ($document->description)
                    <p class="mt-3 text-sm text-maroon-800">{{ $document->description }}</p>
                @endif
                <p class="mt-3 text-xs text-maroon-700">
                    @if ($status->isWaitingClient()) Menunggu client. @elseif ($status->isWaitingAdmin()) Menunggu admin mengupload revisi. @endif
                    Status dokumen tidak mengubah status penagihan maupun pembayaran client.
                </p>
            </div>

            <div class="w-full max-w-sm space-y-3">
                <div class="rounded-lg border border-maroon-200 bg-maroon-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Current Version</p>
                    @if ($activeVersion)
                        <p class="mt-1 text-2xl font-bold text-maroon-950">{{ $activeVersion->label() }}</p>
                        <p class="mt-1 break-all text-sm font-medium text-maroon-900">{{ $activeVersion->original_filename }}</p>
                        <p class="mt-1 text-xs text-maroon-800">
                            {{ $activeVersion->readableSize() }} · {{ $activeVersion->uploader?->name ?? '-' }} ·
                            <x-date-display :value="$activeVersion->uploaded_at" format="d F Y H:i" />
                        </p>
                        @if ($canDownload)
                            <div class="mt-3 flex gap-2">
                                <a href="{{ route('project-document-versions.view', $activeVersion) }}" target="_blank" rel="noopener"
                                   class="rounded-md bg-maroon-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-maroon-900">View</a>
                                <a href="{{ route('project-document-versions.download', $activeVersion) }}"
                                   class="rounded-md border border-maroon-300 bg-white px-3 py-1.5 text-xs font-medium text-maroon-900 hover:bg-maroon-50">Download</a>
                            </div>
                        @endif
                    @else
                        <p class="mt-1 text-sm text-maroon-800">Belum ada file.</p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-2">
                    @can('update', $document)
                        <a href="{{ route('project-documents.edit', $document) }}"
                           class="inline-flex items-center rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Edit</a>
                    @endcan
                    @if ($canUpload && $status === \App\Enums\ProjectDocumentStatus::Draft)
                        <button type="button" @click="showUpload = !showUpload"
                                class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Upload Versi Baru</button>
                    @endif
                    @can('submit', $document)
                        <form method="POST" action="{{ route('project-documents.submit', $document) }}"
                              onsubmit="return confirm('Submit dokumen ke client? Setelah submit, file tidak dapat diganti sampai client meminta revisi.');">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Submit ke Client</button>
                        </form>
                    @endcan
                    @can('startReview', $document)
                        <form method="POST" action="{{ route('project-documents.start-review', $document) }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Start Review</button>
                        </form>
                    @endcan
                    @can('approve', $document)
                        <button type="button" @click="showApprove = !showApprove; showRevision = false"
                                class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Approve</button>
                    @endcan
                    @can('requestRevision', $document)
                        <button type="button" @click="showRevision = !showRevision; showApprove = false"
                                class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Request Revision</button>
                    @endcan
                    @can('cancel', $document)
                        <button type="button" @click="showCancel = !showCancel"
                                class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Batalkan</button>
                    @endcan
                </div>
            </div>
        </div>

        @if ($canUpload && $status === \App\Enums\ProjectDocumentStatus::Draft)
            <form method="POST" action="{{ route('project-document-versions.store', $document) }}" enctype="multipart/form-data"
                  class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4" x-show="showUpload" x-cloak>
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="draft_file" value="File (PDF, maks 10 MB) *" />
                        <input id="draft_file" type="file" name="file" accept=".pdf,application/pdf" required
                               class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                        <x-input-error :messages="$errors->get('file')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="draft_notes" value="Catatan Versi" />
                        <x-text-input id="draft_notes" name="notes" class="mt-1 block w-full text-sm" maxlength="2000" :value="old('notes')" />
                    </div>
                </div>
                <p class="mt-2 text-xs text-maroon-700">File lama tetap disimpan. Versi baru menjadi V{{ $document->current_version + 1 }}.</p>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Upload V{{ $document->current_version + 1 }}</button>
                </div>
            </form>
        @endif

        @can('approve', $document)
            <form method="POST" action="{{ route('project-documents.approve', $document) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showApprove" x-cloak
                  onsubmit="return confirm('Catat persetujuan client untuk {{ $activeVersion?->label() }}? Dokumen APPROVED bersifat final.');">
                @csrf
                <x-input-label for="approval_notes" value="Catatan Persetujuan" />
                <textarea id="approval_notes" name="approval_notes" rows="2"
                          class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600"
                          placeholder="Contoh: disetujui via email client tanggal ...">{{ old('approval_notes') }}</textarea>
                <x-input-error :messages="$errors->get('approval_notes')" class="mt-1" />
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Approve {{ $activeVersion?->label() }}</button>
                </div>
            </form>
        @endcan

        @can('requestRevision', $document)
            <form method="POST" action="{{ route('project-documents.request-revision', $document) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showRevision" x-cloak>
                @csrf
                <x-input-label for="revision_reason" value="Alasan Revisi dari Client *" />
                <textarea id="revision_reason" name="revision_reason" rows="2" required minlength="3"
                          class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600"
                          placeholder="Contoh: nilai pada lampiran belum sesuai BAST.">{{ old('revision_reason') }}</textarea>
                <x-input-error :messages="$errors->get('revision_reason')" class="mt-1" />
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Catat Permintaan Revisi</button>
                </div>
            </form>
        @endcan

        @can('cancel', $document)
            <form method="POST" action="{{ route('project-documents.cancel', $document) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showCancel" x-cloak
                  onsubmit="return confirm('Batalkan dokumen ini? Semua versi dan riwayat tetap disimpan.');">
                @csrf
                <x-input-label for="cancel_reason" value="Alasan Pembatalan *" />
                <textarea id="cancel_reason" name="cancel_reason" rows="2" required minlength="3"
                          class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ old('cancel_reason') }}</textarea>
                <x-input-error :messages="$errors->get('cancel_reason')" class="mt-1" />
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Batalkan Dokumen</button>
                </div>
            </form>
        @endcan

        @if ($status === \App\Enums\ProjectDocumentStatus::Approved)
            <div class="mt-4 rounded-lg border border-maroon-300 bg-white p-3 text-sm text-maroon-900">
                <span class="font-semibold">Approved {{ $document->approved_version ? 'V'.$document->approved_version : '' }}</span>
                — dicatat oleh {{ $document->approver?->name ?? '-' }}
                @if ($document->approved_at) pada <x-date-display :value="$document->approved_at" format="d F Y H:i" /> @endif
                @if ($document->approval_notes)
                    <span class="mt-1 block text-maroon-800">{{ $document->approval_notes }}</span>
                @endif
            </div>
        @endif

        @if ($status === \App\Enums\ProjectDocumentStatus::Cancelled && $document->cancel_reason)
            <div class="mt-4 rounded-lg border border-maroon-300 bg-white p-3 text-sm text-maroon-900">
                <span class="font-semibold">Alasan pembatalan:</span> {{ $document->cancel_reason }}
                <span class="text-maroon-700">({{ $document->canceller?->name ?? '-' }})</span>
            </div>
        @endif
    </div>

    @if ($status === \App\Enums\ProjectDocumentStatus::RevisionRequired)
        <div class="mb-6 rounded-xl border-2 border-maroon-400 bg-maroon-50 p-5 shadow-sm" role="alert">
            <p class="text-base font-semibold text-maroon-950">Dokumen membutuhkan revisi.</p>
            @if ($revisionRequest)
                <p class="mt-1 text-sm text-maroon-900">
                    <span class="font-semibold">Alasan:</span> {{ $revisionRequest->revision_reason }}
                </p>
                <p class="mt-1 text-xs text-maroon-700">
                    Diminta untuk V{{ $revisionRequest->version_number }} · dicatat {{ $revisionRequest->changer?->name ?? '-' }}
                    pada <x-date-display :value="$revisionRequest->changed_at" format="d F Y H:i" />
                </p>
            @endif

            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-maroon-200 bg-white p-4">
                    <p class="text-sm font-semibold text-maroon-950">1. Upload Revision</p>
                    @if ($hasRevisionVersion)
                        <p class="mt-1 text-sm text-maroon-800">{{ $activeVersion?->label() }} sudah diupload. Anda masih dapat mengupload versi lain sebelum resubmit.</p>
                    @endif
                    @if ($canUpload)
                        <form method="POST" action="{{ route('project-document-versions.store', $document) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="revision_file" value="File Revisi (PDF, maks 10 MB) *" />
                                <input id="revision_file" type="file" name="file" accept=".pdf,application/pdf" required
                                       class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                                <x-input-error :messages="$errors->get('file')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="revision_notes" value="Catatan Revisi *" />
                                <x-text-input id="revision_notes" name="notes" class="mt-1 block w-full text-sm" required maxlength="2000" :value="old('notes')"
                                              placeholder="Apa yang diperbaiki pada versi ini?" />
                                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                            </div>
                            <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Upload V{{ $document->current_version + 1 }}</button>
                        </form>
                    @else
                        <p class="mt-1 text-sm text-maroon-700">Anda tidak memiliki akses untuk mengupload revisi.</p>
                    @endif
                </div>
                <div class="rounded-lg border border-maroon-200 bg-white p-4">
                    <p class="text-sm font-semibold text-maroon-950">2. Resubmit ke Client</p>
                    @can('resubmit', $document)
                        <p class="mt-1 text-sm text-maroon-800">Kirim ulang {{ $activeVersion?->label() }} ke client untuk direview.</p>
                        <form method="POST" action="{{ route('project-documents.resubmit', $document) }}" class="mt-3"
                              onsubmit="return confirm('Resubmit {{ $activeVersion?->label() }} ke client?');">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Resubmit {{ $activeVersion?->label() }}</button>
                        </form>
                    @else
                        <p class="mt-1 text-sm text-maroon-700">
                            {{ $hasRevisionVersion ? 'Anda tidak memiliki akses untuk resubmit.' : 'Upload versi revisi terlebih dahulu sebelum resubmit.' }}
                        </p>
                    @endcan
                </div>
            </div>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">Document Timeline</h3>
                <p class="text-xs text-maroon-700">Terbaru di atas. Durasi dihitung dari riwayat status (hari kalender).</p>
            </div>
            <ol class="space-y-0 px-4 py-4">
                @foreach ($timeline as $event)
                    @php
                        $isUpload = $event['type'] === 'upload';
                        $eventStatus = $event['status'];
                        $isRevision = $eventStatus === \App\Enums\ProjectDocumentStatus::RevisionRequired;
                        $isFinal = $eventStatus?->isLocked() ?? false;
                    @endphp
                    <li class="relative flex gap-4 pb-6 last:pb-0">
                        @unless ($loop->last)
                            <span class="absolute left-4 top-9 h-[calc(100%-2.25rem)] w-px bg-maroon-200" aria-hidden="true"></span>
                        @endunless
                        <span @class([
                            'relative z-10 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-bold ring-2',
                            'bg-white text-maroon-800 ring-maroon-300' => $isUpload,
                            'bg-maroon-50 text-maroon-950 ring-maroon-500' => $isRevision,
                            'bg-maroon-800 text-white ring-maroon-900' => ! $isUpload && ! $isRevision && $isFinal,
                            'bg-maroon-100 text-maroon-900 ring-maroon-300' => ! $isUpload && ! $isRevision && ! $isFinal,
                        ]) aria-hidden="true">
                            @if ($isUpload)
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0-12l-4 4m4-4l4 4"/></svg>
                            @elseif ($isRevision)
                                !
                            @elseif ($eventStatus === \App\Enums\ProjectDocumentStatus::Approved)
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            @else
                                <span class="h-2 w-2 rounded-full bg-current"></span>
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($isUpload)
                                    <span class="text-sm font-semibold text-maroon-950">Upload V{{ $event['version_number'] }}</span>
                                    <span class="break-all text-xs text-maroon-700">{{ $event['filename'] }}</span>
                                @else
                                    <x-status-badge :status="$eventStatus" />
                                    @if ($event['from_status'] === null)
                                        <span class="text-xs text-maroon-700">Dokumen dibuat</span>
                                    @elseif ($event['version_number'])
                                        <span class="text-xs text-maroon-700">V{{ $event['version_number'] }}</span>
                                    @endif
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-maroon-700">
                                <x-date-display :value="$event['at']" format="d F Y H:i" /> · {{ $event['actor'] ?? '-' }}
                                @if (! $isUpload && $event['duration_seconds'] !== null)
                                    · <span class="font-semibold text-maroon-900">{{ $event['ongoing'] ? 'Berjalan' : 'Durasi' }} {{ \App\Support\ProjectDocumentMonitor::formatDuration($event['duration_seconds']) }}</span>
                                @endif
                            </p>
                            @if ($event['revision_reason'])
                                <p class="mt-1 rounded-md bg-maroon-50 px-3 py-2 text-sm text-maroon-900"><span class="font-semibold">Alasan revisi:</span> {{ $event['revision_reason'] }}</p>
                            @endif
                            @if ($event['notes'] && ($isUpload || $event['from_status'] !== null))
                                <p class="mt-1 text-sm text-maroon-800">{{ $event['notes'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="space-y-6">
            <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
                <div class="border-b border-maroon-100 px-4 py-3">
                    <h3 class="text-base font-semibold text-maroon-950">Versi Dokumen</h3>
                    <p class="text-xs text-maroon-700">Versi lama tidak pernah ditimpa.</p>
                </div>
                <ul class="divide-y divide-maroon-100">
                    @foreach ($document->versions->sortByDesc('version_number') as $version)
                        <li class="flex items-start justify-between gap-3 px-4 py-3 text-sm">
                            <div class="min-w-0">
                                <p class="font-semibold text-maroon-950">
                                    {{ $version->label() }}
                                    @if ($version->version_number === $document->current_version)
                                        <span class="ml-1 rounded bg-maroon-100 px-1.5 py-0.5 text-xs font-medium text-maroon-900">aktif</span>
                                    @endif
                                    @if ($version->version_number === $document->approved_version)
                                        <span class="ml-1 rounded bg-maroon-800 px-1.5 py-0.5 text-xs font-medium text-white">approved</span>
                                    @endif
                                </p>
                                <p class="break-all text-xs text-maroon-800">{{ $version->original_filename }}</p>
                                <p class="text-xs text-maroon-700">{{ $version->readableSize() }} · {{ $version->uploader?->name ?? '-' }} · <x-date-display :value="$version->uploaded_at" format="d/m/Y H:i" /></p>
                                @if ($version->notes)
                                    <p class="mt-1 text-xs text-maroon-800">{{ $version->notes }}</p>
                                @endif
                            </div>
                            @if ($canDownload)
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <a href="{{ route('project-document-versions.view', $version) }}" target="_blank" rel="noopener" class="text-xs font-medium text-maroon-900 underline">View</a>
                                    <a href="{{ route('project-document-versions.download', $version) }}" class="text-xs font-medium text-maroon-900 underline">Download</a>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
                <div class="border-b border-maroon-100 px-4 py-3">
                    <h3 class="text-base font-semibold text-maroon-950">Durasi per Status</h3>
                    <p class="text-xs text-maroon-700">Akumulasi semua putaran review.</p>
                </div>
                <dl class="divide-y divide-maroon-100 text-sm">
                    @foreach ($statusOrder as $durationStatus)
                        @if (isset($statusTotals[$durationStatus->value]))
                            <div class="flex items-center justify-between px-4 py-2">
                                <dt class="text-maroon-800">{{ $durationStatus->label() }}</dt>
                                <dd class="font-semibold tabular-nums text-maroon-950">{{ \App\Support\ProjectDocumentMonitor::formatDuration($statusTotals[$durationStatus->value]) }}</dd>
                            </div>
                        @endif
                    @endforeach
                    @if ($document->firstSubmittedAt())
                        <div class="flex items-center justify-between bg-maroon-50 px-4 py-2">
                            <dt class="font-medium text-maroon-900">Total siklus review</dt>
                            <dd class="font-bold tabular-nums text-maroon-950">{{ \App\Support\ProjectDocumentMonitor::formatDuration(\App\Support\ProjectDocumentMonitor::cycleSeconds($document)) }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>
