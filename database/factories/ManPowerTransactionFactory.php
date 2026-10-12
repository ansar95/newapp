<?php

namespace Database\Factories;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\ManPowerTransactionType;
use App\Models\ManPowerTransaction;
use App\Models\ProjectManPower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManPowerTransaction>
 */
class ManPowerTransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_man_power_id' => ProjectManPower::factory(),
            'type' => ManPowerTransactionType::Kasbon,
            'transaction_date' => now()->toDateString(),
            'amount' => 100000,
            'notes' => null,
            'status' => ManPowerTransactionStatus::Paid,
            'created_by' => User::factory(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => ManPowerTransactionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancel_reason' => 'Salah input',
        ]);
    }
}
