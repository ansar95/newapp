<?php

namespace App\Actions\Payment;

use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VoidPayment
{
    /**
     * A VOID payment keeps its allocations for audit but no longer counts as actual cost.
     * The plan returns to READY_TO_PAY so the correct payment can be recorded.
     *
     * @param  array{void_reason?: string|null}  $data
     */
    public function handle(User $actor, Payment $payment, array $data): Payment
    {
        if ($payment->status !== PaymentStatus::Paid) {
            throw BusinessRuleException::make('Hanya payment berstatus PAID yang dapat dibatalkan (VOID).');
        }

        $validated = Validator::make($data, [
            'void_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ], [
            'void_reason.required' => 'Alasan pembatalan payment wajib diisi.',
            'void_reason.min' => 'Alasan pembatalan payment minimal 3 karakter.',
        ])->validate();

        return DB::transaction(function () use ($actor, $payment, $validated): Payment {
            $payment->forceFill([
                'status' => PaymentStatus::Void,
                'voided_by' => $actor->id,
                'voided_at' => now(),
                'void_reason' => $validated['void_reason'],
            ])->save();

            $plan = $payment->paymentPlan;

            if ($plan !== null && $plan->status === PaymentPlanStatus::Paid) {
                $plan->status = PaymentPlanStatus::ReadyToPay;
                $plan->save();
            }

            return $payment->refresh();
        });
    }
}
