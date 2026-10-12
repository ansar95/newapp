<?php

namespace App\Actions\ManPower;

use App\Models\ManPower;
use App\Models\User;
use App\Support\ManPowerCsv;
use App\Support\SpreadsheetFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ImportManPowers
{
    /**
     * Rows with an existing code update that worker; rows with a blank code get the next MP code.
     * Any invalid row rolls back the whole file.
     *
     * @return array{created: int, updated: int}
     */
    public function handle(User $actor, mixed $file): array
    {
        Validator::make(
            ['file' => $file],
            ['file' => SpreadsheetFile::UPLOAD_RULES],
            SpreadsheetFile::uploadMessages()
        )->validate();

        try {
            $rows = ManPowerCsv::parse($file);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $this->assertUniqueCodes($rows->pluck('code', '_line')->filter()->all());

        return DB::transaction(function () use ($actor, $rows): array {
            $created = 0;
            $updated = 0;

            foreach ($rows as $row) {
                $line = (int) $row['_line'];
                $validated = $this->validateRow($row, $line);
                $existing = $validated['code'] !== null
                    ? ManPower::query()->where('code', $validated['code'])->first()
                    : null;

                $attributes = [
                    'name' => $validated['name'],
                    'position' => $validated['position'],
                    'daily_wage' => $validated['daily_wage'],
                    'phone' => $validated['phone'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ];

                if ($existing !== null) {
                    $existing->update([...$attributes, 'is_active' => $validated['is_active'] ?? $existing->is_active]);
                    $updated++;

                    continue;
                }

                ManPower::query()->create([
                    ...$attributes,
                    'code' => $validated['code'] ?? ManPower::nextCode(),
                    'is_active' => $validated['is_active'] ?? true,
                    'created_by' => $actor->id,
                ]);
                $created++;
            }

            return ['created' => $created, 'updated' => $updated];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{code: string|null, name: string, position: string, daily_wage: string, phone?: string|null, is_active?: bool|null, notes?: string|null}
     */
    private function validateRow(array $row, int $line): array
    {
        $validator = Validator::make($row, [
            ...ManPowerRules::master(),
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            ...ManPowerRules::messages(),
            'is_active.boolean' => 'Status harus AKTIF atau NONAKTIF.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'file' => collect($validator->errors()->all())->map(fn (string $message) => 'Baris '.$line.': '.$message)->all(),
            ]);
        }

        return $validator->validated() + ['code' => null];
    }

    /**
     * @param  array<int, string>  $codesByLine
     */
    private function assertUniqueCodes(array $codesByLine): void
    {
        $seen = [];

        foreach ($codesByLine as $line => $code) {
            $key = strtoupper($code);

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'file' => 'Baris '.$line.': kode '.$code.' dipakai lebih dari sekali (juga di baris '.$seen[$key].').',
                ]);
            }

            $seen[$key] = $line;
        }
    }
}
