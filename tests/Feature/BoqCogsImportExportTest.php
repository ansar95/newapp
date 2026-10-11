<?php

namespace Tests\Feature;

use App\Enums\BoqStatus;
use App\Support\BoqCogsCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
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
        $this->assertStringNotContainsString('item_category', $content);
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
            ['Keep', '', 'ITEM-KEEP', 'Existing', '', '1', 'ls', '1000000', '0'],
            ['Keep', '', 'ITEM-NEW', 'New Item', '', '2', 'm', '50000', '0'],
            ['Procurement', 'Materials', 'ITEM-P01', 'Panel', '', '1', 'set', '7500000', '0'],
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
    }

    public function test_boq_items_no_longer_have_a_category(): void
    {
        $this->assertFalse(Schema::hasColumn('boq_items', 'category'));

        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-CAT']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-CAT');

        $this->actingAs($actor)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertDontSee('Kategori')
            ->assertDontSee('name="category"', false);
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

    public function test_add_item_stores_notes_and_shows_them_on_boq_page(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NOTE']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-NOTE');
        $cogs = $boq->cogs->first();

        $this->actingAs($actor)
            ->post(route('boqs.items.store', $cogs), [
                'code' => 'ITEM-NOTE',
                'name' => 'Kabel NYY',
                'quantity' => 10,
                'unit' => 'm',
                'material_unit_price' => 50000,
                'service_unit_price' => 0,
                'notes' => 'Termasuk aksesoris',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('boq_items', ['code' => 'ITEM-NOTE', 'notes' => 'Termasuk aksesoris']);

        $this->actingAs($actor)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertSee('Keterangan')
            ->assertSee('name="notes"', false)
            ->assertSee('Termasuk aksesoris');
    }

    public function test_add_item_rejects_notes_longer_than_255_characters(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NOTE2']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-NOTE2');

        $this->actingAs($actor)
            ->post(route('boqs.items.store', $boq->cogs->first()), [
                'code' => 'ITEM-LONG',
                'name' => 'Item',
                'quantity' => 1,
                'unit' => 'ls',
                'material_unit_price' => 1000,
                'service_unit_price' => 0,
                'notes' => str_repeat('a', 256),
            ])
            ->assertSessionHasErrors(['notes' => 'Keterangan maksimal 255 karakter.']);

        $this->assertDatabaseMissing('boq_items', ['code' => 'ITEM-LONG']);
    }

    public function test_csv_import_and_export_include_item_notes(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NCSV']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-NCSV');

        $csv = BoqCogsCsv::rowsToCsv([
            BoqCogsCsv::HEADERS,
            ['Procurement', '', 'ITEM-N01', 'Panel', '', '1', 'set', '7500000', '0', 'Merk Schneider'],
        ]);

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => UploadedFile::fake()->createWithContent('cogs.csv', $csv)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('boq_items', ['code' => 'ITEM-N01', 'notes' => 'Merk Schneider']);

        $content = $this->actingAs($actor)
            ->get(route('boqs.cogs.export', $boq))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('service_price,item_notes', $content);
        $this->assertStringContainsString('ITEM-N01,Panel,,1,set,7500000,0,"Merk Schneider"', $content);
    }

    public function test_create_boq_stores_item_notes(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NCRT']);

        $this->makeDraftBoq($actor, $project, cogs: [
            [
                'name' => 'Engineering',
                'items' => [
                    [
                        'code' => 'ITEM-C01',
                        'name' => 'Design',
                        'quantity' => 1,
                        'unit' => 'ls',
                        'material_unit_price' => 1000000,
                        'notes' => 'Dari import CSV',
                    ],
                ],
            ],
        ], code: 'BOQ-NCRT');

        $this->assertDatabaseHas('boq_items', ['code' => 'ITEM-C01', 'notes' => 'Dari import CSV']);
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
