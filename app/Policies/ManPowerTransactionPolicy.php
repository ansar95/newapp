<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ManPowerTransaction;
use App\Models\User;

class ManPowerTransactionPolicy
{
    public function cancel(User $user, ManPowerTransaction $transaction): bool
    {
        $transaction->loadMissing('projectManPower.project');

        return $user->hasPermission(Permission::ManPowerPay)
            && $transaction->isPaid()
            && $transaction->projectManPower->project->acceptsManPowerPayment();
    }
}
