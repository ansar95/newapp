<?php

namespace App\Policies;

use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PaymentPlanView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasPermission(Permission::PaymentPlanView);
    }

    /**
     * Process payment against a READY_TO_PAY payment plan (domain status lives on PaymentPlan).
     */
    public function process(User $user, PaymentPlan $paymentPlan): bool
    {
        if (! $user->hasPermission(Permission::PaymentProcess)) {
            return false;
        }

        if ($paymentPlan->status === PaymentPlanStatus::Paid) {
            return false;
        }

        if ($paymentPlan->status !== PaymentPlanStatus::ReadyToPay) {
            return false;
        }

        $paymentPlan->loadMissing('boq');

        return $paymentPlan->boq?->status === BoqStatus::Approved;
    }

    public function void(User $user, Payment $payment): bool
    {
        return $user->hasPermission(Permission::PaymentProcess)
            && $payment->status === PaymentStatus::Paid;
    }
}
