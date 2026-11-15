<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            // Klasifikasi dokumen
            $table->string('jenis_dokumen', 50)->default('umum')->after('status_arsip');

            // Kolom profiling / biografi (nullable — hanya relevan utk dokumen khusus)
            $table->string('nama_pemilik', 150)->nullable()->after('jenis_dokumen');
            $table->string('nik_pemilik', 20)->nullable()->after('nama_pemilik');
            $table->text('alamat_objek')->nullable()->after('nik_pemilik');
            $table->string('nomor_alas_hak', 150)->nullable()->after('alamat_objek');
            $table->text('keterangan_profiling')->nullable()->after('nomor_alas_hak');

            // Status pengecekan
            $table->string('status_pengecekan', 50)->default('belum_dicek')->after('keterangan_profiling');
        });

        Schema::create('riwayat_pengecekan_arsip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_arsip_id')->constrained('dokumen_arsip')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status_sebelum', 50)->nullable();
            $table->string('status_sesudah', 50);
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pengecekan_arsip');

        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_dokumen',
                'nama_pemilik',
                'nik_pemilik',
                'alamat_objek',
                'nomor_alas_hak',
                'keterangan_profiling',
                'status_pengecekan',
            ]);
        });
    }
};
