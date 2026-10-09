<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bmhp_allocations');
    }
};
