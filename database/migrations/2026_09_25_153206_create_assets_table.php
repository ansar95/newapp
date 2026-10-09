<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('category', 100);
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('unit', 50)->default('Unit');
            $table->string('source_type', 20);
            $table->string('status', 20)->default('AVAILABLE');
            $table->text('description')->nullable();

            $table->string('request_number', 100)->nullable();
            $table->date('request_date')->nullable();
            $table->string('request_letter_file')->nullable();
            $table->text('request_description')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('request_status', 20)->nullable();

            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'source_type']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
