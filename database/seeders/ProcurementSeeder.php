<?php

namespace Database\Seeders;

use App\Enums\ProcurementStatus;
use App\Enums\ProcurementType;
use App\Models\BoqItem;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class ProcurementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::query()->first();

        $project = Project::query()->where('code', 'PRJ-005')->first();

        if ($admin === null || $project === null) {
            return;
        }

        $items = BoqItem::query()
            ->whereHas('cogs.boq', fn ($query) => $query->where('project_id', $project->id))
            ->get()
            ->keyBy('code');

        $this->seedRequest($project, $admin, 'PR-0001', ProcurementType::Bmhp, ProcurementStatus::Paid, 'PT ABC Material', now()->subDays(10)->toDateString(), [
            [['item_name' => 'Gypsum 9mm', 'unit' => 'Lembar', 'quantity' => 100, 'unit_price' => 250000], [['ITEM-LV-E01', 15000000], ['ITEM-LV-E02', 10000000]]],
            [['item_name' => 'Hollow 4x4', 'unit' => 'Batang', 'quantity' => 200, 'unit_price' => 50000], [['ITEM-LV-E01', 10000000]]],
            [['item_name' => 'Screw Gypsum', 'unit' => 'Box', 'quantity' => 50, 'unit_price' => 50000], [['ITEM-LV-E03', 2000000]]],
        ], $items);

        $this->seedRequest($project, $admin, 'PR-0002', ProcurementType::Bmhp, ProcurementStatus::Draft, 'CV Sumber Jaya', now()->subDay()->toDateString(), [
            [['item_name' => 'Compound Gypsum', 'unit' => 'Sak', 'quantity' => 70, 'unit_price' => 50000], []],
        ], $items);

        $this->seedRequest($project, $admin, 'PR-0003', ProcurementType::Rental, ProcurementStatus::Paid, 'CV Sewa Alat Jaya', now()->subDays(7)->toDateString(), [
            [[
                'item_name' => 'Scaffolding Set',
                'unit' => 'minggu',
                'quantity' => 2,
                'unit_price' => 1500000,
                'rental_start' => now()->subDays(7)->toDateString(),
                'rental_end' => now()->addDays(7)->toDateString(),
            ], [['ITEM-LV-E01', 3000000]]],
        ], $items);

        $this->seedRequest($project, $admin, 'PR-0004', ProcurementType::Asset, ProcurementStatus::Submitted, 'PT Teknik Perkakas', now()->subDays(2)->toDateString(), [
            [['item_name' => 'Bor Listrik', 'unit' => 'unit', 'quantity' => 2, 'unit_price' => 1250000, 'asset_category' => 'Tools', 'brand' => 'Bosch'], []],
        ], $items);
    }

    /**
     * @param  list<array{0: array<string, mixed>, 1: list<array{0: string, 1: int}>}>  $lines  [item attributes, allocations as [boq item code, amount]]
     * @param  Collection<string, BoqItem>  $boqItems
     */
    private function seedRequest(
        Project $project,
        User $admin,
        string $number,
        ProcurementType $type,
        ProcurementStatus $status,
        string $supplier,
        string $date,
        array $lines,
        Collection $boqItems,
    ): ProcurementRequest {
        $isPaid = $status === ProcurementStatus::Paid;
        $isSubmitted = in_array($status, [ProcurementStatus::Submitted, ProcurementStatus::Approved, ProcurementStatus::Paid], true);

        $request = ProcurementRequest::query()->updateOrCreate(
            ['request_number' => $number],
            [
                'project_id' => $project->id,
                'type' => $type,
                'request_date' => $date,
                'supplier' => $supplier,
                'status' => $status,
                'created_by' => $admin->id,
                'submitted_by' => $isSubmitted ? $admin->id : null,
                'submitted_at' => $isSubmitted ? now()->subDays(9) : null,
                'approved_by' => $isPaid ? $admin->id : null,
                'approved_at' => $isPaid ? now()->subDays(8) : null,
                'paid_by' => $isPaid ? $admin->id : null,
                'paid_at' => $isPaid ? now()->subDays(8) : null,
                'payment_date' => $isPaid ? $date : null,
                'invoice_number' => $isPaid ? 'INV-'.$number : null,
            ]
        );

        foreach ($request->items as $existingItem) {
            $existingItem->allocations()->delete();
            $existingItem->delete();
        }

        foreach ($lines as [$attributes, $allocations]) {
            $item = $request->items()->create([
                ...$attributes,
                'paid_unit_price' => $isPaid ? $attributes['unit_price'] : null,
            ]);

            foreach ($allocations as [$itemCode, $amount]) {
                $boqItem = $boqItems->get($itemCode);

                if ($boqItem === null) {
                    continue;
                }

                $item->allocations()->create([
                    'boq_item_id' => $boqItem->id,
                    'allocated_amount' => $amount,
                ]);
            }
        }

        $request->recalculateEstimatedTotal();

        if ($isPaid) {
            $request->update(['paid_total' => $request->items()->sum('paid_total')]);
        }

        return $request->refresh();
    }
}
