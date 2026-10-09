<?php

namespace Database\Seeders;

use App\Enums\BoqItemCategory;
use App\Enums\BoqStatus;
use App\Enums\PaymentPlanStatus;
use App\Enums\PaymentPlanType;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStatus;
use App\Enums\RevisionItemStatus;
use App\Enums\RevisionStatus;
use App\Enums\UserRole;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\BoqRevisionItem;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProjectManagementSeeder extends Seeder
{
    /**
     * Seed realistic screening/demo projects covering the full workflow.
     */
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Superadmin,
                'email_verified_at' => now(),
            ]
        );

        $this->seedDraftProject($admin);              // A — BOQ DRAFT
        $this->seedWaitingApprovalProject($admin);    // B — WAITING APPROVAL
        $this->seedRevisionRequiredProject($admin);   // C — REVISION REQUIRED
        $this->seedApprovedWithPaymentProject($admin); // D — APPROVED + PAYMENT
        $this->seedInProgressProject($admin);         // E — IN PROGRESS
    }

    private function seedDraftProject(User $admin): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-001'],
            [
                'name' => 'Engineering & Installation Project',
                'client_name' => 'PT Nusantara Logistik',
                'contract_number' => '001/KTR/NL/2026',
                'contract_value' => 1_250_000_000,
                'description' => 'Perluasan gudang termasuk engineering design dan instalasi utilitas.',
                'start_date' => now()->toDateString(),
                'target_end_date' => now()->addMonths(5)->toDateString(),
                'status' => ProjectStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        $boq = Boq::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'code' => 'BOQ-PRJ001',
                'revision_number' => 1,
            ],
            [
                'status' => BoqStatus::Draft,
                'submitted_by' => null,
                'submitted_at' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]
        );

        $this->seedStandardCogs($boq, withProgress: false);
    }

    private function seedWaitingApprovalProject(User $admin): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-002'],
            [
                'name' => 'Data Center Fit-Out Phase 2',
                'client_name' => 'PT Mega Data Indonesia',
                'contract_number' => '002/KTR/MDI/2026',
                'contract_value' => 2_400_000_000,
                'description' => 'Fit-out ruang data center: rack layout, cabling, dan testing.',
                'start_date' => now()->subWeeks(2)->toDateString(),
                'target_end_date' => now()->addMonths(3)->toDateString(),
                'status' => ProjectStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        $boq = Boq::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'code' => 'BOQ-PRJ002',
                'revision_number' => 1,
            ],
            [
                'status' => BoqStatus::WaitingApproval,
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subDay(),
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
            ]
        );

        $this->seedStandardCogs($boq, withProgress: false, prefix: 'DC');
    }

    private function seedApprovedWithPaymentProject(User $admin): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-004'],
            [
                'name' => 'Plant Utility Upgrade — Cooling System',
                'client_name' => 'PT Industri Sejahtera',
                'contract_number' => '003/KTR/IS/2026',
                'contract_value' => 850_000_000,
                'description' => 'Upgrade sistem pendingin pabrik. BOQ sudah approved dan siap/ sebagian dibayar.',
                'start_date' => now()->subMonths(1)->toDateString(),
                'target_end_date' => now()->addMonths(3)->toDateString(),
                'status' => ProjectStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        $boq = Boq::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'code' => 'BOQ-PRJ004',
                'revision_number' => 1,
            ],
            [
                'status' => BoqStatus::Approved,
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subDays(10),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(8),
                'review_notes' => 'Disetujui untuk eksekusi.',
            ]
        );

        $this->seedStandardCogs($boq, withProgress: false, prefix: 'UT');

        $plan = PaymentPlan::query()->firstOrCreate(
            ['reference_code' => 'PP-PRJ004-001'],
            [
                'boq_id' => $boq->id,
                'type' => PaymentPlanType::ReadyToPay,
                'planned_date' => now()->subDays(5)->toDateString(),
                'amount' => 40000000,
                'status' => PaymentPlanStatus::ReadyToPay,
                'notes' => 'Down payment',
                'created_by' => $admin->id,
            ]
        );

        $this->syncPlanItems($plan, $boq, [
            'ITEM-UT-E01',
            'ITEM-UT-E02',
            'ITEM-UT-E03',
            'ITEM-UT-I02',
        ]);

        if (! $plan->payments()->exists()) {
            if ($plan->status !== PaymentPlanStatus::ReadyToPay) {
                $plan->update(['status' => PaymentPlanStatus::ReadyToPay]);
            }

            Payment::query()->create([
                'payment_plan_id' => $plan->id,
                'payment_code' => 'PAY-PRJ004-001',
                'payment_date' => now()->subDays(4)->toDateString(),
                'amount' => $plan->fresh()->amount,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TRX-UT-44112',
                'status' => PaymentStatus::Paid,
                'notes' => 'Pembayaran diterima',
                'paid_by' => $admin->id,
            ]);
        } else {
            $plan->payments()->update(['amount' => $plan->fresh()->amount]);
        }

        $plan->update(['status' => PaymentPlanStatus::Paid]);
        $this->syncPaymentAllocations($plan);

        $readyPlan = PaymentPlan::query()->firstOrCreate(
            ['reference_code' => 'PP-PRJ004-002'],
            [
                'boq_id' => $boq->id,
                'type' => PaymentPlanType::ReadyToPay,
                'planned_date' => now()->addWeek()->toDateString(),
                'amount' => 25000000,
                'status' => PaymentPlanStatus::ReadyToPay,
                'notes' => 'Siap diproses',
                'created_by' => $admin->id,
            ]
        );

        $this->syncPlanItems($readyPlan, $boq, [
            'ITEM-UT-T01',
            'ITEM-UT-I01',
        ]);
    }

    private function seedInProgressProject(User $admin): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-005'],
            [
                'name' => 'Office Network Modernization — Phase Live',
                'client_name' => 'PT Sentral Bisnis Asia',
                'contract_number' => '004/KTR/SBA/2026',
                'contract_value' => 3_100_000_000,
                'description' => 'Project berjalan: BOQ approved, payment paid, progress sedang diupdate.',
                'start_date' => now()->subMonths(2)->toDateString(),
                'target_end_date' => now()->addMonths(2)->toDateString(),
                'status' => ProjectStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        $boq = Boq::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'code' => 'BOQ-PRJ005',
                'revision_number' => 1,
            ],
            [
                'status' => BoqStatus::Approved,
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subDays(30),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(28),
                'review_notes' => 'Approved for site execution.',
            ]
        );

        $this->seedStandardCogs($boq, withProgress: true, prefix: 'LV');

        $plan = PaymentPlan::query()->firstOrCreate(
            ['reference_code' => 'PP-PRJ005-001'],
            [
                'boq_id' => $boq->id,
                'type' => PaymentPlanType::ReadyToPay,
                'planned_date' => now()->subDays(20)->toDateString(),
                'amount' => 50000000,
                'status' => PaymentPlanStatus::ReadyToPay,
                'notes' => 'Initial payment',
                'created_by' => $admin->id,
            ]
        );

        $this->syncPlanItems($plan, $boq, [
            'ITEM-LV-E01',
            'ITEM-LV-E02',
            'ITEM-LV-E03',
            'ITEM-LV-P01',
            'ITEM-LV-I02',
        ]);

        if (! $plan->payments()->exists()) {
            if ($plan->status !== PaymentPlanStatus::ReadyToPay) {
                $plan->update(['status' => PaymentPlanStatus::ReadyToPay]);
            }

            Payment::query()->create([
                'payment_plan_id' => $plan->id,
                'payment_code' => 'PAY-PRJ005-001',
                'payment_date' => now()->subDays(19)->toDateString(),
                'amount' => $plan->fresh()->amount,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'TRX-LV-99001',
                'status' => PaymentStatus::Paid,
                'notes' => 'Pembayaran diterima',
                'paid_by' => $admin->id,
            ]);
        } else {
            $plan->payments()->update(['amount' => $plan->fresh()->amount]);
        }

        $plan->update(['status' => PaymentPlanStatus::Paid]);
        $this->syncPaymentAllocations($plan);
    }

    private function seedRevisionRequiredProject(User $admin): void
    {
        $project = Project::query()->updateOrCreate(
            ['code' => 'PRJ-003'],
            [
                'name' => 'Retail Fit-Out — Structured Cabling',
                'client_name' => 'PT Retail Prima',
                'contract_number' => '005/KTR/RP/2026',
                'contract_value' => 640_000_000,
                'description' => 'BOQ dikembalikan untuk revisi harga item tertentu.',
                'start_date' => now()->subWeeks(1)->toDateString(),
                'target_end_date' => now()->addMonths(4)->toDateString(),
                'status' => ProjectStatus::Active,
                'created_by' => $admin->id,
            ]
        );

        $boq = Boq::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'code' => 'BOQ-PRJ003',
                'revision_number' => 1,
            ],
            [
                'status' => BoqStatus::RevisionRequired,
                'submitted_by' => $admin->id,
                'submitted_at' => now()->subDays(4),
                'reviewed_by' => $admin->id,
                'reviewed_at' => now()->subDays(2),
                'review_notes' => 'Harga item cabling perlu disesuaikan.',
            ]
        );

        $cogs = $this->seedStandardCogs($boq, withProgress: false, prefix: 'RT');
        $item = $cogs->items()->where('code', 'ITEM-RT-E01')->first()
            ?? $cogs->items()->first();

        $revision = BoqRevision::query()->updateOrCreate(
            [
                'boq_id' => $boq->id,
                'revision_code' => 'BOQ-PRJ003-REV01',
            ],
            [
                'revision_number' => 1,
                'reason' => 'Harga perlu disesuaikan.',
                'status' => RevisionStatus::Open,
                'created_by' => $admin->id,
            ]
        );

        if ($item) {
            BoqRevisionItem::query()->updateOrCreate(
                [
                    'boq_revision_id' => $revision->id,
                    'boq_item_id' => $item->id,
                ],
                [
                    'reason' => 'Sesuaikan harga sesuai quotation vendor terbaru.',
                    'status' => RevisionItemStatus::Pending->value,
                ]
            );
        }
    }

    /**
     * @return BoqCogs First COGS (Engineering)
     */
    private function seedStandardCogs(Boq $boq, bool $withProgress, string $prefix = ''): BoqCogs
    {
        $codePrefix = $prefix !== '' ? $prefix.'-' : '';

        $engineering = BoqCogs::query()->updateOrCreate(
            ['boq_id' => $boq->id, 'code' => 'COGS-'.$codePrefix.'001'],
            [
                'name' => 'Engineering',
                'description' => 'Design and engineering works',
                'sort_order' => 1,
            ]
        );

        $procurement = BoqCogs::query()->updateOrCreate(
            ['boq_id' => $boq->id, 'code' => 'COGS-'.$codePrefix.'002'],
            [
                'name' => 'Procurement',
                'description' => 'Material and equipment supply',
                'sort_order' => 2,
            ]
        );

        $installation = BoqCogs::query()->updateOrCreate(
            ['boq_id' => $boq->id, 'code' => 'COGS-'.$codePrefix.'003'],
            [
                'name' => 'Installation',
                'description' => 'On-site installation works',
                'sort_order' => 3,
            ]
        );

        $testing = BoqCogs::query()->updateOrCreate(
            ['boq_id' => $boq->id, 'code' => 'COGS-'.$codePrefix.'004'],
            [
                'name' => 'Testing',
                'description' => 'Testing and commissioning',
                'sort_order' => 4,
            ]
        );

        $itemPrefix = $prefix !== '' ? $prefix : 'STD';

        $this->seedItems($engineering, [
            ['code' => 'ITEM-'.$itemPrefix.'-E01', 'name' => 'Design Drawing', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 12000000, 'progress' => $withProgress ? 100 : 0, 'sort' => 1],
            ['code' => 'ITEM-'.$itemPrefix.'-E02', 'name' => 'Structural Calculation', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 8000000, 'progress' => $withProgress ? 75 : 0, 'sort' => 2],
            ['code' => 'ITEM-'.$itemPrefix.'-E03', 'name' => 'Shop Drawing', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 10000000, 'progress' => $withProgress ? 40 : 0, 'sort' => 3],
        ]);

        $this->seedItems($procurement, [
            ['code' => 'ITEM-'.$itemPrefix.'-P01', 'name' => 'Cable Tray Supply', 'quantity' => 200, 'unit' => 'm', 'unit_price' => 85000, 'progress' => $withProgress ? 80 : 0, 'sort' => 1, 'category' => 'main_material'],
            ['code' => 'ITEM-'.$itemPrefix.'-P02', 'name' => 'Control Panel Supply', 'quantity' => 4, 'unit' => 'set', 'unit_price' => 7500000, 'progress' => $withProgress ? 60 : 0, 'sort' => 2, 'category' => 'main_material'],
        ]);

        $this->seedItems($installation, [
            ['code' => 'ITEM-'.$itemPrefix.'-I01', 'name' => 'Cable Tray Installation', 'quantity' => 200, 'unit' => 'm', 'unit_price' => 45000, 'progress' => $withProgress ? 35 : 0, 'sort' => 1, 'category' => 'consumable'],
            ['code' => 'ITEM-'.$itemPrefix.'-I02', 'name' => 'Panel Installation', 'quantity' => 4, 'unit' => 'set', 'unit_price' => 2500000, 'progress' => $withProgress ? 20 : 0, 'sort' => 2, 'category' => 'consumable'],
        ]);

        $this->seedItems($testing, [
            ['code' => 'ITEM-'.$itemPrefix.'-T01', 'name' => 'Testing & Commissioning', 'quantity' => 1, 'unit' => 'lot', 'unit_price' => 15000000, 'progress' => $withProgress ? 10 : 0, 'sort' => 1],
        ]);

        return $engineering->load('items');
    }

    /**
     * @param  list<string>  $itemCodes
     */
    private function syncPlanItems(PaymentPlan $plan, Boq $boq, array $itemCodes): void
    {
        $boq->loadMissing('cogs.items');

        $items = $boq->cogs
            ->flatMap(fn (BoqCogs $cogs) => $cogs->items)
            ->filter(fn (BoqItem $item) => in_array($item->code, $itemCodes, true))
            ->values();

        if ($items->isEmpty()) {
            return;
        }

        $plan->items()->sync(
            $items->mapWithKeys(fn (BoqItem $item) => [$item->id => ['planned_amount' => $item->total_price]])->all()
        );

        $amount = round($items->sum(fn (BoqItem $item) => (float) $item->total_price), 2);
        $plan->update(['amount' => $amount]);
    }

    /**
     * Seeded payments always settle the full plan, so each plan item is allocated its planned amount.
     */
    private function syncPaymentAllocations(PaymentPlan $plan): void
    {
        $payment = $plan->payments()->first();

        if ($payment === null) {
            return;
        }

        $payment->allocations()->delete();

        foreach ($plan->items()->get() as $item) {
            $payment->allocations()->create([
                'boq_item_id' => $item->id,
                'allocated_amount' => $item->pivot->planned_amount,
            ]);
        }
    }

    /**
     * @param  list<array{code: string, name: string, quantity: float|int, unit: string, unit_price: float|int, progress: float|int, sort: int, category?: string}>  $items
     */
    private function seedItems(BoqCogs $cogs, array $items): void
    {
        foreach ($items as $item) {
            BoqItem::query()->updateOrCreate(
                [
                    'boq_cogs_id' => $cogs->id,
                    'code' => $item['code'],
                ],
                [
                    'name' => $item['name'],
                    'description' => null,
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'category' => $item['category'] ?? BoqItemCategory::MainMaterial->value,
                    'progress_percentage' => $item['progress'],
                    'sort_order' => $item['sort'],
                ]
            );
        }
    }
}
