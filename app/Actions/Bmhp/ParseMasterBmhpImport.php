<?php

namespace App\Actions\Bmhp;

use App\Enums\MasterBmhpStatus;
use App\Models\MasterBmhp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Reads a Master BMHP CSV into preview rows. Nothing is saved here.
 *
 * @phpstan-type ImportRow array{
 *     line: int,
 *     action: 'create'|'update'|'unchanged'|'error',
 *     code: string|null,
 *     name: string,
 *     category: string,
 *     unit: string,
 *     description: string|null,
 *     status: string|null,
 *     existing_id: int|null,
 *     existing_name: string|null,
 *     errors: list<string>,
 *     warnings: list<string>
 * }
 */
class ParseMasterBmhpImport
{
    public const MAX_ROWS = 1000;

    public const MAX_KILOBYTES = 2048;

    /**
     * Template column order.
     *
     * @var list<string>
     */
    public const COLUMNS = ['kode', 'nama', 'kategori', 'satuan', 'deskripsi', 'status'];

    private const HEADER_ALIASES = [
        'kode' => 'code',
        'code' => 'code',
        'kode bmhp' => 'code',
        'nama' => 'name',
        'name' => 'name',
        'nama bmhp' => 'name',
        'nama barang' => 'name',
        'kategori' => 'category',
        'category' => 'category',
        'satuan' => 'unit',
        'unit' => 'unit',
        'deskripsi' => 'description',
        'description' => 'description',
        'keterangan' => 'description',
        'status' => 'status',
    ];

    private const REQUIRED_FIELDS = ['name' => 'nama', 'category' => 'kategori', 'unit' => 'satuan'];

    private const STATUS_ALIASES = [
        'ACTIVE' => MasterBmhpStatus::Active,
        'AKTIF' => MasterBmhpStatus::Active,
        'INACTIVE' => MasterBmhpStatus::Inactive,
        'NONAKTIF' => MasterBmhpStatus::Inactive,
        'NON AKTIF' => MasterBmhpStatus::Inactive,
        'TIDAK AKTIF' => MasterBmhpStatus::Inactive,
    ];

    /**
     * @return list<ImportRow>
     */
    public function handle(?UploadedFile $file): array
    {
        Validator::make(['file' => $file], [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:'.self::MAX_KILOBYTES],
        ], [
            'file.required' => 'File CSV wajib dipilih.',
            'file.mimes' => 'File harus berformat CSV.',
            'file.max' => 'Ukuran file maksimal 2 MB.',
        ])->validate();

        [$header, $records] = $this->readCsv((string) file_get_contents($file->getRealPath()));

        $columns = $this->mapHeader($header);
        $rawRows = $this->extractRows($records, $columns);

        if ($rawRows === []) {
            throw ValidationException::withMessages(['file' => 'File tidak berisi data BMHP.']);
        }

        if (count($rawRows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'Maksimal '.self::MAX_ROWS.' baris per import.']);
        }

        return $this->buildRows($rawRows);
    }

    /**
     * @return array{0: list<string>, 1: list<array{line: int, values: list<string|null>}>}
     */
    private function readCsv(string $content): array
    {
        $content = (string) preg_replace('/^\xEF\xBB\xBF/', '', $content);

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $line = 1;
        $delimiter = null;

        if (preg_match('/^sep=(.)\r?\n/i', $content, $matches) === 1) {
            $delimiter = $matches[1];
            $content = substr($content, strlen($matches[0]));
            $line++;
        }

        $delimiter ??= $this->detectDelimiter(strtok($content, "\n") ?: '');

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');

        if ($header === false || $header === [null]) {
            fclose($stream);

            throw ValidationException::withMessages(['file' => 'File kosong atau header kolom tidak ditemukan.']);
        }

        $records = [];

        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            $records[] = ['line' => $line, 'values' => $values];
        }

        fclose($stream);

        return [array_map(fn ($value) => (string) $value, $header), $records];
    }

    private function detectDelimiter(string $firstLine): string
    {
        $counts = [';' => substr_count($firstLine, ';'), ',' => substr_count($firstLine, ','), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int> field => column index
     */
    private function mapHeader(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $label) {
            $normalized = strtolower(trim(str_replace(['*', '_'], ['', ' '], $label)));
            $field = self::HEADER_ALIASES[$normalized] ?? null;

            if ($field !== null && ! isset($columns[$field])) {
                $columns[$field] = $index;
            }
        }

        $missing = array_values(array_diff_key(self::REQUIRED_FIELDS, $columns));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', $missing).'. Gunakan template import.',
            ]);
        }

        return $columns;
    }

    /**
     * @param  list<array{line: int, values: list<string|null>}>  $records
     * @param  array<string, int>  $columns
     * @return list<array{line: int, code: string, name: string, category: string, unit: string, description: string, status: string}>
     */
    private function extractRows(array $records, array $columns): array
    {
        $rows = [];

        foreach ($records as $record) {
            $row = ['line' => $record['line']];

            foreach (['code', 'name', 'category', 'unit', 'description', 'status'] as $field) {
                $row[$field] = isset($columns[$field])
                    ? trim((string) ($record['values'][$columns[$field]] ?? ''))
                    : '';
            }

            if (implode('', array_slice($row, 1)) === '') {
                continue;
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  list<array{line: int, code: string, name: string, category: string, unit: string, description: string, status: string}>  $rawRows
     * @return list<ImportRow>
     */
    private function buildRows(array $rawRows): array
    {
        $codes = collect($rawRows)->pluck('code')->filter()->values();
        $existingByCode = MasterBmhp::withTrashed()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy(fn (MasterBmhp $master) => strtoupper($master->code));
        $existingByName = MasterBmhp::query()
            ->whereIn('name', collect($rawRows)->pluck('name')->filter()->values())
            ->get(['id', 'code', 'name'])
            ->keyBy(fn (MasterBmhp $master) => mb_strtolower($master->name));

        $seenCodes = [];
        $seenNames = [];
        $rows = [];

        foreach ($rawRows as $raw) {
            $rows[] = $this->buildRow($raw, $existingByCode, $existingByName, $seenCodes, $seenNames);
        }

        return $rows;
    }

    /**
     * @param  array{line: int, code: string, name: string, category: string, unit: string, description: string, status: string}  $raw
     * @param  Collection<string, MasterBmhp>  $existingByCode
     * @param  Collection<string, MasterBmhp>  $existingByName
     * @param  array<string, int>  $seenCodes
     * @param  array<string, int>  $seenNames
     * @return ImportRow
     */
    private function buildRow(array $raw, Collection $existingByCode, Collection $existingByName, array &$seenCodes, array &$seenNames): array
    {
        $errors = [];
        $warnings = [];
        $code = $raw['code'] !== '' ? $raw['code'] : null;
        $category = $this->matchCategory($raw['category']);
        $status = $this->matchStatus($raw['status']);

        if ($raw['name'] === '') {
            $errors[] = 'Nama wajib diisi.';
        } elseif (mb_strlen($raw['name']) > 255) {
            $errors[] = 'Nama maksimal 255 karakter.';
        }

        if ($raw['category'] === '') {
            $errors[] = 'Kategori wajib diisi.';
        } elseif ($category === null) {
            $errors[] = 'Kategori "'.$raw['category'].'" tidak valid. Pilihan: '.implode(', ', MasterBmhp::categoryOptions()).'.';
        }

        if ($raw['unit'] === '') {
            $errors[] = 'Satuan wajib diisi.';
        } elseif (mb_strlen($raw['unit']) > 50) {
            $errors[] = 'Satuan maksimal 50 karakter.';
        }

        if ($raw['status'] !== '' && $status === null) {
            $errors[] = 'Status harus ACTIVE atau INACTIVE.';
        }

        $existing = null;

        if ($code !== null) {
            $codeKey = strtoupper($code);

            if (mb_strlen($code) > 50) {
                $errors[] = 'Kode maksimal 50 karakter.';
            }

            if (isset($seenCodes[$codeKey])) {
                $errors[] = 'Kode '.$code.' duplikat dengan baris '.$seenCodes[$codeKey].'.';
            } else {
                $seenCodes[$codeKey] = $raw['line'];
            }

            $existing = $existingByCode->get($codeKey);

            if ($existing?->trashed()) {
                $errors[] = 'Kode '.$code.' milik BMHP yang sudah dihapus dan tidak dapat dipakai lagi.';
                $existing = null;
            }
        }

        $row = [
            'line' => $raw['line'],
            'action' => 'create',
            'code' => $existing?->code ?? $code,
            'name' => $raw['name'],
            'category' => $category ?? $raw['category'],
            'unit' => $raw['unit'],
            'description' => $raw['description'] !== '' ? $raw['description'] : null,
            'status' => $status?->value,
            'existing_id' => $existing?->id,
            'existing_name' => $existing?->name,
            'errors' => $errors,
            'warnings' => [],
        ];

        if ($errors !== []) {
            $row['action'] = 'error';

            return $row;
        }

        if ($existing !== null) {
            $row['action'] = $this->isUnchanged($existing, $row) ? 'unchanged' : 'update';

            return $row;
        }

        $nameKey = mb_strtolower($raw['name']);
        $sameName = $existingByName->get($nameKey);

        if ($sameName !== null) {
            $warnings[] = 'Nama sudah ada di master ('.$sameName->code.'). Isi kolom kode '.$sameName->code.' jika ingin mengupdate.';
        }

        if (isset($seenNames[$nameKey])) {
            $warnings[] = 'Nama sama dengan baris '.$seenNames[$nameKey].'.';
        } else {
            $seenNames[$nameKey] = $raw['line'];
        }

        $row['warnings'] = $warnings;

        return $row;
    }

    private function matchCategory(string $value): ?string
    {
        foreach (MasterBmhp::categoryOptions() as $option) {
            if (mb_strtolower($option) === mb_strtolower($value)) {
                return $option;
            }
        }

        return null;
    }

    private function matchStatus(string $value): ?MasterBmhpStatus
    {
        return self::STATUS_ALIASES[strtoupper(trim($value))] ?? null;
    }

    /**
     * @param  ImportRow  $row
     */
    private function isUnchanged(MasterBmhp $existing, array $row): bool
    {
        return $existing->name === $row['name']
            && $existing->category === $row['category']
            && $existing->unit === $row['unit']
            && ($existing->description ?? null) === $row['description']
            && ($row['status'] === null || $existing->status->value === $row['status']);
    }
}
