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
        Schema::create('project_document_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_document_id')->constrained('project_documents')->restrictOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->unsignedInteger('version_number')->nullable();
            $table->text('notes')->nullable();
            $table->text('revision_reason')->nullable();
            $table->string('actor_type', 20)->default('INTERNAL');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['project_document_id', 'changed_at'], 'doc_status_histories_document_changed_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_document_status_histories');
    }
};
