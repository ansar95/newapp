<?php

namespace Tests\Feature;

use App\Actions\Billing\BillingMessages;
use App\Actions\Billing\CancelClientBilling;
use App\Actions\Billing\RecordClientPayment;
use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Enums\ProjectTermStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\ClientPayment;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectBillingSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ClientPaymentTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
    }

    public function test_finance_can_record_client_payment(): void
    {
        $finance = $this->makeFinance();
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);

        $this->actingAs($finance)
            ->post(route('client-payments.store', $billing), [
                'payment_date' => now()->toDateString(),
                'amount' => '100000000',
                'bank_reference' => 'TRF-001',
            ])
            ->assertRedirect(route('client-billings.show', $billing))
            ->assertSessionHasNoErrors();

        $payment = ClientPayment::query()->sole();

        $this->assertSame('RCV-0001', $payment->payment_number);
        $this->assertSame(ClientPaymentStatus::Received, $payment->status);
        $this->assertSame(100_000_000.0, (float) $payment->amount);
    }

    public function test_payment_cannot_exceed_outstanding(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
        $this->recordClientPayment($this->admin, $billing, 200_000_000);

        $this->actingAs($this->admin)
            ->post(route('client-payments.store', $billing), [
                'payment_date' => now()->toDateString(),
                'amount' => '100000000.01',
            ])
            ->assertSessionHasErrors(['business' => BillingMessages::PAYMENT_EXCEEDS_OUTSTANDING]);

        $this->assertSame(1, ClientPayment::query()->count());
    }

    public function test_payment_requires_sent_billing(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeDraftBilling($this->admin, $term, 300_000_000);

        $this->expectException(BusinessRuleException::class);

        app(RecordClientPayment::class)->handle($this->admin, $billing, [
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
        ]);
    }

    public function test_partial_payment_marks_billing_and_term_partially_paid(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
        $this->recordClientPayment($this->admin, $billing, 100_000_000);

        $billing = $billing->fresh();
        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(ClientBillingStatus::Sent, $billing->status);
        $this->assertSame(ClientBillingStatus::PartiallyPaid, $billing->displayStatus());
        $this->assertSame(200_000_000.0, $billing->outstandingAmount());
        $this->assertSame(ProjectTermStatus::PartiallyPaid, $summary->termStatus($term));
    }

    public function test_full_payment_marks_billing_and_term_paid(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
        $this->recordClientPayment($this->admin, $billing, 100_000_000);
        $this->recordClientPayment($this->admin, $billing, 200_000_000);

        $billing = $billing->fresh();

        $this->assertSame(ClientBillingStatus::Paid, $billing->displayStatus());
        $this->assertSame(0.0, $billing->outstandingAmount());
        $this->assertSame(ProjectTermStatus::Paid, ProjectBillingSummary::forProject($this->project)->termStatus($term));
        $this->assertFalse($this->admin->can('recordPayment', $billing));
    }

    public function test_cancelled_payment_is_excluded_and_kept_in_history(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
        $payment = $this->recordClientPayment($this->admin, $billing, 100_000_000);

        $this->actingAs($this->admin)
            ->post(route('client-payments.cancel', $payment), ['cancel_reason' => 'Dana dikembalikan'])
            ->assertSessionHasNoErrors();

        $summary = ProjectBillingSummary::forProject($this->project);

        $this->assertSame(ClientPaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(0.0, $summary->totalReceived());
        $this->assertSame(300_000_000.0, $summary->outstanding());
        $this->assertSame(300_000_000.0, $billing->fresh()->outstandingAmount());
        $this->assertSame(1, ClientPayment::query()->count());
    }

    public function test_billing_with_received_payment_cannot_be_cancelled(): void
    {
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000);
        $this->recordClientPayment($this->admin, $billing, 100_000_000);

        $this->expectException(BusinessRuleException::class);

        app(CancelClientBilling::class)->handle($this->admin, $billing->fresh(), ['cancel_reason' => 'Salah nilai']);
    }
}
