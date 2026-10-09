<?php

namespace Tests\Feature;

use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvalidPaymentPlanException;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_be_recorded_when_plan_is_ready_to_pay(): void
    {
        $user = User::factory()->create();
        $plan = PaymentPlan::factory()->readyToPay()->create([
            'created_by' => $user->id,
            'amount' => 10000000,
        ]);

        $payment = $plan->recordPayment([
            'payment_code' => 'PAY-001',
            'payment_date' => now()->toDateString(),
            'amount' => 10000000,
            'paid_by' => $user->id,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-001',
        ]);

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentPlanStatus::Paid, $plan->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'payment_plan_id' => $plan->id,
            'payment_code' => 'PAY-001',
        ]);
    }

    public function test_payment_cannot_be_created_when_plan_is_still_planned(): void
    {
        $user = User::factory()->create();
        $plan = PaymentPlan::factory()->create([
            'created_by' => $user->id,
            'status' => PaymentPlanStatus::Planned,
        ]);

        $this->expectException(InvalidPaymentPlanException::class);

        Payment::factory()->create([
            'payment_plan_id' => $plan->id,
            'paid_by' => $user->id,
        ]);
    }

    public function test_payment_cannot_be_created_when_plan_is_already_paid(): void
    {
        $user = User::factory()->create();
        $plan = PaymentPlan::factory()->readyToPay()->create([
            'created_by' => $user->id,
        ]);

        $plan->recordPayment([
            'payment_code' => 'PAY-001',
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'paid_by' => $user->id,
        ]);

        $this->expectException(InvalidPaymentPlanException::class);

        Payment::factory()->create([
            'payment_plan_id' => $plan->id,
            'payment_code' => 'PAY-002',
            'paid_by' => $user->id,
        ]);
    }
}
