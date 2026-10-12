<?php

namespace Tests\Feature;

use App\Actions\BOQ\PrepareBoqForRevision;
use App\Actions\BOQ\RequestRevision;
use App\Models\Boq;
use App\Models\BoqItem;
use App\Models\Project;
use App\Models\User;
use App\Support\BoqCogsCsv;
use App\Support\SimpleXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BoqMaterialServicePriceTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->project = $this->makeActiveProject($this->admin);
    }

    private function makeSplitBoq(): Boq
    {
        return $this->makeDraftBoq($this->admin, $this->project, cogs: [
            [
                'name' => 'Instalasi',
                'items' => [
                    ['code' => 'ITEM-A', 'name' => 'Kabel', 'quantity' => 10, 'unit' => 'm', 'material_unit_price' => 50_000, 'service_unit_price' => 20_000],
                    ['code' => 'ITEM-B', 'name' => 'Panel', 'quantity' => 2, 'unit' => 'set', 'material_unit_price' => 1_000_000, 'service_unit_price' => 250_000],
                ],
            ],
        ], code: 'BOQ-SPLIT');
    }

    public function test_item_total_is_quantity_times_material_plus_service(): void
    {
        $boq = $this->makeSplitBoq()->fresh('cogs.items');
        $cogs = $boq->cogs->first();
        $cable = $cogs->items->firstWhere('code', 'ITEM-A');

        $this->assertSame('50000.00', $cable->material_unit_price);
        $this->assertSame('20000.00', $cable->service_unit_price);
        $this->assertSame('70000.00', $cable->unit_price);
        $this->assertSame('700000.00', $cable->total_price);
        $this->assertSame(500_000.0, $cable->materialTotal());
        $this->assertSame(200_000.0, $cable->serviceTotal());

        $this->assertSame(2_500_000.0, $cogs->materialAmount());
        $this->assertSame(700_000.0, $cogs->serviceAmount());
        $this->assertSame(3_200_000.0, $boq->totalAmount());
        $this->assertSame(2_500_000.0, $boq->materialAmount());
        $this->assertSame(700_000.0, $boq->serviceAmount());
    }

    public function test_factory_split_price_state_builds_unit_price_from_both_parts(): void
    {
        $item = BoqItem::factory()->splitPrice(30_000, 12_500)->create(['quantity' => 4]);

        $this->assertSame('42500.00', $item->unit_price);
        $this->assertSame('170000.00', $item->total_price);
        $this->assertSame(50_000.0, $item->serviceTotal());
    }

    public function test_legacy_single_price_is_stored_as_material_price(): void
    {
        $item = $this->makeDraftBoq($this->admin, $this->project, code: 'BOQ-LEGACY')
            ->cogs->first()->items->firstWhere('code', 'ITEM-E01');

        $this->assertSame('2000000.00', $item->material_unit_price);
        $this->assertSame('0.00', $item->service_unit_price);
        $this->assertSame('2000000.00', $item->total_price);
    }

    public function test_updating_only_service_price_recalculates_total(): void
    {
        $item = $this->makeSplitBoq()->cogs->first()->items->firstWhere('code', 'ITEM-A');

        $this->actingAs($this->admin)
            ->patch(route('boqs.items.update', $item), ['service_unit_price' => 30_000])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('50000.00', $item->material_unit_price);
        $this->assertSame('30000.00', $item->service_unit_price);
        $this->assertSame('800000.00', $item->total_price);
    }

    public function test_add_item_form_requires_valid_material_and_service_prices(): void
    {
        $cogs = $this->makeSplitBoq()->cogs->first();

        $this->actingAs($this->admin)
            ->post(route('boqs.items.store', $cogs), [
                'code' => 'ITEM-C', 'name' => 'Grounding',
                'quantity' => 1, 'unit' => 'ls', 'service_unit_price' => -1,
            ])
            ->assertSessionHasErrors([
                'material_unit_price' => 'Harga material wajib diisi (isi 0 jika tidak ada).',
                'service_unit_price' => 'Harga jasa tidak boleh kurang dari 0.',
            ]);

        $this->actingAs($this->admin)
            ->post(route('boqs.items.store', $cogs), [
                'code' => 'ITEM-C', 'name' => 'Grounding',
                'quantity' => 3, 'unit' => 'titik', 'material_unit_price' => 0, 'service_unit_price' => 150_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('boq_items', [
            'code' => 'ITEM-C',
            'material_unit_price' => 0,
            'service_unit_price' => 150_000,
            'total_price' => 450_000,
        ]);
    }

    public function test_revision_form_updates_material_and_service_prices(): void
    {
        $boq = $this->makeApprovedBoq($this->admin, $this->project, 'BOQ-REV');
        $item = $boq->cogs->first()->items->firstWhere('code', 'ITEM-E01');

        app(RequestRevision::class)->handle($this->admin, $boq->fresh(), [
            'reason' => 'Pisahkan harga jasa.',
            'item_ids' => [$item->id],
        ]);
        app(PrepareBoqForRevision::class)->handle($this->admin, $boq->fresh());

        $this->actingAs($this->admin)
            ->patch(route('boqs.revision.items.update', $item), [
                'quantity' => 1,
                'unit' => 'ls',
                'material_unit_price' => 1_500_000,
                'service_unit_price' => 700_000,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('1500000.00', $item->material_unit_price);
        $this->assertSame('700000.00', $item->service_unit_price);
        $this->assertSame('2200000.00', $item->total_price);
    }

    public function test_csv_template_and_export_use_material_and_service_columns(): void
    {
        $this->assertSame(['quantity', 'unit', 'material_price', 'service_price'], array_slice(BoqCogsCsv::templateRows()[0], 4, 4));

        $boq = $this->makeSplitBoq();

        $rows = SimpleXlsx::readString($this->actingAs($this->admin)
            ->get(route('boqs.cogs.export', $boq))
            ->assertOk()
            ->streamedContent());

        $this->assertSame(BoqCogsCsv::HEADERS, $rows[0]);
        $this->assertContains(['ITEM-A', 'Kabel', '', '10', 'm', '50000', '20000'], array_map(fn (array $row) => array_slice($row, 2, 7), $rows));
    }

    public function test_csv_import_accepts_new_and_legacy_price_columns(): void
    {
        $boq = $this->makeSplitBoq();

        $newFormat = BoqCogsCsv::rowsToCsv([
            BoqCogsCsv::HEADERS,
            ['Instalasi', '', 'ITEM-N1', 'Tray', '', '4', 'm', '100000', '25000'],
        ]);
        $legacyFormat = BoqCogsCsv::rowsToCsv([
            ['cogs_name', 'cogs_description', 'item_code', 'item_name', 'item_description', 'item_category', 'quantity', 'unit', 'unit_price'],
            ['Instalasi', '', 'ITEM-L1', 'Lampu', '', 'consumable', '5', 'pcs', '40000'],
        ]);

        foreach ([$newFormat, $legacyFormat] as $csv) {
            $this->actingAs($this->admin)
                ->post(route('boqs.cogs.import', $boq), ['file' => UploadedFile::fake()->createWithContent('cogs.csv', $csv)])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('boq_items', ['code' => 'ITEM-N1', 'material_unit_price' => 100_000, 'service_unit_price' => 25_000, 'total_price' => 500_000]);
        $this->assertDatabaseHas('boq_items', ['code' => 'ITEM-L1', 'material_unit_price' => 40_000, 'service_unit_price' => 0, 'total_price' => 200_000]);
    }

    public function test_boq_page_shows_material_and_service_breakdown(): void
    {
        $boq = $this->makeSplitBoq();

        $this->actingAs($this->admin)
            ->get(route('boqs.show', $boq))
            ->assertOk()
            ->assertSee('Total Material')
            ->assertSee('Total Jasa')
            ->assertSee('Rp2.500.000')
            ->assertSee('Rp700.000')
            ->assertSee('name="material_unit_price"', false)
            ->assertSee('name="service_unit_price"', false);
    }
}
