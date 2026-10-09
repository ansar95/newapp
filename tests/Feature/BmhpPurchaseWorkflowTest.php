<?php

namespace Tests\Feature;

use App\Actions\Bmhp\CancelBmhpPurchase;
use App\Actions\Bmhp\CreateBmhpPurchase;
use App\Actions\Bmhp\ReturnBmhpPurchaseToDraft;
use App\Actions\Bmhp\SaveBmhpPurchaseDetail;
use App\Actions\Bmhp\SubmitBmhpPurchase;
use App\Actions\Bmhp\UpdateBmhpPurchase;
use App\Enums\BmhpPurchaseStatus;
use App\Enums\MasterBmhpStatus;
use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\MasterBmhp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsWorkflowFixtures;
use Tests\TestCase;

class BmhpPurchaseWorkflowTest extends TestCase
{
    use BuildsWorkflowFixtures;
    use RefreshDatabase;

    public function test_master_bmhp_can_be_created_with_auto_generated_code(): void
    {
        $admin = $this->makeProjectAdmin();

        $this->actingAs($admin)
            ->post(route('master-bmhps.store'), [
                'name' => 'Semen Portland',
                'category' => 'Material',
                'unit' => 'Zak',
                'status' => MasterBmhpStatus::Active->value,
            ])
            ->assertRedirect(route('master-bmhps.index'));

        $master = MasterBmhp::query()->sole();

        $this->assertSame('BMHP-0001', $master->code);
        $this->assertSame('Semen Portland', $master->name);
        $this->assertSame(MasterBmhpStatus::Active, $master->status);
        $this->assertSame($admin->id, $master->created_by);
    }

    public function test_used_master_bmhp_is_deactivated_instead_of_deleted(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $master = $this->makeMasterBmhp($admin);
        $this->makeDraftBmhpPurchase($admin, $project, [[$master, 1, 1000]]);

        $this->actingAs($admin)
            ->delete(route('master-bmhps.destroy', $master))
            ->assertRedirect();

        $this->assertSame(MasterBmhpStatus::Inactive, $master->fresh()->status);
        $this->assertNotSoftDeleted($master);
    }

    public function test_purchase_can_be_created_as_draft_with_sequential_number(): void
    {
        $actor = $this->makeProjectSite();
        $project = $this->makeActiveProject($this->makeSuperadmin());

        $this->actingAs($actor)
            ->post(route('projects.bmhp.store', $project), [
                'purchase_date' => now()->toDateString(),
                'supplier' => 'PT ABC Material',
                'invoice_number' => 'INV-001',
            ])
            ->assertRedirect();

        $purchase = $project->bmhpPurchases()->sole();

        $this->assertSame('PB-0001', $purchase->purchase_number);
        $this->assertSame(BmhpPurchaseStatus::Draft, $purchase->status);
        $this->assertSame('0.00', $purchase->total_amount);
        $this->assertSame($actor->id, $purchase->created_by);
    }

    public function test_purchase_cannot_be_created_for_non_active_project(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $project->update(['status' => ProjectStatus::Completed]);

        $this->expectException(BusinessRuleException::class);

        app(CreateBmhpPurchase::class)->handle($admin, $project->fresh(), [
            'purchase_date' => now()->toDateString(),
            'supplier' => 'PT ABC Material',
        ]);
    }

    public function test_purchase_details_store_snapshot_and_detail_total(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $master = $this->makeMasterBmhp($admin, ['name' => 'Gypsum 9mm', 'unit' => 'Lembar']);

        $purchase = $this->makeDraftBmhpPurchase($admin, $project, [[$master, 100, 250000]]);
        $detail = $purchase->details->sole();

        $this->assertSame('Gypsum 9mm', $detail->item_name);
        $this->assertSame('Lembar', $detail->unit);
        $this->assertSame('100.0000', $detail->quantity);
        $this->assertSame('25000000.00', $detail->total);

        $master->update(['name' => 'Gypsum Renamed']);
        $this->assertSame('Gypsum 9mm', $detail->fresh()->item_name);
    }

    public function test_purchase_total_is_sum_of_detail_totals(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $gypsum = $this->makeMasterBmhp($admin);
        $hollow = $this->makeMasterBmhp($admin, ['name' => 'Hollow 4x4', 'unit' => 'Batang']);
        $screw = $this->makeMasterBmhp($admin, ['name' => 'Screw', 'category' => 'Consumable', 'unit' => 'Box']);

        $purchase = $this->makeDraftBmhpPurchase($admin, $project, [
            [$gypsum, 100, 250000],
            [$hollow, 200, 50000],
            [$screw, 50, 50000],
        ]);

        $this->assertSame('37500000.00', $purchase->total_amount);
    }

    public function test_inactive_master_bmhp_cannot_be_used_on_new_detail(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $master = $this->makeMasterBmhp($admin, ['status' => MasterBmhpStatus::Inactive->value]);
        $purchase = $this->makeDraftBmhpPurchase($admin, $project);

        $this->expectException(BusinessRuleException::class);

        app(SaveBmhpPurchaseDetail::class)->handle($admin, $purchase, [
            'master_bmhp_id' => $master->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);
    }

    public function test_detail_quantity_must_be_greater_than_zero(): void
    {
        $admin = $this->makeSuperadmin();
        $project = $this->makeActiveProject($admin);
        $master = $this->makeMasterBmhp($admin);
        $purchase = $this->makeDraftBmhpPurchase($admin, $project);

        $this->expectException(ValidationException::class);

        app(SaveBmhpPurchaseDetail::class)->handle($admin, $purchase, [
            'master_bmhp_id' => $master->id,
            'quantity' => 0,
            'unit_price' => 1000,
        ]);
    }

    public function test_purchase_can_be_submitted_and_confirmed(): void
    {
        $admin = $this->makeSuperadmin();
        $site = $this->makeProjectSite();
        $finance = $this->makeFinance();
        $project = $this->makeActiveProject($admin);
        $purchase = $this->makeDraftBmhpPurchase($admin, $project, [[$this->makeMasterBmhp($admin), 10, 1000]]);

        $this->actingAs($site)
            ->post(route('bmhp-purchases.submit', $purchase))
            ->assertRedirect();

        $purchase->refresh();
        $this->assertSame(BmhpPurchaseStatus::Submitted, $purchase->status);
        $this->assertSame($site->id, $purchase->submitted_by);
        $this->assertNotNull($purchase->submitted_at);

        $this->actingAs($finance)
            ->post(route('bmhp-purchases.confirm', $purchase))
            ->assertRedirect();

        $purchase->refresh();
        $this->assertSame(BmhpPurchaseStatus::Confirmed, $purchase->status);
        $this->assertSame($finance->id, $purchase->confirmed_by);
        $this->assertNotNull($purchase->confirmed_at);
    }

    public function test_purchase_without_details_cannot_be_submitted(): void
    {
        $admin = $this->makeSuperadmin();
        $purchase = $this->makeDraftBmhpPurchase($admin, $this->makeActiveProject($admin));

        $this->expectException(BusinessRuleException::class);

        app(SubmitBmhpPurchase::class)->handle($admin, $purchase);
    }

    public function test_submitted_purchase_can_be_returned_to_draft(): void
    {
        $admin = $this->makeSuperadmin();
        $purchase = $this->makeDraftBmhpPurchase($admin, $this->makeActiveProject($admin), [[$this->makeMasterBmhp($admin), 1, 1000]]);
        app(SubmitBmhpPurchase::class)->handle($admin, $purchase);

        $returned = app(ReturnBmhpPurchaseToDraft::class)->handle($admin, $purchase->fresh());

        $this->assertSame(BmhpPurchaseStatus::Draft, $returned->status);
    }

    public function test_confirmed_purchase_details_cannot_be_edited(): void
    {
        $admin = $this->makeSuperadmin();
        $master = $this->makeMasterBmhp($admin);
        $purchase = $this->makeDraftBmhpPurchase($admin, $this->makeActiveProject($admin), [[$master, 10, 1000]]);
        $purchase = $this->submitAndConfirmBmhpPurchase($admin, $purchase);
        $detail = $purchase->details()->sole();

        $this->actingAs($admin)
            ->patch(route('bmhp-purchase-details.update', $detail), [
                'master_bmhp_id' => $master->id,
                'quantity' => 999,
                'unit_price' => 1000,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('bmhp-purchase-details.destroy', $detail))
            ->assertForbidden();

        $this->actingAs($admin)
            ->post(route('bmhp-purchases.details.store', $purchase), [
                'master_bmhp_id' => $master->id,
                'quantity' => 1,
                'unit_price' => 1,
            ])
            ->assertForbidden();

        $this->assertSame('10.0000', $detail->fresh()->quantity);
        $this->assertSame('10000.00', $purchase->fresh()->total_amount);

        $this->expectException(BusinessRuleException::class);

        app(SaveBmhpPurchaseDetail::class)->handle($admin, $purchase, [
            'master_bmhp_id' => $master->id,
            'quantity' => 999,
            'unit_price' => 1000,
        ], $detail);
    }

    public function test_confirmed_purchase_header_cannot_be_edited(): void
    {
        $admin = $this->makeSuperadmin();
        $purchase = $this->makeDraftBmhpPurchase($admin, $this->makeActiveProject($admin), [[$this->makeMasterBmhp($admin), 1, 1000]]);
        $purchase = $this->submitAndConfirmBmhpPurchase($admin, $purchase);

        $this->actingAs($admin)
            ->get(route('bmhp-purchases.edit', $purchase))
            ->assertForbidden();

        $this->expectException(BusinessRuleException::class);

        app(UpdateBmhpPurchase::class)->handle($admin, $purchase, [
            'purchase_date' => now()->toDateString(),
            'supplier' => 'Changed Supplier',
        ]);
    }

    public function test_cancel_requires_reason_and_keeps_audit_trail(): void
    {
        $admin = $this->makeSuperadmin();
        $purchase = $this->makeDraftBmhpPurchase($admin, $this->makeActiveProject($admin), [[$this->makeMasterBmhp($admin), 1, 1000]]);
        $purchase = $this->submitAndConfirmBmhpPurchase($admin, $purchase);

        $this->actingAs($admin)
            ->post(route('bmhp-purchases.cancel', $purchase), ['cancel_reason' => ''])
            ->assertSessionHasErrors('cancel_reason');

        $this->assertSame(BmhpPurchaseStatus::Confirmed, $purchase->fresh()->status);

        $cancelled = app(CancelBmhpPurchase::class)->handle($admin, $purchase->fresh(), ['cancel_reason' => 'Salah input supplier']);

        $this->assertSame(BmhpPurchaseStatus::Cancelled, $cancelled->status);
        $this->assertSame('Salah input supplier', $cancelled->cancel_reason);
        $this->assertSame($admin->id, $cancelled->cancelled_by);
        $this->assertSame(1, $cancelled->details()->count());
    }
}
