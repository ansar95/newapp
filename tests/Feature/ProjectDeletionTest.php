<?php

namespace Tests\Feature;

use App\Actions\Asset\AssignAssetToProject;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\Project\DeleteProject;
use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectDocumentVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectDeletionTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(ProjectDocumentVersion::DISK);

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin, ['contract_value' => 1_000_000_000]);
    }

    public function test_superadmin_deletes_project_after_typing_its_code(): void
    {
        $this->actingAs($this->admin)
            ->delete(route('projects.destroy', $this->project), ['confirmation_code' => 'PRJ-001'])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHas('success', 'Project PRJ-001 berhasil dihapus.');

        $this->assertSoftDeleted($this->project);

        $this->actingAs($this->admin)->get(route('projects.show', $this->project))->assertNotFound();
        $this->actingAs($this->admin)->get(route('projects.index'))->assertOk()->assertDontSee('Office Renovation');
    }

    public function test_wrong_or_missing_confirmation_code_keeps_the_project(): void
    {
        $this->actingAs($this->admin)
            ->from(route('projects.show', $this->project))
            ->delete(route('projects.destroy', $this->project), ['confirmation_code' => 'prj-001'])
            ->assertRedirect(route('projects.show', $this->project))
            ->assertSessionHasErrorsIn(DeleteProject::ERROR_BAG, ['confirmation_code' => 'Kode konfirmasi tidak sesuai dengan kode project.']);

        $this->actingAs($this->admin)
            ->delete(route('projects.destroy', $this->project))
            ->assertSessionHasErrorsIn(DeleteProject::ERROR_BAG, ['confirmation_code' => 'Ketik kode project untuk konfirmasi.']);

        $this->assertNotSoftDeleted($this->project);
    }

    public function test_only_superadmin_can_delete_or_see_the_delete_button(): void
    {
        $this->actingAs($this->admin)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('confirm-project-deletion');

        foreach ([UserRole::ProjectAdmin, UserRole::BoqCostControl, UserRole::Approver, UserRole::Finance, UserRole::ProjectSite] as $role) {
            $user = $this->makeUserWithRole($role);

            $this->actingAs($user)
                ->get(route('projects.show', $this->project))
                ->assertOk()
                ->assertDontSee('confirm-project-deletion');

            $this->actingAs($user)
                ->delete(route('projects.destroy', $this->project), ['confirmation_code' => 'PRJ-001'])
                ->assertForbidden();
        }

        $this->assertNotSoftDeleted($this->project);
    }

    public function test_active_assets_are_released_and_history_still_names_the_deleted_project(): void
    {
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $this->admin->id]);
        $assignment = app(AssignAssetToProject::class)->handle($this->admin, $this->project, ['asset_id' => $asset->id]);

        app(DeleteProject::class)->handle($this->admin, $this->project, ['confirmation_code' => 'PRJ-001']);

        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertSame(ProjectAssetStatus::Released, $assignment->fresh()->status);
        $this->assertSame($this->admin->id, $assignment->fresh()->released_by);

        $this->actingAs($this->admin)
            ->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('PRJ-001')
            ->assertSee('(project dihapus)');
    }

    public function test_child_records_of_a_deleted_project_are_hidden_everywhere(): void
    {
        $draftBoq = $this->makeDraftBoq($this->admin, $this->project, code: 'BOQ-WAIT');
        app(SubmitBOQ::class)->handle($this->admin, $draftBoq);
        $approvedBoq = $this->makeApprovedBoq($this->admin, $this->project, 'BOQ-OK');
        $plan = $this->makePaidPlanForBoq($this->admin, $approvedBoq, 'PP-DEL');
        $purchase = $this->makeDraftBmhpPurchase($this->admin, $this->project, [[$this->makeMasterBmhp($this->admin), 2, 50_000]]);
        $term = $this->makeTerm($this->admin, $this->project, ['percentage' => 30]);
        $billing = $this->makeSentBilling($this->admin, $term, 300_000_000, 'INV/DEL/001');
        $document = $this->makeBillingDocument($this->admin, $billing);
        $version = $document->versions()->first();

        $childUrls = [
            route('boqs.show', $approvedBoq),
            route('boqs.review', $draftBoq),
            route('bmhp-purchases.show', $purchase),
            route('client-billings.show', $billing),
            route('project-documents.show', $document),
            route('project-document-versions.download', $version),
        ];

        foreach ($childUrls as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }

        $this->actingAs($this->admin)->get(route('boqs.approvals'))->assertSee('BOQ-WAIT');
        $this->actingAs($this->admin)->get(route('reports.finance'))->assertSee('PP-DEL');

        app(DeleteProject::class)->handle($this->admin, $this->project, ['confirmation_code' => 'PRJ-001']);

        foreach ($childUrls as $url) {
            $this->actingAs($this->admin)->get($url)->assertNotFound();
        }

        $this->actingAs($this->admin)
            ->post(route('payments.void', $plan->payments()->first()))
            ->assertNotFound();

        $this->actingAs($this->admin)->get(route('boqs.approvals'))->assertOk()->assertDontSee('BOQ-WAIT');
        $this->actingAs($this->admin)->get(route('reports.finance'))->assertOk()->assertDontSee('PP-DEL');
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertDontSee('Office Renovation');

        $this->assertDatabaseHas('boqs', ['id' => $approvedBoq->id]);
        $this->assertDatabaseHas('client_billings', ['id' => $billing->id]);
    }
}
