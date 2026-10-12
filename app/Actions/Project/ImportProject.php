<?php

namespace App\Actions\Project;

use App\Enums\AssetStatus;
use App\Enums\DocumentReferenceType;
use App\Enums\ProjectAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ManPower;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectArchive;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class ImportProject
{
    private const MAX_LISTED_CONFLICTS = 15;

    /**
     * Restores a project exported by ExportProject with its original statuses. Users are matched by email
     * (falling back to the importer), assets and document types by code; everything else gets new IDs.
     *
     * @param  array{file?: mixed}  $data
     */
    public function handle(User $actor, array $data): Project
    {
        $validated = Validator::make($data, [
            'file' => ['required', 'file', 'mimes:zip', 'max:512000'],
        ], [
            'file.required' => 'Pilih file ZIP hasil export project.',
            'file.mimes' => 'File harus berformat ZIP hasil export project.',
            'file.max' => 'Ukuran file maksimal 500 MB.',
        ])->validate();

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $zip = new ZipArchive;

        if ($zip->open((string) $file->getRealPath()) !== true) {
            throw BusinessRuleException::make('File ZIP tidak dapat dibuka.');
        }

        try {
            $manifest = $this->readManifest($zip);
            $this->assertNoConflicts($manifest);

            return $this->import($actor, $zip, $manifest);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{project_code: string, users: list<array{id: int, email: string}>, tables: array<string, list<array<string, mixed>>>, files: list<array{disk: string, path: string}>}
     */
    private function readManifest(ZipArchive $zip): array
    {
        $json = $zip->getFromName(ProjectArchive::MANIFEST);

        if ($json === false) {
            throw BusinessRuleException::make('File bukan hasil export project ('.ProjectArchive::MANIFEST.' tidak ditemukan).');
        }

        $manifest = json_decode($json, true);

        if (! is_array($manifest) || ($manifest['format'] ?? null) !== ProjectArchive::FORMAT) {
            throw BusinessRuleException::make('Format file export project tidak dikenali.');
        }

        if ((int) ($manifest['version'] ?? 0) > ProjectArchive::VERSION) {
            throw BusinessRuleException::make('File export dibuat oleh versi aplikasi yang lebih baru dan belum dapat diimport.');
        }

        if (! is_array($manifest['tables'] ?? null) || count($manifest['tables']['projects'] ?? []) !== 1) {
            throw BusinessRuleException::make('File export project tidak lengkap.');
        }

        $manifest['users'] = is_array($manifest['users'] ?? null) ? $manifest['users'] : [];
        $manifest['files'] = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
        $manifest['project_code'] = (string) $manifest['tables']['projects'][0]['code'];

        return $manifest;
    }

    /**
     * Rejects the import when a globally unique number already exists (soft-deleted rows included)
     * or when an asset that is IN USE in the export is already in use by another project.
     *
     * @param  array{tables: array<string, list<array<string, mixed>>>}  $manifest
     */
    private function assertNoConflicts(array $manifest): void
    {
        $conflicts = [];

        foreach (ProjectArchive::tables() as $table => $definition) {
            foreach ($definition['unique'] as $column => $label) {
                $values = $this->rows($manifest, $table)
                    ->pluck($column)
                    ->filter(fn ($value) => $value !== null && $value !== '')
                    ->unique()
                    ->values();

                if ($values->isEmpty()) {
                    continue;
                }

                foreach (DB::table($table)->whereIn($column, $values->all())->pluck($column) as $existing) {
                    $conflicts[] = $label.' '.$existing.' sudah ada.';
                }
            }
        }

        $inUseAssetIds = $this->rows($manifest, 'project_assets')
            ->where('status', ProjectAssetStatus::InUse->value)
            ->pluck('asset_id');
        $inUseAssetCodes = $this->rows($manifest, 'assets')->whereIn('id', $inUseAssetIds)->pluck('code');

        if ($inUseAssetCodes->isNotEmpty()) {
            $busyCodes = DB::table('project_assets')
                ->join('assets', 'assets.id', '=', 'project_assets.asset_id')
                ->where('project_assets.status', ProjectAssetStatus::InUse->value)
                ->whereIn('assets.code', $inUseAssetCodes->all())
                ->pluck('assets.code');

            foreach ($busyCodes as $code) {
                $conflicts[] = 'Asset '.$code.' sedang digunakan project lain.';
            }
        }

        if ($conflicts === []) {
            return;
        }

        $listed = array_slice($conflicts, 0, self::MAX_LISTED_CONFLICTS);
        $more = count($conflicts) - count($listed);

        throw BusinessRuleException::make(
            'Import dibatalkan karena data sudah ada: '.implode(' ', $listed).($more > 0 ? " (+{$more} lainnya)" : '')
        );
    }

    /**
     * @param  array{project_code: string, users: list<array{id: int, email: string}>, tables: array<string, list<array<string, mixed>>>, files: list<array{disk: string, path: string}>}  $manifest
     */
    private function import(User $actor, ZipArchive $zip, array $manifest): Project
    {
        $writtenFiles = [];

        try {
            $fileMap = $this->restoreFiles($zip, $manifest, $writtenFiles);

            return DB::transaction(fn () => $this->insertRows($actor, $manifest, $fileMap));
        } catch (Throwable $exception) {
            foreach ($writtenFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            if ($exception instanceof QueryException) {
                throw BusinessRuleException::make('Data export tidak kompatibel dengan database saat ini. Tidak ada data yang diimport.');
            }

            throw $exception;
        }
    }

    /**
     * Attachments are written under a new folder so existing files are never overwritten.
     *
     * @param  array{project_code: string, files: list<array{disk: string, path: string}>}  $manifest
     * @param  list<array{0: string, 1: string}>  $writtenFiles
     * @return array<string, string> "disk|old path" => new path
     */
    private function restoreFiles(ZipArchive $zip, array $manifest, array &$writtenFiles): array
    {
        $allowedDisks = collect(ProjectArchive::tables())->flatMap(fn (array $definition) => array_values($definition['files']))->unique();
        $folder = 'imported/'.Str::slug($manifest['project_code']).'-'.now()->format('YmdHis');
        $fileMap = [];

        foreach ($manifest['files'] as $file) {
            $disk = (string) ($file['disk'] ?? '');
            $path = (string) ($file['path'] ?? '');

            if (! $allowedDisks->contains($disk) || $path === '' || str_contains($path, '..') || str_starts_with($path, '/')) {
                continue;
            }

            $contents = $zip->getFromName(ProjectArchive::zipPath($disk, $path));

            if ($contents === false) {
                continue;
            }

            $newPath = $folder.'/'.$path;
            Storage::disk($disk)->put($newPath, $contents);
            $writtenFiles[] = [$disk, $newPath];
            $fileMap[$disk.'|'.$path] = $newPath;
        }

        return $fileMap;
    }

    /**
     * @param  array{users: list<array{id: int, email: string}>, tables: array<string, list<array<string, mixed>>>}  $manifest
     * @param  array<string, string>  $fileMap
     */
    private function insertRows(User $actor, array $manifest, array $fileMap): Project
    {
        $userMap = $this->userMap($actor, $manifest['users']);
        $ids = [];

        foreach (ProjectArchive::tables() as $table => $definition) {
            $columns = array_flip(Schema::getColumnListing($table));

            foreach ($this->rows($manifest, $table) as $row) {
                $oldId = (int) $row['id'];

                if (isset($definition['shared'])) {
                    $existingId = $this->existingSharedId($table, $definition['shared'], $row);

                    if ($existingId !== null) {
                        $ids[$table][$oldId] = $existingId;

                        continue;
                    }
                }

                unset($row['id']);

                foreach ($definition['parents'] as $column => $parentTable) {
                    if (($row[$column] ?? null) === null) {
                        continue;
                    }

                    $row[$column] = $ids[$parentTable][(int) $row[$column]]
                        ?? throw BusinessRuleException::make("File export tidak lengkap: data {$table} merujuk {$parentTable} yang tidak ada.");
                }

                foreach ($definition['optional_parents'] ?? [] as $column => $parentTable) {
                    if (($row[$column] ?? null) !== null) {
                        $row[$column] = $ids[$parentTable][(int) $row[$column]] ?? null;
                    }
                }

                foreach ($definition['users'] as $column) {
                    if (($row[$column] ?? null) !== null) {
                        $row[$column] = $userMap[(int) $row[$column]] ?? $actor->id;
                    }
                }

                foreach ($definition['files'] as $column => $disk) {
                    if (filled($row[$column] ?? null) && isset($fileMap[$disk.'|'.$row[$column]])) {
                        $row[$column] = $fileMap[$disk.'|'.$row[$column]];
                    }
                }

                if ($table === 'projects') {
                    $row['deleted_at'] = null;
                }

                if ($table === 'project_documents' && ($row['reference_type'] ?? null) === DocumentReferenceType::Billing->value && $row['reference_id'] !== null) {
                    $row['reference_id'] = $ids['client_billings'][(int) $row['reference_id']] ?? null;
                }

                if ($table === 'man_powers' && DB::table('man_powers')->where('code', $row['code'])->exists()) {
                    $row['code'] = ManPower::nextCode();
                }

                $ids[$table][$oldId] = (int) DB::table($table)->insertGetId(array_intersect_key($row, $columns));
            }
        }

        $this->markImportedAssetsInUse($manifest, $ids);

        return Project::query()->findOrFail($ids['projects'][(int) $manifest['tables']['projects'][0]['id']]);
    }

    /**
     * Man power is matched on code and name so a different worker that happens to share a code is not merged.
     *
     * @param  array<string, mixed>  $row
     */
    private function existingSharedId(string $table, string $column, array $row): ?int
    {
        $query = DB::table($table)->where($column, $row[$column] ?? null);

        if ($table === 'man_powers') {
            $query->where('name', $row['name'] ?? null);
        }

        $id = $query->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  array{tables: array<string, list<array<string, mixed>>>}  $manifest
     * @param  array<string, array<int, int>>  $ids
     */
    private function markImportedAssetsInUse(array $manifest, array $ids): void
    {
        $assetIds = $this->rows($manifest, 'project_assets')
            ->where('status', ProjectAssetStatus::InUse->value)
            ->map(fn (array $row) => $ids['assets'][(int) $row['asset_id']] ?? null)
            ->filter()
            ->unique()
            ->values();

        if ($assetIds->isNotEmpty()) {
            DB::table('assets')->whereIn('id', $assetIds->all())->update(['status' => AssetStatus::InUse->value]);
        }
    }

    /**
     * @param  list<array{id: int, email: string}>  $users
     * @return array<int, int> exported user id => local user id
     */
    private function userMap(User $actor, array $users): array
    {
        $emails = collect($users)->pluck('email')->filter()->all();
        $localIds = DB::table('users')->whereIn('email', $emails)->pluck('id', 'email');

        return collect($users)
            ->mapWithKeys(fn (array $user) => [(int) $user['id'] => (int) ($localIds[$user['email'] ?? ''] ?? $actor->id)])
            ->all();
    }

    /**
     * @param  array{tables: array<string, list<array<string, mixed>>>}  $manifest
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(array $manifest, string $table): Collection
    {
        $rows = $manifest['tables'][$table] ?? [];

        return collect(is_array($rows) ? $rows : []);
    }
}
