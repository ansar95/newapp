<?php

namespace App\Actions\Project;

use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use App\Support\ProjectArchive;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class ExportProject
{
    /**
     * Builds a ZIP with every row that belongs to the project plus its attachments.
     *
     * @return string Absolute path of the temporary ZIP file; the caller is responsible for deleting it.
     */
    public function handle(Project $project): string
    {
        $rows = $this->collectRows($project);
        [$files, $missingFiles] = $this->collectFiles($rows);

        $manifest = [
            'format' => ProjectArchive::FORMAT,
            'version' => ProjectArchive::VERSION,
            'exported_at' => now()->toIso8601String(),
            'app_url' => config('app.url'),
            'project_code' => $project->code,
            'users' => $this->collectUsers($rows),
            'tables' => $rows->map(fn (Collection $tableRows) => $tableRows->values()->all())->all(),
            'files' => $files,
            'missing_files' => $missingFiles,
        ];

        $zipPath = tempnam(sys_get_temp_dir(), 'project-export-');
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw BusinessRuleException::make('File export tidak dapat dibuat.');
        }

        $zip->addFromString(
            ProjectArchive::MANIFEST,
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        foreach ($files as $file) {
            $zip->addFile(Storage::disk($file['disk'])->path($file['path']), ProjectArchive::zipPath($file['disk'], $file['path']));
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * @return Collection<string, Collection<int, object>>
     */
    private function collectRows(Project $project): Collection
    {
        $rows = collect();

        foreach (ProjectArchive::tables() as $table => $definition) {
            if (isset($definition['shared'])) {
                continue;
            }

            $rows[$table] = $definition['scope'] === null
                ? DB::table($table)->where('id', $project->id)->get()
                : $this->whereIn($table, $definition['scope'][0], $rows[$definition['scope'][1]]->pluck('id'));
        }

        $rows['assets'] = DB::table('assets')
            ->whereIn('id', $rows['project_assets']->pluck('asset_id')->unique()->all())
            ->orWhereIn('procurement_request_item_id', $rows['procurement_request_items']->pluck('id')->all())
            ->orderBy('id')
            ->get();
        $rows['document_types'] = $this->whereIn('document_types', 'id', $rows['project_documents']->pluck('document_type_id')->unique());
        $rows['man_powers'] = $this->whereIn('man_powers', 'id', $rows['project_man_powers']->pluck('man_power_id')->unique());

        return $rows;
    }

    /**
     * @param  Collection<int, mixed>  $ids
     * @return Collection<int, object>
     */
    private function whereIn(string $table, string $column, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        return DB::table($table)->whereIn($column, $ids->all())->orderBy('id')->get();
    }

    /**
     * @param  Collection<string, Collection<int, object>>  $rows
     * @return list<array{id: int, name: string, email: string}>
     */
    private function collectUsers(Collection $rows): array
    {
        $userIds = collect();

        foreach (ProjectArchive::tables() as $table => $definition) {
            foreach ($definition['users'] as $column) {
                $userIds = $userIds->merge($rows[$table]->pluck($column));
            }
        }

        return DB::table('users')
            ->whereIn('id', $userIds->filter()->unique()->values()->all())
            ->get(['id', 'name', 'email'])
            ->map(fn (object $user) => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email])
            ->all();
    }

    /**
     * @param  Collection<string, Collection<int, object>>  $rows
     * @return array{0: list<array{disk: string, path: string}>, 1: list<array{disk: string, path: string}>}
     */
    private function collectFiles(Collection $rows): array
    {
        $files = [];
        $missing = [];

        foreach (ProjectArchive::tables() as $table => $definition) {
            foreach ($definition['files'] as $column => $disk) {
                foreach ($rows[$table]->pluck($column)->filter()->unique() as $path) {
                    $entry = ['disk' => $disk, 'path' => (string) $path];

                    if (Storage::disk($disk)->exists($path)) {
                        $files[] = $entry;
                    } else {
                        $missing[] = $entry;
                    }
                }
            }
        }

        return [$files, $missing];
    }
}
