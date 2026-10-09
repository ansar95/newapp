<?php

namespace App\Actions\Asset;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateAsset
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, array $data, ?UploadedFile $requestLetter = null): Asset
    {
        if (array_key_exists('purchase_year', $data) && $data['purchase_year'] === '') {
            $data['purchase_year'] = null;
        }

        $maxYear = (int) now()->year + 1;

        $validated = Validator::make($data, [
            'code' => ['required', 'string', 'max:50', 'unique:assets,code'],
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
            'request_status' => ['nullable', Rule::enum(AssetRequestStatus::class)],
            'request_letter_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'code.unique' => 'Kode asset sudah digunakan.',
            'category.in' => 'Kategori asset tidak valid.',
            'purchase_year.max' => 'Tahun pembelian tidak valid.',
        ])->validate();

        $sourceType = $validated['source_type'] instanceof AssetSourceType
            ? $validated['source_type']
            : AssetSourceType::from($validated['source_type']);

        $payload = [
            'code' => $validated['code'],
            'name' => $validated['name'],
            'category' => $validated['category'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'unit' => $validated['unit'] ?? 'Unit',
            'source_type' => $sourceType,
            'condition' => $validated['condition'] ?? AssetCondition::Good->value,
            'purchase_year' => $validated['purchase_year'] ?? null,
            'description' => $validated['description'] ?? null,
            'created_by' => $actor->id,
        ];

        if ($sourceType === AssetSourceType::Purchase) {
            Validator::make($data, [
                'request_number' => ['required', 'string', 'max:100'],
                'request_date' => ['required', 'date'],
                'request_description' => ['nullable', 'string'],
                'request_letter_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            ], [
                'request_number.required' => 'Nomor surat permohonan pembelian wajib diisi.',
                'request_date.required' => 'Tanggal surat permohonan pembelian wajib diisi.',
            ])->validate();

            $letterPath = null;
            if ($requestLetter !== null) {
                $letterPath = $requestLetter->store('asset-requests', 'public');
            }

            $payload = [
                ...$payload,
                'status' => AssetStatus::Requested,
                'request_number' => $validated['request_number'],
                'request_date' => $validated['request_date'],
                'request_description' => $validated['request_description'] ?? null,
                'request_letter_file' => $letterPath,
                'requested_by' => $actor->id,
                'request_status' => $validated['request_status'] ?? AssetRequestStatus::Requested->value,
            ];
        } else {
            $payload = [
                ...$payload,
                'status' => AssetStatus::Available,
                'request_number' => null,
                'request_date' => null,
                'request_description' => null,
                'request_letter_file' => null,
                'requested_by' => null,
                'request_status' => null,
            ];
        }

        return Asset::query()->create($payload);
    }
}
