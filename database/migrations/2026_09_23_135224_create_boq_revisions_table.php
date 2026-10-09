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
        Schema::create('boq_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_id')->constrained('boqs')->restrictOnDelete();
            $table->string('revision_code');
            $table->unsignedInteger('revision_number');
            $table->text('reason');
            $table->string('status')->default('OPEN');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique('revision_code');
            $table->unique(['boq_id', 'revision_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_revisions');
    }
};
