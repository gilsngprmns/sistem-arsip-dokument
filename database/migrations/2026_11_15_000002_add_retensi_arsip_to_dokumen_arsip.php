<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Klasifikasi masa aktif & retensi arsip — Tahap 6.
     */
    public function up(): void
    {
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->string('retensi_kategori', 30)->default('ringan_5_tahun')->after('file_hash');
            $table->unsignedInteger('retensi_tahun')->nullable()->after('retensi_kategori');
            $table->date('tanggal_kedaluwarsa')->nullable()->after('retensi_tahun');
            $table->boolean('retensi_dipertahankan')->default(false)->after('tanggal_kedaluwarsa');
            $table->timestamp('retensi_ditindak_waktu')->nullable()->after('retensi_dipertahankan');
            $table->foreignId('retensi_ditindak_oleh')->nullable()->after('retensi_ditindak_waktu')->constrained('users')->nullOnDelete();
            $table->text('catatan_retensi')->nullable()->after('retensi_ditindak_oleh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->dropForeign(['retensi_ditindak_oleh']);
            $table->dropColumn([
                'retensi_kategori',
                'retensi_tahun',
                'tanggal_kedaluwarsa',
                'retensi_dipertahankan',
                'retensi_ditindak_waktu',
                'retensi_ditindak_oleh',
                'catatan_retensi',
            ]);
        });
    }
};
