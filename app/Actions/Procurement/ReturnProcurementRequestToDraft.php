<?php

namespace App\Actions\Procurement;

use App\Enums\ProcurementStatus;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class ReturnProcurementRequestToDraft
{
    /**
     * @param  array{return_note?: string|null}  $data
     */
    public function handle(User $actor, ProcurementRequest $request, array $data): ProcurementRequest
    {
        $request->assertStatus(ProcurementStatus::Submitted, 'dikembalikan ke Draft');
        $request->assertProjectAcceptsRequests();

        $validated = Validator::make($data, [
            'return_note' => ['required', 'string', 'max:1000'],
        ], [
            'return_note.required' => 'Catatan pengembalian wajib diisi.',
        ])->validate();

        $request->status = ProcurementStatus::Draft;
        $request->returned_by = $actor->id;
        $request->returned_at = now();
        $request->return_note = $validated['return_note'];
        $request->save();

        return $request->refresh();
    }
}
