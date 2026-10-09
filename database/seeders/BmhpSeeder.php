<?php

namespace Database\Seeders;

use App\Enums\BmhpPurchaseStatus;
use App\Enums\MasterBmhpStatus;
use App\Models\BmhpPurchase;
use App\Models\BoqItem;
use App\Models\MasterBmhp;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BmhpSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'superadmin@example.com')->first()
            ?? User::query()->first();

        if ($admin === null) {
            return;
        }

        $masters = collect([
            ['BMHP-0001', 'Semen Portland', 'Zak', 'Material'],
            ['BMHP-0002', 'Gypsum 9mm', 'Lembar', 'Material'],
            ['BMHP-0003', 'Hollow 4x4', 'Batang', 'Material'],
            ['BMHP-0004', 'Screw Gypsum', 'Box', 'Consumable'],
            ['BMHP-0005', 'Compound Gypsum', 'Sak', 'Consumable'],
            ['BMHP-0006', 'Kabel Ties', 'Pack', 'Consumable'],
        ])->mapWithKeys(fn (array $row) => [$row[0] => MasterBmhp::query()->updateOrCreate(
            ['code' => $row[0]],
            [
                'name' => $row[1],
                'unit' => $row[2],
                'category' => $row[3],
                'status' => MasterBmhpStatus::Active,
                'created_by' => $admin->id,
            ]
        )]);

        $project = Project::query()->where('code', 'PRJ-005')->first();

        if ($project === null) {
            return;
        }

        $items = BoqItem::query()
            ->whereHas('cogs.boq', fn ($query) => $query->where('project_id', $project->id))
            ->get()
            ->keyBy('code');

        $confirmed = $this->seedPurchase($project, $admin, 'PB-0001', BmhpPurchaseStatus::Confirmed, 'PT ABC Material', now()->subDays(10)->toDateString(), [
            [$masters['BMHP-0002'], 100, 250000, [['ITEM-LV-E01', 15000000], ['ITEM-LV-E02', 10000000]]],
            [$masters['BMHP-0003'], 200, 50000, [['ITEM-LV-E01', 10000000]]],
            [$masters['BMHP-0004'], 50, 50000, [['ITEM-LV-E03', 2000000]]],
        ], $items);

        $confirmed->update([
            'submitted_by' => $admin->id,
            'submitted_at' => now()->subDays(9),
            'confirmed_by' => $admin->id,
            'confirmed_at' => now()->subDays(8),
        ]);

        $this->seedPurchase($project, $admin, 'PB-0002', BmhpPurchaseStatus::Draft, 'CV Sumber Jaya', now()->subDay()->toDateString(), [
            [$masters['BMHP-0005'], 70, 50000, [['ITEM-LV-E01', 3500000]]],
        ], $items);
    }

    /**
     * @param  list<array{0: MasterBmhp, 1: int, 2: int, 3: list<array{0: string, 1: int}>}>  $lines
     * @param  Collection<string, BoqItem>  $items
     */
    private function seedPurchase(
        Project $project,
        User $admin,
        string $number,
        BmhpPurchaseStatus $status,
        string $supplier,
        string $date,
        array $lines,
        $items,
    ): BmhpPurchase {
        $purchase = BmhpPurchase::query()->updateOrCreate(
            ['purchase_number' => $number],
            [
                'project_id' => $project->id,
                'purchase_date' => $date,
                'supplier' => $supplier,
                'invoice_number' => 'INV-'.$number,
                'status' => $status,
                'created_by' => $admin->id,
            ]
        );

        foreach ($purchase->details as $existingDetail) {
            $existingDetail->allocations()->delete();
            $existingDetail->delete();
        }

        foreach ($lines as [$master, $quantity, $unitPrice, $allocations]) {
            $detail = $purchase->details()->create([
                'master_bmhp_id' => $master->id,
                'item_name' => $master->name,
                'unit' => $master->unit,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ]);

            foreach ($allocations as [$itemCode, $amount]) {
                $item = $items->get($itemCode);

                if ($item === null) {
                    continue;
                }

                $detail->allocations()->create([
                    'boq_item_id' => $item->id,
                    'allocated_amount' => $amount,
                ]);
            }
        }

        $purchase->recalculateTotal();

        return $purchase->refresh();
    }
}
