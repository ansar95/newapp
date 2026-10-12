<?php

namespace Tests\Feature;

use App\Actions\BOQ\SubmitBOQ;
use App\Enums\BoqStatus;
use App\Support\BoqCogsCsv;
use App\Support\SimpleXlsx;
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
        $response->assertHeader('content-type', SimpleXlsx::MIME);
        $this->assertStringContainsString('BOQ-CSV-cogs.xlsx', (string) $response->headers->get('content-disposition'));
        $rows = SimpleXlsx::readString($response->streamedContent());

        $this->assertSame(BoqCogsCsv::HEADERS, $rows[0]);
        $this->assertContains('Engineering', array_column($rows, 0));
        $this->assertContains('ITEM-E01', array_column($rows, 2));
    }

    public function test_can_download_cogs_excel_template(): void
    {
        $actor = $this->makeSuperadmin();

        $response = $this->actingAs($actor)
            ->get(route('boqs.cogs.template'));

        $response->assertOk();
        $response->assertHeader('content-type', SimpleXlsx::MIME);
        $rows = SimpleXlsx::readString($response->streamedContent());
        $this->assertSame(BoqCogsCsv::TEMPLATE_HEADERS, $rows[0]);
        $this->assertNotContains('item_code', $rows[0]);
        $this->assertSame('2000000', $rows[1][7]);
    }

    public function test_excel_template_can_be_filled_and_imported(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-XLS']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-XLS');

        $rows = BoqCogsCsv::templateRows();
        $rows[] = ['Finishing', 'Cat, dempul', 'Cat tembok', 'Warna putih, 2 lapis', 12.5, 'm2', 45000.75, 15000, 'Merk Jotun'];
        $rows[] = ['Finishing', 'Cat, dempul', 'Plamir', '', 3, 'm2', 20000, 5000, ''];

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => UploadedFile::fake()->createWithContent('cogs.xlsx', SimpleXlsx::write($rows))])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cogs = $boq->fresh()->cogs()->where('name', 'Finishing')->sole();
        $this->assertSame(['ITEM-001', 'ITEM-002'], $cogs->items()->orderBy('id')->pluck('code')->all());
        $item = $cogs->items()->where('name', 'Cat tembok')->sole();

        $this->assertSame('Cat, dempul', $cogs->description);
        $this->assertSame('Warna putih, 2 lapis', $item->description);
        $this->assertSame(12.5, (float) $item->quantity);
        $this->assertSame(45000.75, (float) $item->material_unit_price);
        $this->assertSame('Merk Jotun', $item->notes);
    }

    public function test_semicolon_separated_csv_from_indonesian_excel_is_imported(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-SEMI']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-SEMI');

        $csv = "\xEF\xBB\xBFcogs_name;cogs_description;item_code;item_name;item_description;quantity;unit;material_price;service_price;item_notes\r\n"
            ."Elektrikal;Instalasi, panel;ITEM-S01;Kabel NYM 3x2,5;;2,5;m;Rp 12.500;2.500,50;\"Termasuk; klem\"\r\n";

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => UploadedFile::fake()->createWithContent('cogs.csv', $csv)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('boq_items', [
            'code' => 'ITEM-S01',
            'name' => 'Kabel NYM 3x2,5',
            'quantity' => 2.5,
            'material_unit_price' => 12500,
            'service_unit_price' => 2500.5,
            'notes' => 'Termasuk; klem',
        ]);
        $this->assertDatabaseHas('boqs_cogs', ['boq_id' => $boq->id, 'name' => 'Elektrikal', 'description' => 'Instalasi, panel']);
    }

    public function test_create_boq_form_can_parse_and_export_excel(): void
    {
        $actor = $this->makeSuperadmin();

        $this->actingAs($actor)
            ->postJson(route('boqs.cogs.parse'), ['file' => UploadedFile::fake()->createWithContent('cogs.xlsx', SimpleXlsx::write(BoqCogsCsv::templateRows()))])
            ->assertOk()
            ->assertJsonCount(3, 'rows')
            ->assertJsonPath('rows.2.item_code', '')
            ->assertJsonPath('rows.2.quantity', '10')
            ->assertJsonPath('rows.2.item_notes', 'Termasuk aksesoris');

        $this->actingAs($actor)
            ->postJson(route('boqs.cogs.parse'), ['file' => UploadedFile::fake()->createWithContent('cogs.csv', "name\nX\n")])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Kolom wajib hilang: cogs_name. Gunakan template yang disediakan.']);

        $response = $this->actingAs($actor)
            ->postJson(route('boqs.cogs.form-export'), ['rows' => [[
                'cogs_name' => 'Engineering',
                'item_code' => 'ITEM-001',
                'item_name' => 'Design',
                'quantity' => '2',
                'unit' => 'ls',
                'material_price' => '1500.5',
                'service_price' => '0',
            ]]]);

        $response->assertOk();
        $response->assertHeader('content-type', SimpleXlsx::MIME);
        $rows = SimpleXlsx::readString($response->streamedContent());
        $this->assertSame(BoqCogsCsv::HEADERS, $rows[0]);
        $this->assertSame(['Engineering', '', 'ITEM-001', 'Design', '', '2', 'ls', '1500.5', '0'], $rows[1]);

        $this->actingAs($this->makeProjectSite())
            ->postJson(route('boqs.cogs.form-export'), ['rows' => [['cogs_name' => 'X']]])
            ->assertForbidden();
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

        $rows = SimpleXlsx::readString($this->actingAs($actor)
            ->get(route('boqs.cogs.export', $boq))
            ->assertOk()
            ->streamedContent());

        $this->assertSame(['service_price', 'item_notes'], array_slice($rows[0], 8, 2));
        $this->assertContains(['ITEM-N01', 'Panel', '', '1', 'set', '7500000', '0', 'Merk Schneider'], array_map(fn (array $row) => array_slice($row, 2), $rows));
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

    public function test_existing_item_notes_can_be_edited_and_are_shown_in_review(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NEDT']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-NEDT');
        $item = $boq->cogs->first()->items->first();

        $this->actingAs($actor)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertSeeInOrder(['Item', 'Keterangan', 'Qty']);

        $this->actingAs($actor)
            ->patch(route('boqs.items.update', $item), [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'material_unit_price' => $item->material_unit_price,
                'service_unit_price' => $item->service_unit_price,
                'notes' => 'Sesuai gambar kerja rev. B',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Sesuai gambar kerja rev. B', $item->fresh()->notes);

        app(SubmitBOQ::class)->handle($actor, $boq->fresh(['project', 'cogs.items']));

        $this->actingAs($actor)
            ->get(route('boqs.review', $boq))
            ->assertOk()
            ->assertSee('Keterangan')
            ->assertSee('Sesuai gambar kerja rev. B');
    }

    public function test_create_boq_form_has_notes_column_per_item(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-NFRM']);

        $this->actingAs($actor)
            ->get(route('projects.boq.create', $project))
            ->assertOk()
            ->assertSee('Keterangan')
            ->assertSee('[notes]`', false);
    }

    public function test_item_code_is_generated_per_cogs_when_left_blank(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-AUTOI']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-AUTOI');
        $cogs = $boq->cogs->first();

        $this->actingAs($actor)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertSee('value="ITEM-001"', false);

        foreach (['Item A', 'Item B'] as $name) {
            $this->actingAs($actor)
                ->post(route('boqs.items.store', $cogs), [
                    'code' => '',
                    'name' => $name,
                    'quantity' => 1,
                    'unit' => 'ls',
                    'material_unit_price' => 1000,
                    'service_unit_price' => 0,
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(['ITEM-001', 'ITEM-002'], $cogs->items()->whereIn('name', ['Item A', 'Item B'])->orderBy('id')->pluck('code')->all());
    }

    public function test_create_boq_numbers_blank_item_codes_after_existing_ones(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-AUTOC']);

        $boq = $this->makeDraftBoq($actor, $project, cogs: [
            [
                'name' => 'Engineering',
                'items' => [
                    ['code' => 'ITEM-002', 'name' => 'A', 'quantity' => 1, 'unit' => 'ls', 'material_unit_price' => 1000],
                    ['code' => null, 'name' => 'B', 'quantity' => 1, 'unit' => 'ls', 'material_unit_price' => 1000],
                ],
            ],
            [
                'name' => 'Installation',
                'items' => [
                    ['code' => '', 'name' => 'C', 'quantity' => 1, 'unit' => 'ls', 'material_unit_price' => 1000],
                ],
            ],
        ], code: 'BOQ-AUTOC');

        $codes = $boq->cogs->mapWithKeys(fn ($cogs) => [$cogs->name => $cogs->items->pluck('code')->all()])->all();

        $this->assertSame(['Engineering' => ['ITEM-002', 'ITEM-003'], 'Installation' => ['ITEM-001']], $codes);
    }

    public function test_csv_import_without_item_code_generates_codes(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-AUTOV']);
        $boq = $this->makeDraftBoq($actor, $project, code: 'BOQ-AUTOV');

        $csv = BoqCogsCsv::rowsToCsv([
            ['cogs_name', 'cogs_description', 'item_name', 'item_description', 'quantity', 'unit', 'material_price', 'service_price'],
            ['Commissioning', '', 'Test A', '', '1', 'ls', '1000', '0'],
            ['Commissioning', '', 'Test B', '', '1', 'ls', '1000', '0'],
        ]);

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => UploadedFile::fake()->createWithContent('cogs.csv', $csv)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $cogs = $boq->fresh()->cogs()->where('name', 'Commissioning')->sole();

        $this->assertSame(['ITEM-001', 'ITEM-002'], $cogs->items()->orderBy('id')->pluck('code')->all());
    }

    public function test_cannot_import_cogs_when_boq_is_approved(): void
    {
        $actor = $this->makeSuperadmin();
        $project = $this->makeActiveProject($actor, ['code' => 'PRJ-IMP2']);
        $boq = $this->makeApprovedBoq($actor, $project, code: 'BOQ-IMP2');

        $file = UploadedFile::fake()->createWithContent('cogs.xlsx', SimpleXlsx::write(BoqCogsCsv::templateRows()));

        $this->actingAs($actor)
            ->post(route('boqs.cogs.import', $boq), ['file' => $file])
            ->assertForbidden();

        $this->assertSame(BoqStatus::Approved, $boq->fresh()->status);
    }
}
