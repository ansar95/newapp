<?php

namespace Tests\Feature;

use App\Actions\Progress\UpdateItemProgress;
use App\Enums\ProgressStatus;
use App\Exceptions\BusinessRuleException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProgressWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_update_item_progress(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $this->makePaidPlanForBoq($actor, $boq);

        $item = $boq->cogs->first()->items->firstWhere('code', 'ITEM-E01');

        $updated = app(UpdateItemProgress::class)->handle($actor, $item, 50);

        $this->assertSame('50.00', (string) $updated->progress_percentage);
        $this->assertSame(ProgressStatus::InProgress, $updated->progressStatus());
    }

    public function test_progress_cannot_be_less_than_zero(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $this->makePaidPlanForBoq($actor, $boq);
        $item = $boq->cogs->first()->items->first();

        $this->expectException(ValidationException::class);

        app(UpdateItemProgress::class)->handle($actor, $item, -1);
    }

    public function test_progress_cannot_be_greater_than_one_hundred(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor);
        $boq = $this->makeApprovedBoq($actor, $project);
        $this->makePaidPlanForBoq($actor, $boq);
        $item = $boq->cogs->first()->items->first();

        $this->expectException(ValidationException::class);

        app(UpdateItemProgress::class)->handle($actor, $item, 101);
    }

    public function test_cogs_and_project_progress_are_calculated_from_items(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-100']);
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-PRJ100');
        $this->makePaidPlanForBoq($actor, $boq, 'PP-100');

        $engineering = $boq->cogs->firstWhere('code', 'COGS-001');
        $items = $engineering->items->keyBy('code');

        app(UpdateItemProgress::class)->handle($actor, $items['ITEM-E01'], 100);
        app(UpdateItemProgress::class)->handle($actor, $items['ITEM-E02'], 50);
        app(UpdateItemProgress::class)->handle($actor, $items['ITEM-E03'], 20);

        // Engineering only: earned 2M+1.5M+1M = 4.5M / 10M = 45%
        $this->assertSame(45.0, $engineering->fresh()->progressPercentage());

        // Whole BOQ: engineering earned 4.5M + installation 0 / 11M
        $this->assertSame(40.91, $boq->fresh()->progressPercentage());
        $this->assertSame(40.91, $project->fresh()->progressPercentage());
    }

    public function test_paid_payment_does_not_automatically_set_progress_to_one_hundred(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-101']);
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-PRJ101');
        $this->makePaidPlanForBoq($actor, $boq, 'PP-101');

        foreach ($boq->fresh('cogs.items')->cogs as $cogs) {
            foreach ($cogs->items as $item) {
                $this->assertSame('0.00', (string) $item->progress_percentage);
                $this->assertSame(ProgressStatus::NotStarted, $item->progressStatus());
            }
        }

        $this->assertSame(0.0, $boq->fresh()->progressPercentage());
        $this->assertSame(0.0, $project->fresh()->progressPercentage());
    }

    public function test_cannot_update_progress_before_payment_is_paid(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-102']);
        $boq = $this->makeApprovedBoq($actor, $project, 'BOQ-PRJ102');
        $item = $boq->cogs->first()->items->first();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('setelah Payment terkait berstatus PAID');

        app(UpdateItemProgress::class)->handle($actor, $item, 25);
    }
}
