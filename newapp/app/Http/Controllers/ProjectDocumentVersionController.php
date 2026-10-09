<?php

namespace App\Http\Controllers;

use App\Actions\Document\UploadProjectDocumentVersion;
use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectDocumentVersionController extends Controller
{
    public function store(Request $request, ProjectDocument $projectDocument, UploadProjectDocumentVersion $uploadProjectDocumentVersion): RedirectResponse
    {
        $this->authorize('uploadVersion', $projectDocument);

        $version = $uploadProjectDocumentVersion->handle($request->user(), $projectDocument, $request->only('notes'), $request->file('file'));

        return back()->with('success', 'Versi '.$version->label().' berhasil diupload.');
    }

    public function view(ProjectDocumentVersion $projectDocumentVersion): StreamedResponse|RedirectResponse
    {
        return $this->stream($projectDocumentVersion, 'inline');
    }

    public function download(ProjectDocumentVersion $projectDocumentVersion): StreamedResponse|RedirectResponse
    {
        return $this->stream($projectDocumentVersion, 'attachment');
    }

    private function stream(ProjectDocumentVersion $version, string $disposition): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $version);

        $disk = Storage::disk(ProjectDocumentVersion::DISK);

        if (! $disk->exists($version->file_path)) {
            return back()->with('error', 'File dokumen tidak ditemukan.');
        }

        return $disk->response($version->file_path, $version->original_filename, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ], $disposition);
    }
}
