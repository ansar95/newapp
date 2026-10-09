<?php

namespace Tests\Feature;

use App\Actions\BOQ\ConfirmBOQ;
use App\Actions\BOQ\CreateBOQ;
use App\Actions\BOQ\PrepareBoqForRevision;
use App\Actions\BOQ\RequestRevision;
use App\Actions\BOQ\ReviewBOQ;
use App\Actions\BOQ\SubmitBOQ;
use App\Actions\BOQ\SubmitRevision;
use App\Actions\BOQ\UpdateBoqItem;
use App\Enums\BoqStatus;
use App\Enums\ReviewDecision;
use App\Enums\RevisionItemStatus;
use App\Enums\RevisionStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqCogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BoqWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_create_boq_as_draft_with_cogs_and_items(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);

        $boq = $this->makeDraftBoq($actor, $project);

        $this->assertSame(BoqStatus::Draft, $boq->status);
        $this->assertCount(2, $boq->cogs);
        $this->assertSame(11000000.0, $boq->totalAmount());
    }

    public function test_submit_valid_boq(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);

        $submitted = app(SubmitBOQ::class)->handle($actor, $boq);

        $this->assertSame(BoqStatus::WaitingApproval, $submitted->status);
        $this->assertSame($actor->id, $submitted->submitted_by);
        $this->assertNotNull($submitted->submitted_at);
    }

    public function test_cannot_submit_empty_boq(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);

        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'code' => 'BOQ-EMPTY',
            'status' => BoqStatus::Draft,
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('belum memiliki COGS');

        app(SubmitBOQ::class)->handle($actor, $boq);
    }

    public function test_cannot_submit_boq_with_cogs_but_no_items(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = Boq::factory()->create([
            'project_id' => $project->id,
            'status' => BoqStatus::Draft,
        ]);
        BoqCogs::factory()->create([
            'boq_id' => $boq->id,
            'code' => 'COGS-001',
        ]);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('belum memiliki item');

        app(SubmitBOQ::class)->handle($actor, $boq->fresh(['cogs.items']));
    }

    public function test_confirm_boq(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        app(SubmitBOQ::class)->handle($actor, $boq);

        $confirmed = app(ConfirmBOQ::class)->handle($actor, $boq->fresh(), 'Looks good');

        $this->assertSame(BoqStatus::Approved, $confirmed->status);
        $this->assertSame($actor->id, $confirmed->reviewed_by);
        $this->assertNotNull($confirmed->reviewed_at);
        $this->assertSame('Looks good', $confirmed->review_notes);
    }

    public function test_cannot_confirm_boq_that_is_not_waiting_approval(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('bukan WAITING_APPROVAL');

        app(ConfirmBOQ::class)->handle($actor, $boq);
    }

    public function test_request_revision_creates_history_for_selected_items_only(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        app(SubmitBOQ::class)->handle($actor, $boq);

        $designDrawing = $boq->fresh('cogs.items')->cogs->first()->items->firstWhere('code', 'ITEM-E01');
        $calculation = $boq->fresh('cogs.items')->cogs->first()->items->firstWhere('code', 'ITEM-E02');

        $revision = app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan.',
            'item_ids' => [$designDrawing->id],
            'item_reasons' => [
                $designDrawing->id => 'Harga perlu disesuaikan.',
            ],
        ]);

        $this->assertSame(BoqStatus::RevisionRequired, $boq->fresh()->status);
        $this->assertSame('BOQ-PRJ001-REV01', $revision->revision_code);
        $this->assertSame(1, $revision->revision_number);
        $this->assertSame(RevisionStatus::Open, $revision->status);
        $this->assertCount(1, $revision->revisionItems);
        $this->assertSame($designDrawing->id, $revision->revisionItems->first()->boq_item_id);
        $this->assertSame(RevisionItemStatus::Pending, $revision->revisionItems->first()->status);
        $this->assertFalse(
            $revision->revisionItems->contains(fn ($item) => $item->boq_item_id === $calculation->id)
        );
    }

    public function test_revision_requires_reason(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        app(SubmitBOQ::class)->handle($actor, $boq);
        $itemId = $boq->fresh('cogs.items')->cogs->first()->items->first()->id;

        $this->expectException(ValidationException::class);

        app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => '',
            'item_ids' => [$itemId],
        ]);
    }

    public function test_revision_requires_at_least_one_item(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        app(SubmitBOQ::class)->handle($actor, $boq);

        $this->expectException(ValidationException::class);

        app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Perlu revisi harga.',
            'item_ids' => [],
        ]);
    }

    public function test_submit_revised_boq_keeps_revision_history(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeDraftBoq($actor, $project);
        app(SubmitBOQ::class)->handle($actor, $boq);

        $designDrawing = $boq->fresh('cogs.items')->cogs->first()->items->firstWhere('code', 'ITEM-E01');

        app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan.',
            'item_ids' => [$designDrawing->id],
        ]);

        app(PrepareBoqForRevision::class)->handle($actor, $boq->fresh());

        app(UpdateBoqItem::class)->handle($actor, $designDrawing->fresh(), [
            'unit_price' => 2500000,
        ]);

        $resubmitted = app(SubmitBOQ::class)->handle($actor, $boq->fresh(['cogs.items']));

        $this->assertSame(BoqStatus::WaitingApproval, $resubmitted->status);
        $this->assertDatabaseCount('boq_revisions', 1);
        $this->assertDatabaseHas('boq_revisions', [
            'boq_id' => $boq->id,
            'revision_code' => 'BOQ-PRJ001-REV01',
            'status' => RevisionStatus::Open->value,
        ]);
        $this->assertSame('2500000.00', (string) $designDrawing->fresh()->total_price);
    }

    public function test_cannot_edit_approved_boq_items_freely(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $item = $boq->cogs->first()->items->first();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('melalui mekanisme revision');

        app(UpdateBoqItem::class)->handle($actor, $item, [
            'unit_price' => 999999,
        ]);
    }

    public function test_review_boq_can_confirm_or_request_revision(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-300']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-PRJ300');
        app(SubmitBOQ::class)->handle($actor, $boq);

        $confirmed = app(ReviewBOQ::class)->handle(
            $actor,
            $boq->fresh(),
            ReviewDecision::Confirm,
            ['review_notes' => 'OK'],
        );

        $this->assertInstanceOf(Boq::class, $confirmed);
        $this->assertSame(BoqStatus::Approved, $confirmed->status);

        $boqB = $this->makeDraftBoq($actor, $project, code: 'BOQ-PRJ300B');
        app(SubmitBOQ::class)->handle($actor, $boqB);
        $itemId = $boqB->fresh('cogs.items')->cogs->first()->items->first()->id;

        $revision = app(ReviewBOQ::class)->handle(
            $actor,
            $boqB->fresh(),
            ReviewDecision::RequestRevision,
            [
                'reason' => 'Harga perlu disesuaikan.',
                'item_ids' => [$itemId],
            ],
        );

        $this->assertSame('BOQ-PRJ300B-REV01', $revision->revision_code);
        $this->assertSame(BoqStatus::RevisionRequired, $boqB->fresh()->status);
    }

    public function test_submit_revision_resubmits_from_revision_required(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-301']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-PRJ301');
        app(SubmitBOQ::class)->handle($actor, $boq);

        $item = $boq->fresh('cogs.items')->cogs->first()->items->first();
        app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Harga perlu disesuaikan.',
            'item_ids' => [$item->id],
        ]);

        app(PrepareBoqForRevision::class)->handle($actor, $boq->fresh());
        app(UpdateBoqItem::class)->handle($actor, $item->fresh(), [
            'unit_price' => 2100000,
        ]);

        // Put back to REVISION_REQUIRED style path: submit revision from DRAFT via SubmitRevision
        $resubmitted = app(SubmitRevision::class)->handle($actor, $boq->fresh());

        $this->assertSame(BoqStatus::WaitingApproval, $resubmitted->status);
        $this->assertDatabaseHas('boq_revisions', [
            'boq_id' => $boq->id,
            'revision_code' => 'BOQ-PRJ301-REV01',
        ]);
    }

    public function test_approved_boq_can_be_revised_then_must_be_approved_again(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-REV-AP']);
        $boq = $this->makeApprovedBoq($actor, $project, code: 'BOQ-REV-AP');
        $item = $boq->cogs->first()->items->first();

        $revision = app(RequestRevision::class)->handle($actor, $boq->fresh(), [
            'reason' => 'Perlu penyesuaian harga setelah approval.',
            'item_ids' => [$item->id],
        ]);

        $this->assertSame(BoqStatus::RevisionRequired, $boq->fresh()->status);
        $this->assertSame(RevisionStatus::Open, $revision->status);

        app(PrepareBoqForRevision::class)->handle($actor, $boq->fresh());
        app(UpdateBoqItem::class)->handle($actor, $item->fresh(), [
            'unit_price' => 2200000,
        ]);

        $resubmitted = app(SubmitRevision::class)->handle($actor, $boq->fresh());

        $this->assertSame(BoqStatus::WaitingApproval, $resubmitted->status);

        $confirmed = app(ConfirmBOQ::class)->handle($actor, $resubmitted->fresh(), 'Re-approved');

        $this->assertSame(BoqStatus::Approved, $confirmed->status);
        $this->assertSame(RevisionStatus::Resolved, $revision->fresh()->status);
        $this->assertSame('2200000.00', (string) $item->fresh()->unit_price);
    }

    public function test_create_boq_requires_cogs_and_items(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);

        $this->expectException(ValidationException::class);

        app(CreateBOQ::class)->handle($actor, $project, [
            'code' => 'BOQ-EMPTY',
            'cogs' => [],
        ]);
    }
}
