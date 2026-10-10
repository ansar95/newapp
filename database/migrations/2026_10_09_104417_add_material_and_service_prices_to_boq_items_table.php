<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing single prices become material prices so item totals stay unchanged.
     */
    public function up(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->decimal('material_unit_price', 18, 2)->default(0)->after('unit');
            $table->decimal('service_unit_price', 18, 2)->default(0)->after('material_unit_price');
        });

        DB::table('boq_items')->update([
            'material_unit_price' => DB::raw('unit_price'),
            'service_unit_price' => 0,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boq_items', function (Blueprint $table) {
            $table->dropColumn(['material_unit_price', 'service_unit_price']);
        });
    }
};
