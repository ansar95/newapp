<?php

namespace App\Actions\Payment;

use App\Enums\PaymentPlanStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\PaymentPlan;
use App\Models\User;

class MarkPaymentPlanReady
{
    public function handle(User $actor, PaymentPlan $paymentPlan): PaymentPlan
    {
        if ($paymentPlan->status === PaymentPlanStatus::Paid) {
            throw BusinessRuleException::make('Payment Plan yang sudah PAID tidak dapat diubah.');
        }

        if ($paymentPlan->status === PaymentPlanStatus::ReadyToPay) {
            return $paymentPlan;
        }

        if ($paymentPlan->status !== PaymentPlanStatus::Planned) {
            throw BusinessRuleException::make(
                'Payment Plan hanya dapat diubah menjadi READY_TO_PAY dari status PLANNED.'
            );
        }

        $paymentPlan->markReadyToPay();

        return $paymentPlan->refresh();
    }
}
