<?php

namespace Tests\Feature;

use App\Enums\BoqItemCategory;
use App\Enums\BoqStatus;
use App\Support\BoqCogsCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BoqCogsImportExportTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_can_export_boq_cogs_as_csv(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-CSV']);
        $boq = $this->makeApprovedBoq($actor, $project, code: 'BOQ-CSV');

        $response = $this->actingAs($actor)
            ->get(route('boqs.cogs.export', $boq));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $content = $response->streamedContent();

        $this->assertStringContainsString('cogs_name,cogs_description,item_code,item_name', $content);
        $this->assertStringContainsString('item_category', $content);
        $this->assertStringNotContainsString('cogs_code', $content);
        $this->assertStringContainsString('Engineering', $content);
        $this->assertStringContainsString('ITEM-E01', $content);
    }

    public function test_can_download_cogs_csv_template(): void
    {
        $actor = $this->makeSuperadmin();

        $response = $this->actingAs($actor)
            ->get(route('boqs.cogs.template'));

        $response->assertOk();
        $content = $response->streamedContent();
        $this->assertStringContainsString('cogs_name', $content);
        $this->assertStringNotContainsString('cogs_code', $content);
    }

    public function test_can_import_cogs_csv_into_draft_boq(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-IMP']);
        $boq = $this->makeDraftBoq($actor, $project, cogs: [
            [
                'name' => 'Keep',
                'items' => [
                    [
                        'code' => 'ITEM-KEEP',
                        'name' => 'Existing',
                        'quantity' => 1,
                        'unit' => 'ls',
                        'unit_price' => 1000000,
                    ],
                ],
            ],
        ], code: 'BOQ-IMP');

        $csv = BoqCogsCsv::rowsToCsv([
            BoqCogsCsv::HEADERS,
            ['Keep', '', 'ITEM-KEEP', 'Existing', '', 'main_material', '1', 'ls', '1000000'],
            ['Keep', '', 'ITEM-NEW', 'New Item', '', 'consumable', '2', 'm', '50000'],
            ['Procurement', 'Materials', 'ITEM-P01', 'Panel', '', 'main_material', '1', 'set', '7500000'],
        ]);

        $file = UploadedFile::fake()->createWithContent('cogs.csv', $csv);

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('success');

        $boq->refresh()->load('cogs.items');

        $this->assertSame(2, $boq->cogs->count());
        $this->assertNotNull($boq->cogs->firstWhere('name', 'Procurement'));
        $this->assertTrue(
            $boq->cogs->firstWhere('name', 'Keep')->items->contains(fn ($item) => $item->code === 'ITEM-NEW')
        );
        $this->assertSame('COGS-002', $boq->cogs->firstWhere('name', 'Procurement')->code);
        $this->assertSame(
            BoqItemCategory::Consumable,
            $boq->cogs->firstWhere('name', 'Keep')->items->firstWhere('code', 'ITEM-NEW')->category
        );
    }

    public function test_can_create_item_with_category(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-CAT']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-CAT');
        $cogs = $boq->cogs->first();

        $this->actingAs($actor)
            ->post(route('boqs.items.store', $cogs), [
                'code' => 'ITEM-CONS',
                'name' => 'Sealant',
                'category' => 'consumable',
                'quantity' => 5,
                'unit' => 'tube',
                'unit_price' => 75000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('boq_items', [
            'boq_cogs_id' => $cogs->id,
            'code' => 'ITEM-CONS',
            'category' => 'consumable',
        ]);
    }

    public function test_add_cogs_auto_generates_code(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-AUTO']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-AUTO');

        $this->actingAs($actor)
            ->post(route('boqs.cogs.store', $boq), [
                'name' => 'Extra Works',
                'description' => 'Auto code',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('boqs_cogs', [
            'boq_id' => $boq->id,
            'name' => 'Extra Works',
            'code' => 'COGS-003',
        ]);
    }

    public function test_cannot_import_cogs_when_boq_is_approved(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-IMP2']);
        $boq = $this->makeApprovedBoq($actor, $project, code: 'BOQ-IMP2');

        $csv = BoqCogsCsv::templateCsv();
        $file = UploadedFile::fake()->createWithContent('cogs.csv', $csv);

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => $file])
            ->assertForbidden();

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
    }
}
