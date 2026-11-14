<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kapasitas muatan dokumen dipindah dari rak ke boks:
     * rak = penanda lokasi teks, boks = satuan muatan fisik.
     */
    public function up(): void
    {
        Schema::table('boks_arsip', function (Blueprint $table) {
            // Nullable: kapasitas opsional untuk boks yang tidak memerlukan
            // batasan muatan (misalnya boks referensi atau test).
            // Form tetap memaksa pengisian bila diperlukan via validasi.
            $table->integer('kapasitas')->nullable()->after('kode_boks');
        });

        Schema::table('rak_arsip', function (Blueprint $table) {
            $table->dropColumn('kapasitas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rak_arsip', function (Blueprint $table) {
            $table->integer('kapasitas')->nullable()->after('nomor_rak');
        });

        Schema::table('boks_arsip', function (Blueprint $table) {
            $table->dropColumn('kapasitas');
        });
    }
};
