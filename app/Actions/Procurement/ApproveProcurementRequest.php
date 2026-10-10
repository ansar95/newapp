<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementStatus;
use App\Models\ProcurementRequest;
use App\Models\User;

class ApproveProcurementRequest
{
    public function handle(User $actor, ProcurementRequest $request): ProcurementRequest
    {
        $request->assertStatus(ProcurementStatus::Submitted, 'disetujui');
        $request->assertProjectAcceptsRequests();

        $request->status = ProcurementStatus::Approved;
        $request->approved_by = $actor->id;
        $request->approved_at = now();
        $request->save();

        return $request->refresh();
    }
}
