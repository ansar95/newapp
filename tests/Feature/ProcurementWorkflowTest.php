<?php

namespace Tests\Feature;

use App\Actions\Procurement\PayProcurementRequest;
use App\Enums\AssetSourceType;
use App\Enums\AssetStatus;
use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Enums\ProjectAssetStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Asset;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\ProjectActualCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    private User $admin;

    private User $site;

    private User $approver;

    private User $finance;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeSuperadmin();
        $this->site = $this->makeProjectSite();
        $this->approver = $this->makeApprover();
        $this->finance = $this->makeFinance();
        $this->project = $this->makeActiveProject($this->admin);
    }

    public function test_full_bmhp_flow_from_draft_to_paid_uses_final_prices(): void
    {
        Storage::fake('public');

        $this->actingAs($this->site)
            ->post(route('projects.procurement.store', $this->project), [
                'type' => ProcurementType::Bmhp->value,
                'request_date' => now()->toDateString(),
                'supplier' => 'PT ABC Material',
                'items' => [
                    ['item_name' => 'Gypsum 9mm', 'unit' => 'Lembar', 'quantity' => 10, 'unit_price' => 50000],
                    ['item_name' => 'Hollow 4x4', 'unit' => 'Batang', 'quantity' => 4, 'unit_price' => 25000],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $request = ProcurementRequest::query()->sole();
        $this->assertSame('PR-0001', $request->request_number);
        $this->assertSame(ProcurementStatus::Draft, $request->status);
        $this->assertSame('600000.00', (string) $request->estimated_total);

        $this->actingAs($this->site)->post(route('procurement-requests.submit', $request))->assertRedirect();
        $this->assertSame(ProcurementStatus::Submitted, $request->fresh()->status);

        $this->actingAs($this->approver)->post(route('procurement-requests.approve', $request))->assertRedirect();
        $this->assertSame(ProcurementStatus::Approved, $request->fresh()->status);

        [$gypsum, $hollow] = $request->items->sortBy('id')->values()->all();

        $this->actingAs($this->finance)
            ->get(route('procurement-requests.pay', $request))
            ->assertOk()
            ->assertSee('Gypsum 9mm');

        $this->actingAs($this->finance)
            ->post(route('procurement-requests.pay.store', $request), [
                'payment_date' => now()->toDateString(),
                'invoice_number' => 'INV-001',
                'items' => [
                    $gypsum->id => ['paid_unit_price' => 48000],
                    $hollow->id => ['paid_unit_price' => 25000],
                ],
                'payment_proof_file' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('procurement-requests.show', $request));

        $request->refresh();
        $this->assertSame(ProcurementStatus::Paid, $request->status);
        $this->assertSame('580000.00', (string) $request->paid_total);
        $this->assertSame('600000.00', (string) $request->estimated_total);
        $this->assertSame($this->finance->id, $request->paid_by);
        $this->assertSame('480000.00', (string) $gypsum->fresh()->paid_total);
        Storage::disk('public')->assertExists($request->payment_proof_file);

        $this->actingAs($this->site)
            ->get(route('procurement-requests.show', $request))
            ->assertOk()
            ->assertSee('Dibayar')
            ->assertSee('Rp580.000');
    }

    public function test_payment_must_cover_every_item(): void
    {
        $request = $this->makeDraftProcurementRequest($this->site, $this->project, items: [
            $this->procurementItemPayload(ProcurementType::Bmhp),
            $this->procurementItemPayload(ProcurementType::Bmhp, name: 'Hollow'),
        ]);
        $this->submitAndApproveProcurementRequest($this->site, $this->approver, $request);

        $this->expectException(BusinessRuleException::class);

        app(PayProcurementRequest::class)->handle($this->finance, $request->fresh(), [
            'payment_date' => now()->toDateString(),
            'items' => [$request->items->first()->id => ['paid_unit_price' => 1000]],
        ]);
    }

    public function test_return_to_draft_requires_note_and_makes_request_editable_again(): void
    {
        $request = $this->makeDraftProcurementRequest($this->site, $this->project);
        $this->actingAs($this->site)->post(route('procurement-requests.submit', $request));

        $this->actingAs($this->site)->get(route('procurement-requests.edit', $request))->assertForbidden();

        $this->actingAs($this->approver)
            ->post(route('procurement-requests.return-to-draft', $request), ['return_note' => ''])
            ->assertSessionHasErrors('return_note');

        $this->actingAs($this->approver)
            ->post(route('procurement-requests.return-to-draft', $request), ['return_note' => 'Harga terlalu tinggi'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProcurementStatus::Draft, $request->fresh()->status);

        $this->actingAs($this->site)
            ->get(route('procurement-requests.show', $request))
            ->assertSee('Harga terlalu tinggi');

        $this->actingAs($this->site)
            ->put(route('procurement-requests.update', $request), [
                'request_date' => now()->toDateString(),
                'items' => [['item_name' => 'Gypsum 12mm', 'unit' => 'Lembar', 'quantity' => 3, 'unit_price' => 40000]],
            ])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('120000.00', (string) $request->estimated_total);
        $this->assertSame(['Gypsum 12mm'], $request->items()->pluck('item_name')->all());
    }

    public function test_request_can_be_cancelled_before_payment_but_not_after(): void
    {
        $approved = $this->makeDraftProcurementRequest($this->site, $this->project);
        $this->submitAndApproveProcurementRequest($this->site, $this->approver, $approved);

        $this->actingAs($this->finance)
            ->post(route('procurement-requests.cancel', $approved), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->actingAs($this->finance)
            ->post(route('procurement-requests.cancel', $approved), ['cancel_reason' => 'Supplier batal'])
            ->assertSessionHasNoErrors();

        $this->assertSame(ProcurementStatus::Cancelled, $approved->fresh()->status);

        $paid = $this->makePaidProcurementRequest($this->site, $this->project);

        $this->actingAs($this->finance)
            ->post(route('procurement-requests.cancel', $paid), ['cancel_reason' => 'Batal'])
            ->assertForbidden();

        $this->assertSame(ProcurementStatus::Paid, $paid->fresh()->status);
    }

    public function test_type_specific_validation_for_assets_and_rentals(): void
    {
        $this->actingAs($this->site)
            ->post(route('projects.procurement.store', $this->project), [
                'type' => ProcurementType::Asset->value,
                'request_date' => now()->toDateString(),
                'items' => [['item_name' => 'Bor', 'unit' => 'unit', 'quantity' => 1.5, 'unit_price' => 1000]],
            ])
            ->assertSessionHasErrors(['items.0.quantity', 'items.0.asset_category']);

        $this->actingAs($this->site)
            ->post(route('projects.procurement.store', $this->project), [
                'type' => ProcurementType::Rental->value,
                'request_date' => now()->toDateString(),
                'items' => [[
                    'item_name' => 'Scaffolding',
                    'unit' => 'minggu',
                    'quantity' => 1,
                    'unit_price' => 1000,
                    'rental_start' => now()->toDateString(),
                    'rental_end' => now()->subDay()->toDateString(),
                ]],
            ])
            ->assertSessionHasErrors('items.0.rental_end');

        $this->assertSame(0, ProcurementRequest::query()->count());
    }

    public function test_paying_an_asset_request_registers_one_asset_per_unit_and_assigns_it_to_the_project(): void
    {
        $request = $this->makePaidProcurementRequest($this->site, $this->project, ProcurementType::Asset, [
            [...$this->procurementItemPayload(ProcurementType::Asset, 2, 1250000, 'Bor Listrik'), 'brand' => 'Bosch'],
        ]);

        $assets = Asset::query()->orderBy('id')->get();
        $this->assertCount(2, $assets);
        $this->assertSame(['AST-0001', 'AST-0002'], $assets->pluck('code')->all());

        foreach ($assets as $asset) {
            $this->assertSame('Bor Listrik', $asset->name);
            $this->assertSame('Tools', $asset->category);
            $this->assertSame('Bosch', $asset->brand);
            $this->assertSame(AssetSourceType::Purchase, $asset->source_type);
            $this->assertSame(AssetStatus::InUse, $asset->status);
            $this->assertSame($request->items->sole()->id, $asset->procurement_request_item_id);
            $this->assertSame(ProjectAssetStatus::InUse, $asset->projectAssets()->sole()->status);
            $this->assertSame($this->project->id, $asset->projectAssets()->sole()->project_id);
        }

        $actualCost = ProjectActualCost::forProject($this->project->fresh());
        $this->assertSame(0.0, $actualCost->projectActual());

        $this->actingAs($this->site)
            ->get(route('procurement-requests.show', $request))
            ->assertOk()
            ->assertSee('Aset Tercatat (2)')
            ->assertSee('AST-0002');
    }

    public function test_procurement_page_lists_requests_with_type_filter(): void
    {
        $this->makeDraftProcurementRequest($this->site, $this->project, ProcurementType::Bmhp, [
            $this->procurementItemPayload(ProcurementType::Bmhp, name: 'Semen Portland'),
        ]);
        $this->makeDraftProcurementRequest($this->site, $this->project, ProcurementType::Rental, [
            $this->procurementItemPayload(ProcurementType::Rental, name: 'Scaffolding'),
        ]);

        $this->actingAs($this->site)
            ->get(route('projects.procurement.index', $this->project))
            ->assertOk()
            ->assertSee('PR-0001')
            ->assertSee('PR-0002')
            ->assertSee('+ Permintaan Aset');

        $this->actingAs($this->site)
            ->get(route('projects.procurement.index', [$this->project, 'type' => ProcurementType::Rental->value]))
            ->assertOk()
            ->assertDontSee('PR-0001')
            ->assertSee('PR-0002');

        $this->actingAs($this->site)
            ->get(route('projects.procurement.create', [$this->project, 'type' => ProcurementType::Rental->value]))
            ->assertOk()
            ->assertSee('Permintaan Sewa Alat Baru')
            ->assertSee('Mulai Sewa')
            ->assertDontSee('Import CSV');

        $this->actingAs($this->site)
            ->get(route('projects.procurement.create', [$this->project, 'type' => ProcurementType::Bmhp->value]))
            ->assertOk()
            ->assertSee('Download Template CSV')
            ->assertSee('Import CSV');
    }
}
