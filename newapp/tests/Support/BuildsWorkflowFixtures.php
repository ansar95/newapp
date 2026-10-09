<?php

namespace Tests\Support;

use App\Actions\Billing\CreateClientBilling;
use App\Actions\Billing\RecordClientPayment;
use App\Actions\Billing\SaveProjectTerm;
use App\Actions\Billing\SendClientBilling;
use App\Actions\Billing\SetProjectTermReadiness;
use App\Actions\Billing\SubmitClientBilling;
use App\Actions\Bmhp\ConfirmBmhpPurchase;
use App\Actions\Bmhp\CreateBmhpPurchase;
use App\Actions\Bmhp\CreateMasterBmhp;
use App\Actions\Bmhp\SaveBmhpPurchaseDetail;
use App\Actions\Bmhp\SubmitBmhpPurchase;
use App\Actions\BOQ\ConfirmBOQ;
use App\Actions\BOQ\CreateBOQ;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\Document\CreateProjectDocument;
use App\Actions\Document\StartProjectDocumentReview;
use App\Actions\Document\SubmitProjectDocument;
use App\Actions\Payment\CreatePaymentPlan;
use App\Actions\Payment\ProcessPayment;
use App\Actions\Project\ActivateProject;
use App\Actions\Project\CreateProject;
use App\Enums\PaymentPlanType;
use App\Enums\ProjectTermCalculationType;
use App\Enums\UserRole;
use App\Models\BmhpPurchase;
use App\Models\Boq;
use App\Models\ClientBilling;
use App\Models\ClientPayment;
use App\Models\DocumentType;
use App\Models\MasterBmhp;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectTerm;
use App\Models\User;
use Illuminate\Http\UploadedFile;

trait BuildsWorkflowFixtures
{
    protected function makeSuperadmin(array $attributes = []): User
    {
        return User::factory()->superadmin()->create($attributes);
    }

    protected function makeUserWithRole(UserRole $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    protected function makeProjectAdmin(array $attributes = []): User
    {
        return User::factory()->projectAdmin()->create($attributes);
    }

    protected function makeBoqCostControl(array $attributes = []): User
    {
        return User::factory()->boqCostControl()->create($attributes);
    }

    protected function makeApprover(array $attributes = []): User
    {
        return User::factory()->approver()->create($attributes);
    }

    protected function makeFinance(array $attributes = []): User
    {
        return User::factory()->finance()->create($attributes);
    }

    protected function makeProjectSite(array $attributes = []): User
    {
        return User::factory()->projectSite()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeActiveProject(User $actor, array $overrides = []): Project
    {
        $project = app(CreateProject::class)->handle($actor, array_merge([
            'code' => 'PRJ-001',
            'name' => 'Office Renovation',
            'client_name' => 'PT Contoh',
            'description' => 'Demo project',
            'start_date' => now()->toDateString(),
            'target_end_date' => now()->addMonths(3)->toDateString(),
        ], $overrides));

        return app(ActivateProject::class)->handle($actor, $project);
    }

    /**
     * @param  list<array<string, mixed>>|null  $cogs
     */
    protected function makeDraftBoq(User $actor, Project $project, ?array $cogs = null, string $code = 'BOQ-PRJ001'): Boq
    {
        return app(CreateBOQ::class)->handle($actor, $project, [
            'code' => $code,
            'revision_number' => 1,
            'cogs' => $cogs ?? $this->defaultCogsPayload(),
        ]);
    }

    protected function makeApprovedBoq(User $actor, Project $project, string $code = 'BOQ-PRJ001'): Boq
    {
        $boq = $this->makeDraftBoq($actor, $project, code: $code);
        app(SubmitBOQ::class)->handle($actor, $boq);
        app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Approved');

        return $boq->fresh(['cogs.items']);
    }

    protected function makePaidPlanForBoq(User $actor, Boq $boq, string $reference = 'PP-001', float $amount = 5000000): PaymentPlan
    {
        $plan = app(CreatePaymentPlan::class)->handle($actor, $boq, [
            'reference_code' => $reference,
            'type' => PaymentPlanType::ReadyToPay->value,
            'item_ids' => $this->boqItemIdsForAmount($boq, $amount),
        ]);

        app(ProcessPayment::class)->handle($actor, $plan, [
            'payment_code' => 'PAY-'.$reference,
            'payment_date' => now()->toDateString(),
            'amount' => $plan->amount,
            'payment_method' => 'bank_transfer',
            'reference_number' => 'TRX-001',
        ]);

        return $plan->fresh(['items']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMasterBmhp(User $actor, array $overrides = []): MasterBmhp
    {
        return app(CreateMasterBmhp::class)->handle($actor, array_merge([
            'name' => 'Gypsum 9mm',
            'category' => 'Material',
            'unit' => 'Lembar',
        ], $overrides));
    }

    /**
     * @param  list<array{0: MasterBmhp, 1: float|int, 2: float|int}>  $lines  [master, quantity, unit_price]
     */
    protected function makeDraftBmhpPurchase(User $actor, Project $project, array $lines = []): BmhpPurchase
    {
        $purchase = app(CreateBmhpPurchase::class)->handle($actor, $project, [
            'purchase_date' => now()->toDateString(),
            'supplier' => 'PT ABC Material',
            'invoice_number' => 'INV-001',
        ]);

        foreach ($lines as [$master, $quantity, $unitPrice]) {
            app(SaveBmhpPurchaseDetail::class)->handle($actor, $purchase, [
                'master_bmhp_id' => $master->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);
        }

        return $purchase->fresh(['details']);
    }

    protected function submitAndConfirmBmhpPurchase(User $actor, BmhpPurchase $purchase): BmhpPurchase
    {
        app(SubmitBmhpPurchase::class)->handle($actor, $purchase->fresh());

        return app(ConfirmBmhpPurchase::class)->handle($actor, $purchase->fresh());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeTerm(User $actor, Project $project, array $overrides = [], bool $ready = true): ProjectTerm
    {
        $term = app(SaveProjectTerm::class)->handle($actor, $project, array_merge([
            'term_name' => 'Uang Muka',
            'calculation_type' => ProjectTermCalculationType::Percentage->value,
            'percentage' => 30,
        ], $overrides));

        return $ready ? app(SetProjectTermReadiness::class)->handle($actor, $term, true) : $term;
    }

    protected function makeDraftBilling(User $actor, ProjectTerm $term, float $amount): ClientBilling
    {
        return app(CreateClientBilling::class)->handle($actor, $term->project, [
            'project_term_id' => $term->id,
            'billing_date' => now()->toDateString(),
            'billing_amount' => $amount,
        ]);
    }

    protected function makeSentBilling(User $actor, ProjectTerm $term, float $amount, ?string $invoiceNumber = null): ClientBilling
    {
        $billing = $this->makeDraftBilling($actor, $term, $amount);
        app(SubmitClientBilling::class)->handle($actor, $billing);

        return app(SendClientBilling::class)->handle($actor, $billing->fresh(), [
            'invoice_number' => $invoiceNumber ?? 'INV-'.$billing->billing_number,
            'invoice_date' => now()->toDateString(),
        ]);
    }

    protected function recordClientPayment(User $actor, ClientBilling $billing, float $amount): ClientPayment
    {
        return app(RecordClientPayment::class)->handle($actor, $billing->fresh(), [
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
        ]);
    }

    protected function fakePdf(string $name = 'dokumen.pdf', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    protected function billingDocumentType(): DocumentType
    {
        return DocumentType::query()->where('code', DocumentType::BILLING)->sole();
    }

    protected function makeBillingDocument(User $actor, ClientBilling $billing, ?UploadedFile $file = null): ProjectDocument
    {
        return app(CreateProjectDocument::class)->handle($actor, $billing->project, [
            'document_type_id' => $this->billingDocumentType()->id,
            'client_billing_id' => $billing->id,
            'document_title' => 'Dokumen Penagihan '.$billing->billing_number,
        ], $file ?? $this->fakePdf('Invoice V1.pdf'));
    }

    protected function submitAndStartDocumentReview(User $actor, ProjectDocument $document): ProjectDocument
    {
        app(SubmitProjectDocument::class)->handle($actor, $document->fresh());

        return app(StartProjectDocumentReview::class)->handle($actor, $document->fresh());
    }

    /**
     * Select BOQ item IDs whose totals match (or cover) the target amount.
     *
     * @return list<int>
     */
    protected function boqItemIdsForAmount(Boq $boq, float $amount): array
    {
        $boq->loadMissing('cogs.items');

        $items = $boq->cogs
            ->flatMap(fn ($cogs) => $cogs->items)
            ->sortBy('id')
            ->values();

        $exact = $items->first(
            fn ($item) => abs((float) $item->total_price - $amount) < 0.01
        );

        if ($exact !== null) {
            return [(int) $exact->id];
        }

        $ids = [];
        $sum = 0.0;

        foreach ($items as $item) {
            $ids[] = (int) $item->id;
            $sum += (float) $item->total_price;

            if ($sum + 0.01 >= $amount) {
                break;
            }
        }

        if ($ids === []) {
            throw new \RuntimeException('BOQ has no items available for payment planning.');
        }

        return $ids;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function defaultCogsPayload(): array
    {
        return [
            [
                'code' => 'COGS-001',
                'name' => 'Engineering',
                'description' => 'Engineering works',
                'sort_order' => 1,
                'items' => [
                    [
                        'code' => 'ITEM-E01',
                        'name' => 'Design Drawing',
                        'quantity' => 1,
                        'unit' => 'ls',
                        'unit_price' => 2000000,
                        'sort_order' => 1,
                    ],
                    [
                        'code' => 'ITEM-E02',
                        'name' => 'Structural Calculation',
                        'quantity' => 1,
                        'unit' => 'ls',
                        'unit_price' => 3000000,
                        'sort_order' => 2,
                    ],
                    [
                        'code' => 'ITEM-E03',
                        'name' => '3D Modeling',
                        'quantity' => 1,
                        'unit' => 'ls',
                        'unit_price' => 5000000,
                        'sort_order' => 3,
                    ],
                ],
            ],
            [
                'code' => 'COGS-002',
                'name' => 'Installation',
                'description' => 'Installation works',
                'sort_order' => 2,
                'items' => [
                    [
                        'code' => 'ITEM-I01',
                        'name' => 'Cable Tray',
                        'quantity' => 10,
                        'unit' => 'm',
                        'unit_price' => 100000,
                        'sort_order' => 1,
                    ],
                ],
            ],
        ];
    }
}
