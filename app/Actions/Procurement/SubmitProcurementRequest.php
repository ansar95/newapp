<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ProcurementRequest;
use App\Models\User;

class SubmitProcurementRequest
{
    public function handle(User $actor, ProcurementRequest $request): ProcurementRequest
    {
        $request->assertStatus(ProcurementStatus::Draft, 'diajukan');
        $request->assertProjectAcceptsRequests();

        if (! $request->items()->exists()) {
            throw BusinessRuleException::make('Tambahkan minimal satu item sebelum mengajukan permintaan.');
        }

        $request->status = ProcurementStatus::Submitted;
        $request->submitted_by = $actor->id;
        $request->submitted_at = now();
        $request->save();

        return $request->refresh();
    }
}
