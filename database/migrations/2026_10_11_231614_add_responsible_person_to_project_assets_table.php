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
        Schema::table('project_assets', function (Blueprint $table) {
            $table->string('responsible_name')->nullable()->after('notes');
            $table->string('responsible_position')->nullable()->after('responsible_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_assets', function (Blueprint $table) {
            $table->dropColumn(['responsible_name', 'responsible_position']);
        });
    }
};
