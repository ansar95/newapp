<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment becomes actual cost: an item can be paid in many plans, plan amounts are free,
     * and every payment is allocated to BOQ items.
     */
    public function up(): void
    {
        Schema::table('payment_plan_items', function (Blueprint $table) {
            $table->decimal('planned_amount', 18, 2)->default(0)->after('boq_item_id');
            $table->index('boq_item_id');
        });

        Schema::table('payment_plan_items', function (Blueprint $table) {
            $table->dropUnique(['boq_item_id']);
            $table->unique(['payment_plan_id', 'boq_item_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->text('overrun_reason')->nullable()->after('notes');
            $table->foreignId('voided_by')->nullable()->after('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('voided_at')->nullable()->after('voided_by');
            $table->text('void_reason')->nullable()->after('voided_at');
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained()->restrictOnDelete();
            $table->decimal('allocated_amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['payment_id', 'boq_item_id']);
            $table->index('boq_item_id');
        });

        $this->backfillLegacyData();
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('voided_by');
            $table->dropColumn(['overrun_reason', 'voided_at', 'void_reason']);
        });

        Schema::table('payment_plan_items', function (Blueprint $table) {
            $table->dropUnique(['payment_plan_id', 'boq_item_id']);
            $table->unique('boq_item_id');
        });

        Schema::table('payment_plan_items', function (Blueprint $table) {
            $table->dropIndex(['boq_item_id']);
            $table->dropColumn('planned_amount');
        });
    }

    /**
     * Existing plans were always worth exactly their BOQ items, so planned_amount = item value.
     * Existing payments are spread over their plan items proportionally to item value.
     */
    private function backfillLegacyData(): void
    {
        $planItems = DB::table('payment_plan_items')
            ->join('boq_items', 'boq_items.id', '=', 'payment_plan_items.boq_item_id')
            ->orderBy('payment_plan_items.id')
            ->get([
                'payment_plan_items.id',
                'payment_plan_items.payment_plan_id',
                'payment_plan_items.boq_item_id',
                'boq_items.total_price',
            ]);

        foreach ($planItems as $planItem) {
            DB::table('payment_plan_items')
                ->where('id', $planItem->id)
                ->update(['planned_amount' => $planItem->total_price]);
        }

        $itemsByPlan = $planItems->groupBy('payment_plan_id');
        $now = now();

        foreach (DB::table('payments')->orderBy('id')->get(['id', 'payment_plan_id', 'amount']) as $payment) {
            $items = $itemsByPlan->get($payment->payment_plan_id, collect())->values();

            if ($items->isEmpty()) {
                continue;
            }

            $paymentCents = (int) round((float) $payment->amount * 100);
            $baseCents = (int) round($items->sum(fn ($item) => (float) $item->total_price) * 100);
            $allocatedCents = 0;

            foreach ($items as $index => $item) {
                $isLast = $index === $items->count() - 1;
                $shareCents = $isLast
                    ? $paymentCents - $allocatedCents
                    : ($baseCents > 0
                        ? (int) floor($paymentCents * ((float) $item->total_price * 100) / $baseCents)
                        : intdiv($paymentCents, $items->count()));
                $allocatedCents += $shareCents;

                if ($shareCents <= 0) {
                    continue;
                }

                DB::table('payment_allocations')->insert([
                    'payment_id' => $payment->id,
                    'boq_item_id' => $item->boq_item_id,
                    'allocated_amount' => number_format($shareCents / 100, 2, '.', ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
