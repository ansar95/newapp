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
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('reference_type', 50)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('document_types')->insert([
            ['code' => 'DOC-BILLING', 'name' => 'Dokumen Penagihan', 'reference_type' => 'BILLING', 'description' => 'Dokumen penagihan termin ke client.', 'is_active' => true, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DOC-CONTRACT', 'name' => 'Kontrak', 'reference_type' => null, 'description' => 'Dokumen kontrak dan addendum.', 'is_active' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DOC-BA', 'name' => 'Berita Acara', 'reference_type' => null, 'description' => 'Berita acara progress, serah terima, dan sejenisnya.', 'is_active' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DOC-GENERAL', 'name' => 'Dokumen Umum', 'reference_type' => null, 'description' => 'Dokumen administrasi project lainnya.', 'is_active' => false, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_types');
    }
};
