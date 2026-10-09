<?php

namespace Tests\Feature;

use App\Actions\Project\ActivateProject;
use App\Actions\Project\CompleteProject;
use App\Actions\Project\CreateProject;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProjectWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_create_project_starts_as_draft(): void
    {
        $actor = $this->makeSuperadmin();

        $project = app(CreateProject::class)->handle($actor, [
            'code' => 'PRJ-001',
            'name' => 'Office Renovation',
            'client_name' => 'PT Contoh',
        ]);

        $this->assertSame(ProjectStatus::Draft, $project->status);
        $this->assertSame($actor->id, $project->created_by);
        $this->assertDatabaseHas('projects', [
            'code' => 'PRJ-001',
            'status' => ProjectStatus::Draft->value,
        ]);
    }

    public function test_store_project_saves_contract_number(): void
    {
        $actor = $this->makeSuperadmin();

        $this->actingAs($actor)
            ->post(route('projects.store'), [
                'code' => 'PRJ-020',
                'name' => 'Data Center Fit Out',
                'client_name' => 'PT Kontrak',
                'contract_number' => '010/KTR/DC/2026',
                'contract_value' => '1500000000.50',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', [
            'code' => 'PRJ-020',
            'contract_number' => '010/KTR/DC/2026',
            'contract_value' => '1500000000.50',
        ]);
    }

    public function test_contract_value_must_be_a_non_negative_number(): void
    {
        $actor = $this->makeSuperadmin();

        $this->actingAs($actor)
            ->post(route('projects.store'), [
                'code' => 'PRJ-023',
                'name' => 'Nilai Negatif',
                'client_name' => 'PT Minus',
                'contract_value' => '-1',
            ])
            ->assertSessionHasErrors('contract_value');

        $this->actingAs($actor)
            ->post(route('projects.store'), [
                'code' => 'PRJ-024',
                'name' => 'Nilai Teks',
                'client_name' => 'PT Teks',
                'contract_value' => 'satu juta',
            ])
            ->assertSessionHasErrors('contract_value');

        $this->assertDatabaseMissing('projects', ['code' => 'PRJ-023']);
        $this->assertDatabaseMissing('projects', ['code' => 'PRJ-024']);
    }

    public function test_contract_number_is_optional_and_limited_to_100_characters(): void
    {
        $actor = $this->makeSuperadmin();

        $project = app(CreateProject::class)->handle($actor, [
            'code' => 'PRJ-021',
            'name' => 'Tanpa Kontrak',
            'client_name' => 'PT Opsional',
        ]);

        $this->assertNull($project->contract_number);

        $this->actingAs($actor)
            ->post(route('projects.store'), [
                'code' => 'PRJ-022',
                'name' => 'Kontrak Panjang',
                'client_name' => 'PT Panjang',
                'contract_number' => str_repeat('A', 101),
            ])
            ->assertSessionHasErrors('contract_number');
    }

    public function test_update_project_changes_contract_number(): void
    {
        $actor = $this->makeSuperadmin();
        $project = Project::factory()->create([
            'created_by' => $actor->id,
            'contract_number' => 'OLD/KTR/2026',
        ]);

        $this->actingAs($actor)
            ->put(route('projects.update', $project), [
                'code' => $project->code,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'contract_number' => 'NEW/KTR/2026',
                'contract_value' => '750000000',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame('NEW/KTR/2026', $project->contract_number);
        $this->assertSame('750000000.00', $project->contract_value);
    }

    public function test_project_code_must_be_unique(): void
    {
        $actor = $this->makeSuperadmin();

        app(CreateProject::class)->handle($actor, [
            'code' => 'PRJ-001',
            'name' => 'First',
            'client_name' => 'Client A',
        ]);

        $this->expectException(ValidationException::class);

        app(CreateProject::class)->handle($actor, [
            'code' => 'PRJ-001',
            'name' => 'Second',
            'client_name' => 'Client B',
        ]);
    }

    public function test_activate_project_from_draft(): void
    {
        $actor = $this->makeSuperadmin();
        $project = app(CreateProject::class)->handle($actor, [
            'code' => 'PRJ-002',
            'name' => 'Warehouse',
            'client_name' => 'PT Gudang',
        ]);

        $activated = app(ActivateProject::class)->handle($actor, $project);

        $this->assertSame(ProjectStatus::Active, $activated->status);
    }

    public function test_cannot_activate_project_with_incomplete_basic_data(): void
    {
        $actor = $this->makeSuperadmin();

        $project = Project::factory()->create([
            'created_by' => $actor->id,
            'code' => 'PRJ-003',
            'name' => 'Incomplete',
            'client_name' => '',
            'status' => ProjectStatus::Draft,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('data dasar project belum lengkap');

        app(ActivateProject::class)->handle($actor, $project);
    }

    public function test_complete_project_from_active(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-010']);

        $completed = app(CompleteProject::class)->handle($actor, $project);

        $this->assertSame(ProjectStatus::Completed, $completed->status);
    }
}
