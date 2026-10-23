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
        Schema::create('s_curve_item_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('s_curve_baseline_id');
            $table->unsignedBigInteger('boq_item_id');
            $table->date('planned_start');
            $table->date('planned_finish');
            $table->timestamps();

            $table->unique(['s_curve_baseline_id', 'boq_item_id']);
            $table->foreign('s_curve_baseline_id')->references('id')->on('s_curve_baselines')->cascadeOnDelete();
            $table->foreign('boq_item_id')->references('id')->on('boq_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s_curve_item_schedules');
    }
};
