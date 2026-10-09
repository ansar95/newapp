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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bmhp_purchase_details');
    }
};
