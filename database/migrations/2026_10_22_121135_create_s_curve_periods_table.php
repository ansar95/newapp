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
        Schema::create('s_curve_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('s_curve_baseline_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('planned_percent', 8, 4)->default(0);
            $table->timestamps();

            $table->unique(['s_curve_baseline_id', 'period_start']);
            $table->foreign('s_curve_baseline_id')->references('id')->on('s_curve_baselines')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('s_curve_periods');
    }
};
