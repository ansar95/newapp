<?php

namespace Database\Seeders;

use App\Enums\ClientBillingStatus;
use App\Enums\ClientPaymentStatus;
use App\Enums\ProjectTermCalculationType;
use App\Enums\ProjectTermReadiness;
use App\Enums\ProjectTermTriggerType;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\Project;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::query()->first();
        $project = Project::query()->where('code', 'PRJ-005')->first();

        if ($admin === null || $project === null || (float) $project->contract_value <= 0) {
            return;
        }

        $billingIds = ClientBilling::query()->where('project_id', $project->id)->pluck('id');
        ClientPayment::query()->whereIn('client_billing_id', $billingIds)->delete();
        ClientBilling::query()->whereKey($billingIds)->delete();
        ProjectTerm::query()->where('project_id', $project->id)->delete();

        $contract = (float) $project->contract_value;
        $terms = collect([
            [1, 'Uang Muka', 20, ProjectTermTriggerType::Manual, 'Setelah kontrak ditandatangani', ProjectTermReadiness::Ready, null],
            [2, 'Progress 50%', 30, ProjectTermTriggerType::Progress, 'Progress fisik 50%', ProjectTermReadiness::Ready, 50],
            [3, 'Progress 100%', 30, ProjectTermTriggerType::Progress, 'Progress fisik 100%', ProjectTermReadiness::NotReady, 100],
            [4, 'Retensi', 20, ProjectTermTriggerType::Milestone, 'BAST / masa pemeliharaan selesai', ProjectTermReadiness::NotReady, null],
        ])->mapWithKeys(fn (array $row) => [$row[0] => ProjectTerm::query()->create([
            'project_id' => $project->id,
            'term_number' => $row[0],
            'term_name' => $row[1],
            'calculation_type' => ProjectTermCalculationType::Percentage,
            'percentage' => $row[2],
            'amount' => round($contract * $row[2] / 100, 2),
            'trigger_type' => $row[3],
            'trigger_value' => $row[4],
            'trigger_progress' => $row[6],
            'readiness' => $row[5],
            'created_by' => $admin->id,
        ])]);

        $downPayment = $this->sentBilling($project, $terms[1], $admin, 'BIL-0001', 'INV/SBA/2026/001', (float) $terms[1]->amount, 50);
        $this->receivedPayment($downPayment, $admin, 'RCV-0001', (float) $terms[1]->amount, 40, 'TRF-BCA-0001');

        $halfProgress = round((float) $terms[2]->amount / 2, 2);
        $progressBilling = $this->sentBilling($project, $terms[2], $admin, 'BIL-0002', 'INV/SBA/2026/002', $halfProgress, 10);
        $this->receivedPayment($progressBilling, $admin, 'RCV-0002', 300_000_000, 3, 'TRF-BCA-0002');

        ClientBilling::query()->create([
            'project_id' => $project->id,
            'project_term_id' => $terms[2]->id,
            'billing_number' => 'BIL-0003',
            'billing_date' => now()->toDateString(),
            'billing_amount' => (float) $terms[2]->amount - $halfProgress,
            'description' => 'Sisa termin progress 50%.',
            'status' => ClientBillingStatus::Draft,
            'created_by' => $admin->id,
        ]);
    }

    private function sentBilling(Project $project, ProjectTerm $term, User $admin, string $number, string $invoice, float $amount, int $daysAgo): ClientBilling
    {
        return ClientBilling::query()->create([
            'project_id' => $project->id,
            'project_term_id' => $term->id,
            'billing_number' => $number,
            'billing_date' => now()->subDays($daysAgo)->toDateString(),
            'billing_amount' => $amount,
            'invoice_number' => $invoice,
            'invoice_date' => now()->subDays($daysAgo)->toDateString(),
            'due_date' => now()->subDays($daysAgo)->addDays(30)->toDateString(),
            'description' => 'Penagihan '.$term->displayName().'.',
            'status' => ClientBillingStatus::Sent,
            'created_by' => $admin->id,
            'submitted_by' => $admin->id,
            'submitted_at' => now()->subDays($daysAgo),
            'sent_by' => $admin->id,
            'sent_at' => now()->subDays($daysAgo),
        ]);
    }

    private function receivedPayment(ClientBilling $billing, User $admin, string $number, float $amount, int $daysAgo, string $reference): void
    {
        ClientPayment::query()->create([
            'client_billing_id' => $billing->id,
            'payment_number' => $number,
            'payment_date' => now()->subDays($daysAgo)->toDateString(),
            'amount' => $amount,
            'bank_reference' => $reference,
            'status' => ClientPaymentStatus::Received,
            'created_by' => $admin->id,
        ]);
    }
}
