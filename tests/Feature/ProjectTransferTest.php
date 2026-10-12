<?php

namespace Tests\Feature;

use App\Actions\Asset\AssignAssetToProject;
use App\Actions\Asset\ReleaseAssetFromProject;
use App\Actions\ManPower\RecordManPowerTransaction;
use App\Enums\AssetStatus;
use App\Enums\BoqStatus;
use App\Enums\ClientBillingStatus;
use App\Enums\ManPowerTransactionType;
use App\Enums\PaymentStatus;
use App\Enums\ProcurementStatus;
use App\Enums\ProjectStatus;
use App\Models\Asset;
use App\Models\ManPower;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use App\Models\ProjectManPower;
use App\Models\User;
use App\Support\ProjectActualCost;
use App\Support\ProjectArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;
use ZipArchive;

class ProjectTransferTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake(ProjectDocumentVersion::DISK);
    }

    public function test_export_contains_all_project_data_and_attachments(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->buildProject($admin);

        $zipPath = $this->exportZip($admin, $project);
        $manifest = $this->readManifest($zipPath);

        $this->assertSame(ProjectArchive::FORMAT, $manifest['format']);
        $this->assertSame('PRJ-EXP', $manifest['tables']['projects'][0]['code']);

        foreach (['boqs', 'boq_items', 'payment_plans', 'payments', 'procurement_requests', 'client_billings', 'client_payments', 'project_documents', 'project_document_versions', 'project_assets', 'assets', 'project_man_powers', 'man_power_transactions', 'man_powers'] as $table) {
            $this->assertNotEmpty($manifest['tables'][$table], "{$table} should be exported");
        }

        $documentPath = $manifest['tables']['project_document_versions'][0]['file_path'];
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $this->assertNotFalse($zip->getFromName(ProjectArchive::zipPath(ProjectDocumentVersion::DISK, $documentPath)));
        $zip->close();
        unlink($zipPath);
    }

    public function test_import_restores_project_with_statuses_files_and_matched_master_data(): void
    {
        $admin = $this->makeSuperadmin(['email' => 'admin@example.test']);
        $project = $this->buildProject($admin);
        $originalActual = ProjectActualCost::forProject($project)->projectActual();
        $originalManPowerId = ProjectManPower::query()->sole()->man_power_id;
        $asset = Asset::query()->sole();

        $zipPath = $this->exportZip($admin, $project);

        $payer = User::query()->findOrFail(DB::table('procurement_requests')->value('paid_by'));
        $payer->forceFill(['email' => 'no-longer-here@example.test'])->save();
        app(ReleaseAssetFromProject::class)->handle($admin, $project, $project->projectAssets()->sole());
        $this->renameExistingUniqueValues();

        $importer = $this->makeSuperadmin();

        $this->actingAs($importer)
            ->post(route('projects.import'), ['file' => $this->uploaded($zipPath)])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $imported = Project::query()->where('code', 'PRJ-EXP')->sole();

        $this->assertNotSame($project->id, $imported->id);
        $this->assertSame(ProjectStatus::Active, $imported->status);
        $this->assertSame($admin->id, (int) $imported->created_by);
        $this->assertSame(BoqStatus::Approved, $imported->boqs()->sole()->status);
        $this->assertSame(PaymentStatus::Paid, $imported->boqs()->sole()->paymentPlans()->sole()->payments()->sole()->status);
        $this->assertSame($originalActual, ProjectActualCost::forProject($imported)->projectActual());

        $procurement = $imported->procurementRequests()->sole();
        $this->assertSame(ProcurementStatus::Paid, $procurement->status);
        $this->assertSame($importer->id, (int) $procurement->paid_by);

        $billing = $imported->clientBillings()->sole();
        $this->assertSame(ClientBillingStatus::Sent, $billing->status);
        $this->assertSame(1, $billing->payments()->count());

        $document = $imported->documents()->sole();
        $this->assertSame($billing->id, (int) $document->reference_id);
        $version = $document->versions()->sole();
        $this->assertStringStartsWith('imported/', $version->file_path);
        Storage::disk(ProjectDocumentVersion::DISK)->assertExists($version->file_path);

        $member = $imported->manPowers()->sole();
        $this->assertSame($originalManPowerId, (int) $member->man_power_id);
        $this->assertSame(1, ManPower::query()->count());
        $this->assertSame(250000.0, $member->paidAmount());

        $assignment = $imported->projectAssets()->sole();
        $this->assertSame($asset->id, (int) $assignment->asset_id);
        $this->assertSame('Budi', $assignment->responsible_name);
        $this->assertSame(AssetStatus::InUse, $asset->fresh()->status);

        unlink($zipPath);
    }

    public function test_import_is_rejected_when_project_code_or_numbers_already_exist(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->buildProject($admin);
        $zipPath = $this->exportZip($admin, $project);
        $projectCount = Project::withTrashed()->count();

        $this->actingAs($admin)
            ->post(route('projects.import'), ['file' => $this->uploaded($zipPath)])
            ->assertSessionHasErrors('business');

        $message = session('errors')->first('business');
        $this->assertStringContainsString('Kode project PRJ-EXP sudah ada.', $message);
        $this->assertStringContainsString('Kode payment PAY-PP-EXP sudah ada.', $message);
        $this->assertStringContainsString('sedang digunakan project lain.', $message);
        $this->assertSame($projectCount, Project::withTrashed()->count());
        $this->assertSame([], Storage::disk(ProjectDocumentVersion::DISK)->allFiles('imported'));

        unlink($zipPath);
    }

    public function test_soft_deleted_project_code_still_blocks_import(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-DEL']);
        $zipPath = $this->exportZip($admin, $project);
        $project->delete();

        $this->actingAs($admin)
            ->post(route('projects.import'), ['file' => $this->uploaded($zipPath)])
            ->assertSessionHasErrors('business');

        $this->assertSame(1, Project::withTrashed()->where('code', 'PRJ-DEL')->count());

        unlink($zipPath);
    }

    public function test_zip_without_manifest_is_rejected(): void
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'bad-zip-');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'bukan export');
        $zip->close();

        $this->actingAs($this->makeSuperadmin())
            ->post(route('projects.import'), ['file' => $this->uploaded($zipPath)])
            ->assertSessionHasErrors('business');

        unlink($zipPath);
    }

    public function test_only_superadmin_can_export_and_import(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-ACL']);
        $projectAdmin = $this->makeProjectAdmin();

        $this->actingAs($projectAdmin)->get(route('projects.export', $project))->assertForbidden();
        $this->actingAs($projectAdmin)->post(route('projects.import'), [])->assertForbidden();
        $this->actingAs($projectAdmin)->get(route('projects.show', $project))->assertDontSee(route('projects.export', $project));
        $this->actingAs($projectAdmin)->get(route('projects.index'))->assertDontSee(route('projects.import'));

        $this->actingAs($admin)->get(route('projects.show', $project))->assertSee(route('projects.export', $project));
        $this->actingAs($admin)->get(route('projects.index'))->assertSee(route('projects.import'));
    }

    private function buildProject(User $admin): Project
    {
        $project = $this->makeActiveProject($admin, ['code' => 'PRJ-EXP', 'contract_value' => 100000000]);
        $boq = $this->makeApprovedBoq($admin, $project, 'BOQ-EXP');
        $this->makePaidPlanForBoq($admin, $boq, 'PP-EXP');
        $this->makePaidProcurementRequest($admin, $project);

        $term = $this->makeTerm($admin, $project);
        $billing = $this->makeSentBilling($admin, $term, 1000000);
        $this->recordClientPayment($admin, $billing, 400000);
        $this->makeBillingDocument($admin, $billing);

        $asset = Asset::factory()->existing()->available()->create(['created_by' => $admin->id]);
        app(AssignAssetToProject::class)->handle($admin, $project, ['asset_id' => $asset->id, 'responsible_name' => 'Budi']);

        $member = ProjectManPower::factory()->create([
            'project_id' => $project->id,
            'man_power_id' => ManPower::factory()->create(['created_by' => $admin->id])->id,
            'work_days' => 10,
            'daily_wage' => 100000,
            'created_by' => $admin->id,
        ]);
        app(RecordManPowerTransaction::class)->handle($admin, $member, [
            'type' => ManPowerTransactionType::Kasbon->value,
            'transaction_date' => now()->toDateString(),
            'amount' => 250000,
        ]);

        return $project->fresh();
    }

    private function exportZip(User $actor, Project $project): string
    {
        $response = $this->actingAs($actor)->get(route('projects.export', $project));
        $response->assertOk();

        $copy = tempnam(sys_get_temp_dir(), 'export-test-');
        copy($response->baseResponse->getFile()->getPathname(), $copy);
        @unlink($response->baseResponse->getFile()->getPathname());

        return $copy;
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifest(string $zipPath): array
    {
        $zip = new ZipArchive;
        $zip->open($zipPath);
        $manifest = json_decode((string) $zip->getFromName(ProjectArchive::MANIFEST), true);
        $zip->close();

        return $manifest;
    }

    private function uploaded(string $zipPath): UploadedFile
    {
        return new UploadedFile($zipPath, 'project.zip', 'application/zip', null, true);
    }

    private function renameExistingUniqueValues(): void
    {
        foreach (ProjectArchive::tables() as $table => $definition) {
            foreach (array_keys($definition['unique']) as $column) {
                DB::table($table)->whereNotNull($column)->update([$column => DB::raw("'OLD-' || {$column}")]);
            }
        }
    }
}
