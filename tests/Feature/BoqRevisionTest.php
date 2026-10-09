<?php

namespace Tests\Feature;

use App\Enums\RevisionItemStatus;
use App\Enums\RevisionStatus;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\BoqItem;
use App\Models\BoqRevision;
use App\Models\BoqRevisionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoqRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revision_can_store_targeted_items_without_removing_history(): void
    {
        $user = User::factory()->create();
        $boq = Boq::factory()->create(['code' => 'BOQ-PRJ001']);
        $cogs = BoqCogs::factory()->create(['boq_id' => $boq->id, 'code' => 'COGS-001', 'name' => 'Engineering']);

        $designDrawing = BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'ITEM-E01',
            'name' => 'Design Drawing',
            'quantity' => 1,
            'unit_price' => 2000000,
        ]);
        $otherItem = BoqItem::factory()->create([
            'boq_cogs_id' => $cogs->id,
            'code' => 'ITEM-E02',
            'name' => 'Structural Calculation',
            'quantity' => 1,
            'unit_price' => 3000000,
        ]);

        $revision = BoqRevision::factory()->create([
            'boq_id' => $boq->id,
            'revision_code' => 'BOQ-PRJ001-REV01',
            'revision_number' => 1,
            'reason' => 'Harga pada beberapa item perlu disesuaikan',
            'status' => RevisionStatus::Open,
            'created_by' => $user->id,
        ]);

        $revisionItem = BoqRevisionItem::factory()->create([
            'boq_revision_id' => $revision->id,
            'boq_item_id' => $designDrawing->id,
            'reason' => 'Harga perlu disesuaikan',
            'status' => RevisionItemStatus::Pending,
        ]);

        $this->assertDatabaseCount('boq_revisions', 1);
        $this->assertDatabaseCount('boq_revision_items', 1);
        $this->assertTrue($revision->revisionItems->contains($revisionItem));
        $this->assertTrue($designDrawing->revisionItems->contains($revisionItem));
        $this->assertFalse(
            $revision->revisionItems->contains(fn (BoqRevisionItem $item): bool => $item->boq_item_id === $otherItem->id)
        );

        $secondRevision = BoqRevision::factory()->create([
            'boq_id' => $boq->id,
            'revision_code' => 'BOQ-PRJ001-REV02',
            'revision_number' => 2,
            'reason' => 'Revisi lanjutan',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseCount('boq_revisions', 2);
        $this->assertDatabaseHas('boq_revisions', [
            'id' => $revision->id,
            'revision_code' => 'BOQ-PRJ001-REV01',
        ]);
        $this->assertDatabaseHas('boq_revisions', [
            'id' => $secondRevision->id,
            'revision_code' => 'BOQ-PRJ001-REV02',
        ]);
    }
}
