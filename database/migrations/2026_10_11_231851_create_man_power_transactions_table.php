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
        Schema::create('man_power_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_man_power_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->date('transaction_date');
            $table->decimal('amount', 18, 2);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('PAID');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['project_man_power_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('man_power_transactions');
    }
};
