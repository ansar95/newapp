<?php

namespace App\Actions\Asset;

use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateAsset
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $actor, Asset $asset, array $data, ?UploadedFile $requestLetter = null): Asset
    {
        if ($asset->status === AssetStatus::InUse && isset($data['status']) && $data['status'] !== AssetStatus::InUse->value) {
            throw BusinessRuleException::make(
                'Asset yang sedang IN_USE tidak dapat diubah statusnya kecuali melalui release assignment.'
            );
        }

        if (array_key_exists('purchase_year', $data) && $data['purchase_year'] === '') {
            $data['purchase_year'] = null;
        }

        $maxYear = (int) now()->year + 1;

        $validated = Validator::make($data, [
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('assets', 'code')->ignore($asset->id)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'category' => ['sometimes', 'required', 'string', Rule::in(Asset::categoryOptions())],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'source_type' => ['sometimes', 'required', Rule::enum(AssetSourceType::class)],
            'status' => ['sometimes', 'required', Rule::enum(AssetStatus::class)],
            'condition' => ['sometimes', 'nullable', Rule::enum(AssetCondition::class)],
            'purchase_year' => ['sometimes', 'nullable', 'integer', 'min:1990', 'max:'.$maxYear],
            'description' => ['sometimes', 'nullable', 'string'],
            'request_number' => ['sometimes', 'nullable', 'string', 'max:100'],
            'request_date' => ['sometimes', 'nullable', 'date'],
            'request_description' => ['sometimes', 'nullable', 'string'],
            'request_status' => ['sometimes', 'nullable', Rule::enum(AssetRequestStatus::class)],
            'request_letter_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'code.unique' => 'Kode asset sudah digunakan.',
            'category.in' => 'Kategori asset tidak valid.',
            'purchase_year.max' => 'Tahun pembelian tidak valid.',
        ])->validate();

        $sourceType = isset($validated['source_type'])
            ? ($validated['source_type'] instanceof AssetSourceType
                ? $validated['source_type']
                : AssetSourceType::from($validated['source_type']))
            : $asset->source_type;

        if ($sourceType === AssetSourceType::Purchase) {
            $requestNumber = $validated['request_number'] ?? $asset->request_number;
            $requestDate = $validated['request_date'] ?? $asset->request_date?->toDateString();

            if ($requestNumber === null || $requestNumber === '' || $requestDate === null) {
                throw BusinessRuleException::make(
                    'Asset pembelian wajib memiliki nomor dan tanggal surat permohonan.'
                );
            }

            if ($requestLetter !== null) {
                if ($asset->request_letter_file) {
                    Storage::disk('public')->delete($asset->request_letter_file);
                }
                $validated['request_letter_file'] = $requestLetter->store('asset-requests', 'public');
            }

            if (! isset($validated['requested_by']) && $asset->requested_by === null) {
                $validated['requested_by'] = $actor->id;
            }

            if (! isset($validated['request_status']) && $asset->request_status === null) {
                $validated['request_status'] = AssetRequestStatus::Requested;
            }
        } else {
            $validated['request_number'] = null;
            $validated['request_date'] = null;
            $validated['request_description'] = null;
            $validated['request_status'] = null;
            $validated['requested_by'] = null;
            if ($asset->request_letter_file) {
                Storage::disk('public')->delete($asset->request_letter_file);
            }
            $validated['request_letter_file'] = null;
        }

        $asset->fill($validated);
        $asset->source_type = $sourceType;
        $asset->save();

        return $asset->refresh();
    }
}
