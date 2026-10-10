<?php

namespace App\Actions\Procurement;

use App\Actions\Asset\AssignAssetToProject;
use App\Enums\AssetCondition;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\ProcurementRequest;
use App\Models\ProcurementRequestItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayProcurementRequest
{
    public function __construct(
        private AssignAssetToProject $assignAssetToProject,
    ) {}

    /**
     * Records the single full payment with the final price per item. Paid asset requests create one asset per unit and assign it to the project.
     *
     * @param  array{
     *     payment_date: string,
     *     invoice_number?: string|null,
     *     items: array<int|string, array{paid_unit_price: float|int|string}>
     * }  $data
     */
    public function handle(User $actor, ProcurementRequest $request, array $data, ?UploadedFile $paymentProof = null): ProcurementRequest
    {
        $request->assertStatus(ProcurementStatus::Approved, 'dibayar');
        $request->assertProjectAcceptsRequests();
        $request->loadMissing('items', 'project');

        $validated = Validator::make([...$data, 'payment_proof_file' => $paymentProof], [
            'payment_date' => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'payment_proof_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'items' => ['required', 'array'],
            'items.*.paid_unit_price' => ['required', 'numeric', 'min:0'],
        ], [
            'payment_date.required' => 'Tanggal bayar wajib diisi.',
            'items.*.paid_unit_price.required' => 'Harga final wajib diisi untuk setiap item.',
            'items.*.paid_unit_price.min' => 'Harga final tidak boleh kurang dari 0.',
            'payment_proof_file.mimes' => 'Bukti bayar harus berupa PDF, JPG, atau PNG.',
            'payment_proof_file.max' => 'Ukuran bukti bayar maksimal 5 MB.',
        ])->validate();

        $paidPrices = collect($validated['items'])->mapWithKeys(
            fn (array $row, int|string $itemId): array => [(int) $itemId => $row['paid_unit_price']]
        );

        if ($paidPrices->keys()->sort()->values()->all() !== $request->items->pluck('id')->sort()->values()->all()) {
            throw BusinessRuleException::make('Harga final harus diisi untuk semua item pada permintaan ini.');
        }

        $proofPath = $paymentProof?->store('procurement-payments', 'public');

        return DB::transaction(function () use ($actor, $request, $validated, $paidPrices, $proofPath): ProcurementRequest {
            foreach ($request->items as $item) {
                $item->paid_unit_price = $paidPrices[$item->id];
                $item->save();
            }

            $request->status = ProcurementStatus::Paid;
            $request->paid_by = $actor->id;
            $request->paid_at = now();
            $request->payment_date = $validated['payment_date'];
            $request->invoice_number = $validated['invoice_number'] ?? null;
            $request->paid_total = number_format((float) $request->items()->sum('paid_total'), 2, '.', '');

            if ($proofPath !== null) {
                $request->payment_proof_file = $proofPath;
            }

            $request->save();

            if ($request->type === ProcurementType::Asset) {
                foreach ($request->items as $item) {
                    $this->registerAssets($actor, $request, $item);
                }
            }

            return $request->fresh(['items.assets']);
        });
    }

    private function registerAssets(User $actor, ProcurementRequest $request, ProcurementRequestItem $item): void
    {
        for ($unit = 1; $unit <= (int) $item->quantity; $unit++) {
            $asset = Asset::query()->create([
                'code' => Asset::nextCode(),
                'name' => $item->item_name,
                'category' => $item->asset_category,
                'brand' => $item->brand,
                'model' => $item->model,
                'unit' => $item->unit,
                'source_type' => AssetSourceType::Purchase,
                'status' => AssetStatus::Available,
                'condition' => AssetCondition::Good,
                'purchase_year' => $request->payment_date?->year,
                'description' => $item->notes,
                'request_number' => $request->request_number,
                'request_date' => $request->request_date,
                'requested_by' => $request->created_by,
                'request_status' => AssetRequestStatus::Purchased,
                'created_by' => $actor->id,
                'procurement_request_item_id' => $item->id,
            ]);

            $this->assignAssetToProject->handle($actor, $request->project, [
                'asset_id' => $asset->id,
                'notes' => 'Pembelian dari permintaan '.$request->request_number.'.',
            ]);
        }
    }
}
