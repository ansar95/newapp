<?php

namespace Tests\Feature;

use App\Enums\BoqStatus;
use App\Enums\ProgressStatus;
use App\Exceptions\InvalidProgressPercentageException;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoqCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_total_is_quantity_times_unit_price(): void
    {
        $item = BoqItem::factory()->create([
            'quantity' => 10,
            'unit_price' => 150000,
        ]);

        $this->assertSame('1500000.00', (string) $item->fresh()->total_price);
    }

    public function test_item_total_recalculates_when_quantity_or_price_changes(): void
    {
        $item = BoqItem::factory()->create([
            'quantity' => 2,
            'unit_price' => 1000000,
        ]);

        $item->update([
            'quantity' => 3,
            'unit_price' => 2000000,
        ]);

        $this->assertSame('6000000.00', (string) $item->fresh()->total_price);
    }

    public function test_cogs_total_is_sum_of_item_totals(): void
    {
        $cogs = BoqCogs::factory()->create();

        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'A',
            'quantity' => 1,
            'unit_price' => 2000000,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'B',
            'quantity' => 1,
            'unit_price' => 3000000,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'C',
            'quantity' => 1,
            'unit_price' => 5000000,
        ]);

        $this->assertSame(10000000.0, $cogs->fresh()->totalAmount());
    }

    public function test_boq_total_is_sum_of_cogs_totals(): void
    {
        $boq = Boq::factory()->create();

        $cogsA = BoqCogs::factory()->create(['boq_id' => $boq->id, 'code' => 'COGS-A']);
        $cogsB = BoqCogs::factory()->create(['boq_id' => $boq->id, 'code' => 'COGS-B']);

        BoqItem::factory()->create([
            'boq_cogs_id' => $cogsA->id,
            'code' => 'A1',
            'quantity' => 1,
            'unit_price' => 10000000,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogsB->id,
            'code' => 'B1',
            'quantity' => 1,
            'unit_price' => 5000000,
        ]);

        $this->assertSame(15000000.0, $boq->fresh()->totalAmount());
    }

    public function test_item_weight_against_cogs_and_boq(): void
    {
        $boq = Boq::factory()->create();
        $cogs = BoqCogs::factory()->create(['boq_id' => $boq->id]);

        $itemA = BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'A',
            'quantity' => 1,
            'unit_price' => 2000000,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'B',
            'quantity' => 1,
            'unit_price' => 3000000,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'C',
            'quantity' => 1,
            'unit_price' => 5000000,
        ]);

        $itemA->refresh();

        $this->assertSame(20.0, $itemA->weightAgainstCogs());
        $this->assertSame(20.0, $itemA->weightAgainstBoq());
    }

    public function test_cogs_progress_uses_earned_value(): void
    {
        $cogs = BoqCogs::factory()->create();

        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'A',
            'quantity' => 1,
            'unit_price' => 2000000,
            'progress_percentage' => 100,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'B',
            'quantity' => 1,
            'unit_price' => 3000000,
            'progress_percentage' => 50,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'C',
            'quantity' => 1,
            'unit_price' => 5000000,
            'progress_percentage' => 20,
        ]);

        // Earned: 2M + 1.5M + 1M = 4.5M / 10M = 45%
        $this->assertSame(45.0, $cogs->fresh()->progressPercentage());
    }

    public function test_project_progress_uses_approved_boq_items(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['created_by' => $user->id]);
        $boq = Boq::factory()->approved()->create([
            'project_id' => $project->id,
            'submitted_by' => $user->id,
            'reviewed_by' => $user->id,
        ]);
        $cogs = BoqCogs::factory()->create(['boq_id' => $boq->id]);

        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'A',
            'quantity' => 1,
            'unit_price' => 2000000,
            'progress_percentage' => 100,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'B',
            'quantity' => 1,
            'unit_price' => 3000000,
            'progress_percentage' => 50,
        ]);
        BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'C',
            'quantity' => 1,
            'unit_price' => 5000000,
            'progress_percentage' => 20,
        ]);

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
        $this->assertSame(10000000.0, $project->fresh()->totalCost());
        $this->assertSame(45.0, $project->fresh()->progressPercentage());
    }

    public function test_progress_cannot_exceed_100(): void
    {
        $this->expectException(InvalidProgressPercentageException::class);

        BoqItem::factory()->create([
            'progress_percentage' => 100.01,
        ]);
    }

    public function test_progress_cannot_be_negative(): void
    {
        $this->expectException(InvalidProgressPercentageException::class);

        BoqItem::factory()->create([
            'progress_percentage' => -1,
        ]);
    }

    public function test_progress_status_is_derived_from_percentage(): void
    {
        $notStarted = BoqItem::factory()->create(['progress_percentage' => 0]);
        $inProgress = BoqItem::factory()->create(['progress_percentage' => 35]);
        $completed = BoqItem::factory()->create(['progress_percentage' => 100]);

        $this->assertSame(ProgressStatus::NotStarted, $notStarted->progressStatus());
        $this->assertSame(ProgressStatus::InProgress, $inProgress->progressStatus());
        $this->assertSame(ProgressStatus::Completed, $completed->progressStatus());
    }

    public function test_progress_and_totals_handle_zero_division(): void
    {
        $cogs = BoqCogs::factory()->create();
        $boq = $cogs->boq;
        $project = $boq->project;

        $this->assertSame(0.0, $cogs->progressPercentage());
        $this->assertSame(0.0, $boq->progressPercentage());
        $this->assertSame(0.0, $project->progressPercentage());
        $this->assertSame(0.0, $cogs->totalAmount());
    }
}
