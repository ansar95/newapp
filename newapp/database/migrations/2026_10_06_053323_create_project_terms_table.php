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
        Schema::create('project_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('term_number');
            $table->string('term_name');
            $table->string('calculation_type', 20);
            $table->decimal('percentage', 7, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('trigger_type', 20)->default('MANUAL');
            $table->string('trigger_value')->nullable();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('readiness', 20)->default('NOT_READY');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'term_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_terms');
    }
};
