<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('request_number', 50)->unique();
            $table->string('type', 20);
            $table->date('request_date');
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('estimated_total', 18, 2)->default(0);
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('returned_at')->nullable();
            $table->text('return_note')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('paid_total', 18, 2)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('payment_proof_file')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'type', 'status']);
        });

        Schema::create('procurement_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained('procurement_requests')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('unit', 50);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total', 18, 2);
            $table->decimal('paid_unit_price', 18, 2)->nullable();
            $table->decimal('paid_total', 18, 2)->nullable();
            $table->string('asset_category', 100)->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->date('rental_start')->nullable();
            $table->date('rental_end')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_item_id')->constrained('procurement_request_items')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items')->restrictOnDelete();
            $table->decimal('allocated_quantity', 18, 4)->nullable();
            $table->decimal('allocated_amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['procurement_request_item_id', 'boq_item_id'], 'procurement_allocations_item_boq_item_unique');
            $table->index('boq_item_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->foreignId('procurement_request_item_id')->nullable()->after('created_by')
                ->constrained('procurement_request_items')->nullOnDelete();
        });

        $this->migrateBmhpPurchases();

        Schema::dropIfExists('bmhp_allocations');
        Schema::dropIfExists('bmhp_purchase_details');
        Schema::dropIfExists('bmhp_purchases');
        Schema::dropIfExists('master_bmhps');
    }

    /**
     * Old BMHP purchases become BMHP requests. CONFIRMED purchases were already actual cost, so they become PAID and keep their allocations.
     */
    private function migrateBmhpPurchases(): void
    {
        $statusMap = [
            'DRAFT' => 'DRAFT',
            'SUBMITTED' => 'SUBMITTED',
            'CONFIRMED' => 'PAID',
            'CANCELLED' => 'CANCELLED',
        ];

        foreach (DB::table('bmhp_purchases')->orderBy('id')->get() as $purchase) {
            $isPaid = $purchase->status === 'CONFIRMED';

            $requestId = DB::table('procurement_requests')->insertGetId([
                'project_id' => $purchase->project_id,
                'request_number' => $purchase->purchase_number,
                'type' => 'BMHP',
                'request_date' => $purchase->purchase_date,
                'supplier' => $purchase->supplier,
                'notes' => $purchase->notes,
                'estimated_total' => $purchase->total_amount,
                'status' => $statusMap[$purchase->status] ?? 'DRAFT',
                'created_by' => $purchase->created_by,
                'submitted_by' => $purchase->submitted_by,
                'submitted_at' => $purchase->submitted_at,
                'approved_by' => $isPaid ? $purchase->confirmed_by : null,
                'approved_at' => $isPaid ? $purchase->confirmed_at : null,
                'paid_by' => $isPaid ? $purchase->confirmed_by : null,
                'paid_at' => $isPaid ? $purchase->confirmed_at : null,
                'payment_date' => $isPaid ? $purchase->purchase_date : null,
                'paid_total' => $isPaid ? $purchase->total_amount : null,
                'invoice_number' => $purchase->invoice_number,
                'payment_proof_file' => $purchase->receipt_file,
                'cancelled_by' => $purchase->cancelled_by,
                'cancelled_at' => $purchase->cancelled_at,
                'cancel_reason' => $purchase->cancel_reason,
                'created_at' => $purchase->created_at,
                'updated_at' => $purchase->updated_at,
            ]);

            foreach (DB::table('bmhp_purchase_details')->where('bmhp_purchase_id', $purchase->id)->orderBy('id')->get() as $detail) {
                $itemId = DB::table('procurement_request_items')->insertGetId([
                    'procurement_request_id' => $requestId,
                    'item_name' => $detail->item_name,
                    'unit' => $detail->unit,
                    'quantity' => $detail->quantity,
                    'unit_price' => $detail->unit_price,
                    'total' => $detail->total,
                    'paid_unit_price' => $isPaid ? $detail->unit_price : null,
                    'paid_total' => $isPaid ? $detail->total : null,
                    'notes' => $detail->notes,
                    'created_at' => $detail->created_at,
                    'updated_at' => $detail->updated_at,
                ]);

                if (! $isPaid) {
                    continue;
                }

                foreach (DB::table('bmhp_allocations')->where('bmhp_purchase_detail_id', $detail->id)->orderBy('id')->get() as $allocation) {
                    DB::table('procurement_allocations')->insert([
                        'procurement_request_item_id' => $itemId,
                        'boq_item_id' => $allocation->boq_item_id,
                        'allocated_quantity' => $allocation->allocated_quantity,
                        'allocated_amount' => $allocation->allocated_amount,
                        'notes' => $allocation->notes,
                        'created_at' => $allocation->created_at,
                        'updated_at' => $allocation->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations. The old BMHP tables are recreated empty; procurement data is not converted back.
     */
    public function down(): void
    {
        Schema::create('master_bmhps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 100);
            $table->string('unit', 50);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('ACTIVE');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category']);
        });

        Schema::create('bmhp_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->string('purchase_number', 50)->unique();
            $table->date('purchase_date');
            $table->string('supplier');
            $table->string('invoice_number', 100)->nullable();
            $table->string('receipt_file')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->string('status', 20)->default('DRAFT');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('bmhp_purchase_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bmhp_purchase_id')->constrained('bmhp_purchases')->cascadeOnDelete();
            $table->foreignId('master_bmhp_id')->constrained('master_bmhps')->restrictOnDelete();
            $table->string('item_name');
            $table->string('unit', 50);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('total', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bmhp_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bmhp_purchase_detail_id')->constrained('bmhp_purchase_details')->cascadeOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items')->restrictOnDelete();
            $table->decimal('allocated_quantity', 18, 4)->nullable();
            $table->decimal('allocated_amount', 18, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bmhp_purchase_detail_id', 'boq_item_id']);
            $table->index('boq_item_id');
        });

        Schema::table('assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_request_item_id');
        });

        Schema::dropIfExists('procurement_allocations');
        Schema::dropIfExists('procurement_request_items');
        Schema::dropIfExists('procurement_requests');
    }
};
