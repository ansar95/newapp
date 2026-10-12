<?php

namespace App\Actions\Asset;

use App\Enums\AssetCondition;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use App\Support\AssetCsv;
use App\Support\SpreadsheetFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportAssets
{
    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(User $actor, UploadedFile $file): array
    {
        Validator::make(
            ['file' => $file],
            ['file' => SpreadsheetFile::UPLOAD_RULES],
            SpreadsheetFile::uploadMessages()
        )->validate();

        try {
            $rows = AssetCsv::parse($file);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        $maxYear = (int) now()->year + 1;

        return DB::transaction(function () use ($actor, $rows, $maxYear): array {
            $created = 0;
            $updated = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                $line = (int) ($row['_line'] ?? 0);

                $validated = Validator::make($row, [
                    'code' => ['required', 'string', 'max:50'],
                    'name' => ['required', 'string', 'max:255'],
                    'category' => ['required', 'string', Rule::in(Asset::categoryOptions())],
                    'brand' => ['nullable', 'string', 'max:255'],
                    'model' => ['nullable', 'string', 'max:255'],
                    'unit' => ['nullable', 'string', 'max:50'],
                    'source_type' => ['required', Rule::enum(AssetSourceType::class)],
                    'condition' => ['nullable', Rule::enum(AssetCondition::class)],
                    'purchase_year' => ['nullable', 'integer', 'min:1990', 'max:'.$maxYear],
                    'description' => ['nullable', 'string'],
                    'request_number' => ['nullable', 'string', 'max:100'],
                    'request_date' => ['nullable', 'date'],
                    'request_description' => ['nullable', 'string'],
                ], [
                    'required' => 'Baris '.$line.': kolom :attribute wajib diisi.',
                    'category.in' => 'Baris '.$line.': category tidak valid.',
                    'source_type' => 'Baris '.$line.': source_type harus PURCHASE atau EXISTING.',
                    'condition' => 'Baris '.$line.': condition harus GOOD, FAIR, POOR, atau DAMAGED.',
                    'purchase_year' => 'Baris '.$line.': purchase_year tidak valid.',
                ])->validate();

                $sourceType = $validated['source_type'] instanceof AssetSourceType
                    ? $validated['source_type']
                    : AssetSourceType::from($validated['source_type']);

                if ($sourceType === AssetSourceType::Purchase) {
                    Validator::make($validated, [
                        'request_number' => ['required', 'string', 'max:100'],
                        'request_date' => ['required', 'date'],
                    ], [
                        'request_number.required' => 'Baris '.$line.': request_number wajib untuk source PURCHASE.',
                        'request_date.required' => 'Baris '.$line.': request_date wajib untuk source PURCHASE.',
                    ])->validate();
                }

                $existing = Asset::query()->where('code', $validated['code'])->first();

                if ($existing !== null) {
                    if ($existing->status === AssetStatus::InUse) {
                        // Allow condition / purchase year updates even while in use.
                        $existing->fill([
                            'condition' => $validated['condition'] ?? $existing->condition,
                            'purchase_year' => $validated['purchase_year'] ?? $existing->purchase_year,
                            'description' => $validated['description'] ?? $existing->description,
                            'brand' => $validated['brand'] ?? $existing->brand,
                            'model' => $validated['model'] ?? $existing->model,
                        ]);
                        $existing->save();
                        $updated++;

                        continue;
                    }

                    app(UpdateAsset::class)->handle($actor, $existing, [
                        'name' => $validated['name'],
                        'category' => $validated['category'],
                        'brand' => $validated['brand'] ?? null,
                        'model' => $validated['model'] ?? null,
                        'unit' => $validated['unit'] ?? 'Unit',
                        'source_type' => $sourceType->value,
                        'condition' => $validated['condition'] ?? AssetCondition::Good->value,
                        'purchase_year' => $validated['purchase_year'] ?? null,
                        'description' => $validated['description'] ?? null,
                        'request_number' => $validated['request_number'] ?? null,
                        'request_date' => $validated['request_date'] ?? null,
                        'request_description' => $validated['request_description'] ?? null,
                    ]);
                    $updated++;

                    continue;
                }

                app(CreateAsset::class)->handle($actor, [
                    'code' => $validated['code'],
                    'name' => $validated['name'],
                    'category' => $validated['category'],
                    'brand' => $validated['brand'] ?? null,
                    'model' => $validated['model'] ?? null,
                    'unit' => $validated['unit'] ?? 'Unit',
                    'source_type' => $sourceType->value,
                    'condition' => $validated['condition'] ?? AssetCondition::Good->value,
                    'purchase_year' => $validated['purchase_year'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'request_number' => $validated['request_number'] ?? null,
                    'request_date' => $validated['request_date'] ?? null,
                    'request_description' => $validated['request_description'] ?? null,
                ]);
                $created++;
            }

            return [
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
            ];
        });
    }
}
