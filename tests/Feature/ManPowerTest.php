<?php

namespace Tests\Feature;

use App\Enums\ManPowerTransactionStatus;
use App\Enums\ManPowerTransactionType;
use App\Enums\ProjectStatus;
use App\Models\ManPower;
use App\Models\ManPowerTransaction;
use App\Models\Project;
use App\Models\ProjectManPower;
use App\Models\User;
use App\Support\AssetCsv;
use App\Support\ManPowerCsv;
use App\Support\ProjectActualCost;
use App\Support\ProjectDetailContext;
use App\Support\ProjectReport;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ManPowerTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_master_man_power_gets_sequential_codes(): void
    {
        $admin = $this->makeProjectAdmin();

        foreach (['Budi', 'Andi'] as $name) {
            $this->actingAs($admin)
                ->post(route('man-powers.store'), ['name' => $name, 'position' => 'Tukang', 'daily_wage' => 150000])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(['MP-001', 'MP-002'], ManPower::query()->orderBy('id')->pluck('code')->all());

        $this->actingAs($admin)->get(route('man-powers.index'))
            ->assertOk()
            ->assertSee('MP-002')
            ->assertSee('Andi');
    }

    public function test_master_man_power_access_follows_roles(): void
    {
        $manPower = ManPower::factory()->create();

        $this->actingAs($this->makeFinance())->get(route('man-powers.index'))->assertOk();
        $this->actingAs($this->makeFinance())
            ->post(route('man-powers.store'), ['name' => 'X', 'position' => 'Helper', 'daily_wage' => 1000])
            ->assertForbidden();
        $this->actingAs($this->makeApprover())->get(route('man-powers.edit', $manPower))->assertForbidden();
        $this->actingAs($this->makeProjectSite())->get(route('man-powers.index'))->assertForbidden();
    }

    public function test_csv_import_creates_new_and_updates_existing_man_power(): void
    {
        $admin = $this->makeProjectAdmin();
        $existing = ManPower::factory()->create(['code' => 'MP-001', 'name' => 'Andi', 'daily_wage' => 100000]);

        $csv = AssetCsv::rowsToCsv([
            ManPowerCsv::HEADERS,
            ['', 'Budi Santoso', 'Tukang', 'Rp 150.000', '0812', '', ''],
            ['MP-001', 'Andi Pratama', 'Mandor', '200000', '', 'NONAKTIF', 'naik jabatan'],
        ]);

        $this->actingAs($admin)
            ->post(route('man-powers.import'), ['file' => UploadedFile::fake()->createWithContent('mp.csv', $csv)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Import man power selesai. Dibuat: 1, diperbarui: 1.');

        $existing->refresh();
        $this->assertSame('Andi Pratama', $existing->name);
        $this->assertSame(200000.0, (float) $existing->daily_wage);
        $this->assertFalse($existing->is_active);

        $new = ManPower::query()->where('name', 'Budi Santoso')->sole();
        $this->assertSame('MP-002', $new->code);
        $this->assertSame(150000.0, (float) $new->daily_wage);
        $this->assertTrue($new->is_active);
    }

    public function test_csv_import_rolls_back_whole_file_on_invalid_row(): void
    {
        $csv = AssetCsv::rowsToCsv([
            ManPowerCsv::HEADERS,
            ['', 'Budi', 'Tukang', '150000', '', '', ''],
            ['', 'Tanpa Upah', 'Helper', '', '', '', ''],
        ]);

        $this->actingAs($this->makeSuperadmin())
            ->post(route('man-powers.import'), ['file' => UploadedFile::fake()->createWithContent('mp.csv', $csv)])
            ->assertSessionHasErrors(['file' => 'Baris 3: Upah harian wajib diisi.']);

        $this->assertSame(0, ManPower::query()->count());
    }

    public function test_csv_import_rejects_duplicate_codes_and_missing_columns(): void
    {
        $admin = $this->makeSuperadmin();
        $duplicate = AssetCsv::rowsToCsv([
            ManPowerCsv::HEADERS,
            ['MP-009', 'A', 'Tukang', '1000', '', '', ''],
            ['mp-009', 'B', 'Tukang', '1000', '', '', ''],
        ]);

        $this->actingAs($admin)
            ->post(route('man-powers.import'), ['file' => UploadedFile::fake()->createWithContent('mp.csv', $duplicate)])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)
            ->post(route('man-powers.import'), ['file' => UploadedFile::fake()->createWithContent('mp.csv', "name,position\nBudi,Tukang\n")])
            ->assertSessionHasErrors(['file' => 'Kolom wajib hilang: daily_wage. Gunakan template yang disediakan.']);

        $this->assertSame(0, ManPower::query()->count());
    }

    public function test_export_and_template_follow_master_permissions(): void
    {
        ManPower::factory()->create(['code' => 'MP-001', 'name' => 'Andi', 'daily_wage' => 175000]);
        $finance = $this->makeFinance();

        $export = $this->actingAs($finance)->get(route('man-powers.export'));
        $export->assertOk();
        $export->assertHeader('content-type', SimpleXlsx::MIME);
        $rows = SimpleXlsx::readString($export->streamedContent());
        $this->assertSame(ManPowerCsv::HEADERS, $rows[0]);
        $this->assertSame(['MP-001', 'Andi'], array_slice($rows[1], 0, 2));
        $this->assertSame('175000', $rows[1][3]);

        $this->actingAs($finance)->get(route('man-powers.import.template'))->assertForbidden();
        $this->actingAs($finance)->post(route('man-powers.import'), [])->assertForbidden();
        $template = $this->actingAs($this->makeProjectAdmin())->get(route('man-powers.import.template'));
        $template->assertOk();
        $this->assertSame(ManPowerCsv::HEADERS, SimpleXlsx::readString($template->streamedContent())[0]);
    }

    public function test_excel_import_keeps_phone_leading_zero_and_reads_numeric_wage(): void
    {
        $xlsx = SimpleXlsx::write([
            ManPowerCsv::HEADERS,
            ['', 'Siti Aminah', 'Helper', 125000, '081298765432', 'AKTIF', ''],
        ]);

        $this->actingAs($this->makeProjectAdmin())
            ->post(route('man-powers.import'), ['file' => UploadedFile::fake()->createWithContent('mp.xlsx', $xlsx)])
            ->assertSessionHasNoErrors();

        $manPower = ManPower::query()->where('name', 'Siti Aminah')->sole();
        $this->assertSame('081298765432', $manPower->phone);
        $this->assertSame(125000.0, (float) $manPower->daily_wage);
    }

    public function test_assignment_copies_master_values_and_computes_total_salary(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP1']);
        $manPower = ManPower::factory()->create(['position' => 'Mandor', 'daily_wage' => 200000]);

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => $manPower->id, 'work_days' => 12])
            ->assertSessionHasNoErrors();

        $member = ProjectManPower::query()->sole();
        $this->assertSame('Mandor', $member->position);
        $this->assertSame(200000.0, (float) $member->daily_wage);
        $this->assertSame(2400000.0, $member->totalSalary());

        $this->actingAs($admin)->get(route('projects.man-power.index', $project))
            ->assertOk()
            ->assertSee($manPower->name)
            ->assertSee(rupiah(2400000));
    }

    public function test_assignment_can_override_wage_and_rejects_duplicates_and_inactive_workers(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP2']);
        $manPower = ManPower::factory()->create(['daily_wage' => 200000]);
        $inactive = ManPower::factory()->inactive()->create();

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), [
                'man_power_id' => $manPower->id,
                'position' => 'Operator',
                'daily_wage' => 175000,
                'work_days' => 4,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(175000.0, (float) ProjectManPower::query()->sole()->daily_wage);

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => $manPower->id, 'work_days' => 1])
            ->assertSessionHasErrors('business');

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => $inactive->id, 'work_days' => 1])
            ->assertSessionHasErrors('business');

        $this->assertSame(1, ProjectManPower::query()->count());
    }

    public function test_work_days_must_be_a_whole_number(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP3']);
        $manPower = ManPower::factory()->create();

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => $manPower->id, 'work_days' => 2.5])
            ->assertSessionHasErrors('work_days');

        $this->assertSame(0, ProjectManPower::query()->count());
    }

    public function test_kasbon_and_payout_count_as_project_actual_cost_but_not_per_boq_item(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP4']);
        $boq = $this->makeApprovedBoq($admin, $project, 'BOQ-MP4');
        $member = $this->makeMember($admin, $project, workDays: 10, dailyWage: 100000);

        $this->recordTransaction($admin, $member, ManPowerTransactionType::Kasbon, 200000)->assertSessionHasNoErrors();
        $this->recordTransaction($admin, $member, ManPowerTransactionType::Pelunasan, 300000)->assertSessionHasNoErrors();

        $actualCost = ProjectActualCost::forProject($project);
        $this->assertSame(500000.0, $actualCost->manPowerActual());
        $this->assertSame(500000.0, $actualCost->projectActual());
        $this->assertSame(0.0, $actualCost->forBoq($boq));

        $context = ProjectDetailContext::for($project->fresh());
        $this->assertSame(500000.0, $context->manPowerActual);
        $this->assertSame(500000.0, $context->totalActual);

        $this->assertSame(500000.0, $member->fresh()->paidAmount());
        $this->assertSame(500000.0, $member->fresh()->remainingSalary());

        $report = ProjectReport::for($project->fresh(), $admin);
        $this->assertSame(500000.0, $report->cost()['summary']['man_power']);
        $this->assertSame(500000.0, $report->cost()['summary']['actual']);
    }

    public function test_kasbon_and_payout_cannot_exceed_total_salary(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP5']);
        $member = $this->makeMember($admin, $project, workDays: 1, dailyWage: 10000);

        $this->recordTransaction($admin, $member, ManPowerTransactionType::Kasbon, 2000)->assertSessionHasNoErrors();
        $this->recordTransaction($admin, $member, ManPowerTransactionType::Pelunasan, 8001)->assertSessionHasErrors('business');
        $this->recordTransaction($admin, $member, ManPowerTransactionType::Pelunasan, 8000)->assertSessionHasNoErrors();
        $this->recordTransaction($admin, $member, ManPowerTransactionType::Kasbon, 1)->assertSessionHasErrors('business');

        $this->assertSame(10000.0, $member->fresh()->paidAmount());
    }

    public function test_cancelled_transaction_needs_a_reason_and_leaves_actual_cost(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP6']);
        $member = $this->makeMember($admin, $project, workDays: 5, dailyWage: 100000);

        $this->recordTransaction($admin, $member, ManPowerTransactionType::Kasbon, 150000);
        $transaction = ManPowerTransaction::query()->sole();

        $this->actingAs($admin)
            ->post(route('man-power-transactions.cancel', $transaction), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($admin)
            ->post(route('man-power-transactions.cancel', $transaction), ['cancel_reason' => 'Salah input nominal'])
            ->assertSessionHasNoErrors();

        $transaction->refresh();
        $this->assertSame(ManPowerTransactionStatus::Cancelled, $transaction->status);
        $this->assertSame('Salah input nominal', $transaction->cancel_reason);
        $this->assertSame(0.0, ProjectActualCost::forProject($project)->manPowerActual());

        $this->actingAs($admin)
            ->post(route('man-power-transactions.cancel', $transaction), ['cancel_reason' => 'Lagi'])
            ->assertForbidden();
    }

    public function test_salary_cannot_be_reduced_below_amount_already_paid(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP7']);
        $member = $this->makeMember($admin, $project, workDays: 10, dailyWage: 100000);

        $this->recordTransaction($admin, $member, ManPowerTransactionType::Kasbon, 450000);

        $this->actingAs($admin)
            ->put(route('project-man-powers.update', $member), ['position' => 'Tukang', 'daily_wage' => 100000, 'work_days' => 4])
            ->assertSessionHasErrors('business');

        $this->assertSame(10, $member->fresh()->work_days);

        $this->actingAs($admin)
            ->put(route('project-man-powers.update', $member), ['position' => 'Tukang', 'daily_wage' => 100000, 'work_days' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame(5, $member->fresh()->work_days);
    }

    public function test_finance_can_pay_but_cannot_manage_project_man_power(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP8']);
        $member = $this->makeMember($admin, $project, workDays: 10, dailyWage: 100000);
        $finance = $this->makeFinance();

        $this->recordTransaction($finance, $member, ManPowerTransactionType::Kasbon, 100000)->assertSessionHasNoErrors();

        $this->actingAs($finance)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => ManPower::factory()->create()->id, 'work_days' => 1])
            ->assertForbidden();

        $this->actingAs($finance)
            ->put(route('project-man-powers.update', $member), ['position' => 'Tukang', 'daily_wage' => 1, 'work_days' => 100])
            ->assertForbidden();
    }

    public function test_view_only_roles_and_project_site_access(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP9']);
        $member = $this->makeMember($admin, $project, workDays: 10, dailyWage: 100000);

        $costControl = $this->makeBoqCostControl();
        $this->actingAs($costControl)->get(route('projects.man-power.index', $project))
            ->assertOk()
            ->assertDontSee('Kasbon / Pelunasan</button>', false);
        $this->recordTransaction($costControl, $member, ManPowerTransactionType::Kasbon, 1000)->assertForbidden();

        $site = $this->makeProjectSite();
        $this->actingAs($site)->get(route('projects.man-power.index', $project))->assertForbidden();
        $this->actingAs($site)->get(route('projects.show', $project))
            ->assertOk()
            ->assertDontSee(route('projects.man-power.index', $project));
    }

    public function test_payment_allowed_on_completed_project_but_assignment_is_not(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-MP10']);
        $member = $this->makeMember($admin, $project, workDays: 10, dailyWage: 100000);
        $project->forceFill(['status' => ProjectStatus::Completed])->save();

        $this->recordTransaction($admin, $member, ManPowerTransactionType::Pelunasan, 1000000)->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->post(route('projects.man-power.store', $project), ['man_power_id' => ManPower::factory()->create()->id, 'work_days' => 1])
            ->assertForbidden();
    }

    private function makeMember(User $actor, Project $project, int $workDays, float $dailyWage): ProjectManPower
    {
        return ProjectManPower::factory()->create([
            'project_id' => $project->id,
            'man_power_id' => ManPower::factory()->create(['created_by' => $actor->id])->id,
            'work_days' => $workDays,
            'daily_wage' => $dailyWage,
            'created_by' => $actor->id,
        ]);
    }

    private function recordTransaction(User $actor, ProjectManPower $member, ManPowerTransactionType $type, float $amount): TestResponse
    {
        return $this->actingAs($actor)->post(route('man-power-transactions.store', $member), [
            'type' => $type->value,
            'transaction_date' => now()->toDateString(),
            'amount' => $amount,
        ]);
    }
}
