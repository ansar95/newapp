<?php

namespace App\Actions\Document;

use App\Models\ProjectDocument;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Stores an uploaded file as the next version of a document. Earlier versions are never touched.
 * Must run inside a transaction.
 */
class StoreProjectDocumentVersion
{
    public const MAX_KILOBYTES = 10240;

    /**
     * @return array<int, string>
     */
    public static function fileRules(): array
    {
        return ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.self::MAX_KILOBYTES];
    }

    /**
     * @return array<string, string>
     */
    public static function fileMessages(string $field = 'file'): array
    {
        return [
            $field.'.required' => 'File dokumen wajib diupload.',
            $field.'.mimes' => 'File dokumen harus berupa PDF.',
            $field.'.mimetypes' => 'File dokumen harus berupa PDF.',
            $field.'.max' => 'Ukuran file dokumen maksimal 10 MB.',
        ];
    }

    public function handle(User $actor, ProjectDocument $document, UploadedFile $file, ?string $notes = null): ProjectDocumentVersion
    {
        $versionNumber = (int) ProjectDocumentVersion::query()
            ->where('project_document_id', $document->id)
            ->lockForUpdate()
            ->max('version_number') + 1;

        $directory = 'project-documents/'.$document->project_id.'/'.$document->id;
        $path = $file->storeAs($directory, Str::uuid()->toString().'.pdf', ProjectDocumentVersion::DISK);

        $version = ProjectDocumentVersion::query()->create([
            'project_document_id' => $document->id,
            'version_number' => $versionNumber,
            'file_path' => $path,
            'original_filename' => self::sanitizeFilename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?? 'application/pdf',
            'file_size' => $file->getSize(),
            'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
            'notes' => $notes,
            'uploaded_by' => $actor->id,
            'uploaded_at' => now(),
        ]);

        $document->forceFill(['current_version' => $versionNumber])->save();

        return $version;
    }

    public static function sanitizeFilename(string $filename): string
    {
        $name = pathinfo(basename(str_replace('\\', '/', $filename)), PATHINFO_FILENAME);
        $name = trim((string) preg_replace('/[^\pL\pN ._()-]+/u', '_', $name), ' ._');

        return Str::limit($name !== '' ? $name : 'dokumen', 200, '').'.pdf';
    }
}
