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
        Schema::create('boq_revision_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boq_revision_id')->constrained('boq_revisions')->restrictOnDelete();
            $table->foreignId('boq_item_id')->constrained('boq_items')->restrictOnDelete();
            $table->text('reason');
            $table->string('status')->default('PENDING');
            $table->timestamps();

            $table->unique(['boq_revision_id', 'boq_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boq_revision_items');
    }
};
