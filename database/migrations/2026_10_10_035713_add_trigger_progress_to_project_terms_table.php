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
        Schema::table('project_terms', function (Blueprint $table) {
            $table->decimal('trigger_progress', 5, 2)->nullable()->after('trigger_value');
        });

        DB::table('project_terms')
            ->where('trigger_type', 'PROGRESS')
            ->whereNotNull('trigger_value')
            ->orderBy('id')
            ->get(['id', 'trigger_value'])
            ->each(function (object $term): void {
                if (! preg_match('/(\d+(?:[.,]\d+)?)\s*%?/', (string) $term->trigger_value, $matches)) {
                    return;
                }

                $progress = (float) str_replace(',', '.', $matches[1]);

                if ($progress > 0 && $progress <= 100) {
                    DB::table('project_terms')->where('id', $term->id)->update(['trigger_progress' => $progress]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_terms', function (Blueprint $table) {
            $table->dropColumn('trigger_progress');
        });
    }
};
