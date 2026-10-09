<?php

namespace App\Actions\Bmhp;

use App\Enums\BmhpPurchaseStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BmhpPurchase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateBmhpPurchase
{
    /**
     * @param  array{
     *     purchase_date: string,
     *     supplier: string,
     *     invoice_number?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function handle(User $actor, Project $project, array $data, ?UploadedFile $receipt = null): BmhpPurchase
    {
        if (! BmhpPurchase::projectAcceptsPurchases($project)) {
            throw BusinessRuleException::make('Pembelian BMHP hanya dapat dicatat pada project berstatus ACTIVE.');
        }

        $validated = Validator::make([...$data, 'receipt_file' => $receipt], BmhpPurchaseRules::header(), BmhpPurchaseRules::headerMessages())
            ->validate();

        $receiptPath = $receipt?->store('bmhp-receipts', 'public');

        return DB::transaction(fn (): BmhpPurchase => BmhpPurchase::query()->create([
            'project_id' => $project->id,
            'purchase_number' => BmhpPurchase::nextPurchaseNumber(),
            'purchase_date' => $validated['purchase_date'],
            'supplier' => $validated['supplier'],
            'invoice_number' => $validated['invoice_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'receipt_file' => $receiptPath,
            'total_amount' => 0,
            'status' => BmhpPurchaseStatus::Draft,
            'created_by' => $actor->id,
        ]));
    }
}
