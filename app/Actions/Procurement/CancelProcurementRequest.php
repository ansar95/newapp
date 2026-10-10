<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CancelProcurementRequest
{
    /**
     * @param  array{cancel_reason?: string|null}  $data
     */
    public function handle(User $actor, ProcurementRequest $request, array $data): ProcurementRequest
    {
        if (! $request->status->isCancellable()) {
            throw BusinessRuleException::make(
                'Permintaan berstatus '.$request->status->label().' tidak dapat dibatalkan. Permintaan yang sudah dibayar tidak bisa dibatalkan.'
            );
        }

        $request->assertProjectAcceptsRequests();

        $validated = Validator::make($data, [
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ], [
            'cancel_reason.required' => 'Alasan pembatalan wajib diisi.',
        ])->validate();

        $request->status = ProcurementStatus::Cancelled;
        $request->cancelled_by = $actor->id;
        $request->cancelled_at = now();
        $request->cancel_reason = $validated['cancel_reason'];
        $request->save();

        return $request->refresh();
    }
}
