<?php

namespace Database\Factories;

use App\Enums\ClientPaymentStatus;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientPayment>
 */
class ClientPaymentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_billing_id' => ClientBilling::factory()->sent(),
            'payment_number' => 'RCV-'.fake()->unique()->numerify('######'),
            'payment_date' => now()->toDateString(),
            'amount' => fake()->randomFloat(2, 100000, 1000000),
            'bank_reference' => fake()->optional()->bothify('TRF-#####'),
            'notes' => null,
            'status' => ClientPaymentStatus::Received,
            'created_by' => User::factory(),
        ];
    }
}
