<?php

namespace Tests\Feature;

use App\Actions\Asset\AssignAssetToProject;
use App\Actions\Asset\CanAssignAsset;
use App\Actions\Asset\CreateAsset;
use App\Actions\Asset\MarkAssetAvailable;
use App\Actions\Asset\ReleaseAssetFromProject;
use App\Enums\AssetRequestStatus;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\ProjectAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\Payment;
use App\Models\PaymentPlan;
use App\Models\ProjectAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class AssetWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_create_existing_asset(): void
    {
        $actor = $this->makeSuperadmin();

        $asset = app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-0002',
            'name' => 'Printer Laser',
            'category' => 'Office Equipment',
            'source_type' => AssetSourceType::Existing->value,
        ]);

        $this->assertSame(AssetSourceType::Existing, $asset->source_type);
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertNull($asset->request_number);
    }

    public function test_create_purchase_asset(): void
    {
        $actor = $this->makeSuperadmin();

        $asset = app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-0001',
            'name' => 'Laptop Dell Latitude 5450',
            'category' => 'IT Equipment',
            'source_type' => AssetSourceType::Purchase->value,
            'request_number' => 'SPP-2026-001',
            'request_date' => '2026-01-10',
            'request_description' => 'Permohonan pembelian laptop.',
        ]);

        $this->assertSame(AssetSourceType::Purchase, $asset->source_type);
        $this->assertSame(AssetStatus::Requested, $asset->status);
        $this->assertSame(AssetRequestStatus::Requested, $asset->request_status);
        $this->assertSame('SPP-2026-001', $asset->request_number);
    }

    public function test_purchase_asset_requires_request_data(): void
    {
        $actor = $this->makeSuperadmin();

        $this->expectException(ValidationException::class);

        app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-0003',
            'name' => 'Portable Generator',
            'category' => 'Machinery',
            'source_type' => AssetSourceType::Purchase->value,
        ]);
    }

    public function test_existing_asset_does_not_require_request_data(): void
    {
        $actor = $this->makeSuperadmin();

        $asset = app(CreateAsset::class)->handle($actor, [
            'code' => 'AST-0004',
            'name' => 'Network Switch',
            'category' => 'IT Equipment',
            'source_type' => AssetSourceType::Existing->value,
        ]);

        $this->assertNull($asset->request_status);
        $this->assertSame(AssetStatus::Available, $asset->status);
    }

    public function test_requested_asset_cannot_be_assigned(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-01']);
        $asset = Asset::factory()->purchase()->requested()->create(['created_by' => $actor->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('REQUESTED');

        app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);
    }

    public function test_available_asset_can_be_assigned(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-02']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);

        $this->assertSame(ProjectAssetStatus::InUse, $assignment->status);
        $this->assertSame(AssetStatus::InUse, $asset->fresh()->status);
    }

    public function test_in_use_asset_cannot_be_assigned_to_another_project(): void
    {
        $actor = $this->makeSuperadmin();
        $projectA = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-03A']);
        $projectB = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-03B']);
        $asset = Asset::factory()->existing()->available()->create([
            'code' => 'AST-LOCK-01',
            'created_by' => $actor->id,
        ]);

        app(AssignAssetToProject::class)->handle($actor, $projectA, [
            'asset_id' => $asset->id,
        ]);

        try {
            app(AssignAssetToProject::class)->handle($actor, $projectB, [
                'asset_id' => $asset->id,
            ]);
            $this->fail('Expected BusinessRuleException was not thrown.');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('PRJ-ASSET-03A', $e->getMessage());
            $this->assertStringContainsString('belum dapat digunakan pada project lain', $e->getMessage());
        }
    }

    public function test_asset_can_be_assigned_after_release(): void
    {
        $actor = $this->makeSuperadmin();
        $projectA = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-04A']);
        $projectB = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-04B']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $projectA, [
            'asset_id' => $asset->id,
        ]);

        app(ReleaseAssetFromProject::class)->handle($actor, $projectA, $assignment);

        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);

        $next = app(AssignAssetToProject::class)->handle($actor, $projectB, [
            'asset_id' => $asset->id,
        ]);

        $this->assertSame(ProjectAssetStatus::InUse, $next->status);
        $this->assertSame($projectB->id, $next->project_id);
    }

    public function test_asset_cannot_have_two_active_assignments(): void
    {
        $actor = $this->makeSuperadmin();
        $projectA = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-05A']);
        $projectB = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-05B']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        app(AssignAssetToProject::class)->handle($actor, $projectA, [
            'asset_id' => $asset->id,
        ]);

        $this->expectException(BusinessRuleException::class);

        app(AssignAssetToProject::class)->handle($actor, $projectB, [
            'asset_id' => $asset->id,
        ]);
    }

    public function test_inactive_asset_cannot_be_assigned(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-06']);
        $asset = Asset::factory()->existing()->inactive()->create(['created_by' => $actor->id]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('INACTIVE');

        app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);
    }

    public function test_release_only_allowed_by_project_using_asset(): void
    {
        $actor = $this->makeSuperadmin();
        $projectA = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-07A']);
        $projectB = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-07B']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $projectA, [
            'asset_id' => $asset->id,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Release hanya dapat dilakukan oleh project yang sedang menggunakan asset.');

        app(ReleaseAssetFromProject::class)->handle($actor, $projectB, $assignment);
    }

    public function test_responsible_person_is_stored_on_assignment_and_shown(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-PIC1']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $this->actingAs($actor)
            ->post(route('projects.assets.store', $project), [
                'asset_id' => $asset->id,
                'responsible_name' => 'Budi Santoso',
                'responsible_position' => 'Site Manager',
            ])
            ->assertSessionHasNoErrors();

        $assignment = ProjectAsset::query()->where('asset_id', $asset->id)->sole();
        $this->assertSame('Budi Santoso', $assignment->responsible_name);
        $this->assertSame('Site Manager', $assignment->responsible_position);

        $this->actingAs($actor)->get(route('projects.assets.index', $project))
            ->assertOk()
            ->assertSee('Budi Santoso')
            ->assertSee('Site Manager');

        $this->actingAs($actor)->get(route('assets.show', $asset))
            ->assertOk()
            ->assertSee('Budi Santoso');
    }

    public function test_responsible_person_can_be_changed_only_while_asset_is_in_use(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-PIC2']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
            'responsible_name' => 'Budi',
        ]);

        $this->actingAs($actor)
            ->patch(route('projects.assets.responsible', [$project, $assignment]), [
                'responsible_name' => 'Andi',
                'responsible_position' => 'Mandor',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Andi', $assignment->fresh()->responsible_name);
        $this->assertSame('Mandor', $assignment->fresh()->responsible_position);

        app(ReleaseAssetFromProject::class)->handle($actor, $project, $assignment);

        $this->actingAs($actor)
            ->patch(route('projects.assets.responsible', [$project, $assignment]), ['responsible_name' => 'Citra'])
            ->assertForbidden();

        $this->assertSame('Andi', $assignment->fresh()->responsible_name);
    }

    public function test_user_without_assign_permission_cannot_change_responsible_person(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-PIC3']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, ['asset_id' => $asset->id]);

        $this->actingAs($this->makeFinance())
            ->patch(route('projects.assets.responsible', [$project, $assignment]), ['responsible_name' => 'Citra'])
            ->assertForbidden();

        $this->assertNull($assignment->fresh()->responsible_name);
    }

    public function test_asset_does_not_affect_total_boq(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-08']);
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-ASSET-08');
        $before = $project->fresh()->totalCost();

        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);
        app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals($before, $project->fresh()->totalCost());
        $this->assertEquals($before, $boq->fresh()->totalAmount());
    }

    public function test_asset_does_not_affect_project_progress(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-09']);
        $this->makeApprovedBoq($actor, $project, 'BOQ-ASSET-09');
        $before = $project->fresh()->progressPercentage();

        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);
        app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);

        $this->assertEquals($before, $project->fresh()->progressPercentage());
    }

    public function test_asset_does_not_affect_payment_plan(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-10']);
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-ASSET-10');

        $plansBefore = PaymentPlan::query()->count();
        $paymentsBefore = Payment::query()->count();

        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);
        app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);

        $this->assertSame($plansBefore, PaymentPlan::query()->count());
        $this->assertSame($paymentsBefore, Payment::query()->count());
        $this->assertSame(0, PaymentPlan::query()->where('boq_id', $boq->id)->count());
    }

    public function test_usage_history_persists_after_release(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-11']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
            'notes' => 'First use',
        ]);

        app(ReleaseAssetFromProject::class)->handle($actor, $project, $assignment, [
            'notes' => 'Returned to pool',
        ]);

        $this->assertDatabaseHas('project_assets', [
            'id' => $assignment->id,
            'status' => ProjectAssetStatus::Released->value,
        ]);
        $this->assertSame(1, $asset->projectAssets()->count());
        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
    }

    public function test_assignment_uses_transaction_and_locking_against_race(): void
    {
        $actor = $this->makeSuperadmin();
        $projectA = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-12A']);
        $projectB = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-12B']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assign = app(AssignAssetToProject::class);

        $first = $assign->handle($actor, $projectA, ['asset_id' => $asset->id]);
        $this->assertSame(ProjectAssetStatus::InUse, $first->status);

        $caught = null;

        DB::transaction(function () use ($actor, $projectB, $asset, $assign, &$caught) {
            Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            try {
                $assign->handle($actor, $projectB, ['asset_id' => $asset->id]);
            } catch (BusinessRuleException $e) {
                $caught = $e;
            }
        });

        $this->assertInstanceOf(BusinessRuleException::class, $caught);
        $this->assertSame(1, ProjectAsset::query()
            ->where('asset_id', $asset->id)
            ->where('status', ProjectAssetStatus::InUse)
            ->count());
    }

    public function test_mark_requested_purchase_asset_available_then_assign(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-13']);
        $asset = Asset::factory()->purchase()->requested()->create([
            'created_by' => $actor->id,
            'requested_by' => $actor->id,
        ]);

        app(MarkAssetAvailable::class)->handle($actor, $asset);

        $this->assertSame(AssetStatus::Available, $asset->fresh()->status);
        $this->assertSame(AssetRequestStatus::Purchased, $asset->fresh()->request_status);

        $this->assertTrue(app(CanAssignAsset::class)->handle($asset->fresh(), $project));

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);

        $this->assertSame(ProjectAssetStatus::InUse, $assignment->status);
    }

    public function test_used_asset_is_set_inactive_instead_of_hard_delete(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-ASSET-14']);
        $asset = Asset::factory()->existing()->available()->create(['created_by' => $actor->id]);

        $assignment = app(AssignAssetToProject::class)->handle($actor, $project, [
            'asset_id' => $asset->id,
        ]);
        app(ReleaseAssetFromProject::class)->handle($actor, $project, $assignment);

        $response = $this->actingAs($actor)->delete(route('assets.destroy', $asset));

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'status' => AssetStatus::Inactive->value,
            'deleted_at' => null,
        ]);
    }
}
