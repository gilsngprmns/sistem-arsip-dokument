<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Urutan ketat:
     * 1. Buat tabel boks_arsip (FK rak_id → rak_arsip, CASCADE)
     * 2. Tambah boks_id ke dokumen_arsip (nullable, FK boks_arsip, nullOnDelete)
     * 3. Migrate data: salin nomor_box→boks_arsip.nomor_boks, nomor_slot→nomor_baris
     * 4. Hapus kolom nomor_box, nomor_map, nomor_slot dari dokumen_arsip
     * 5. Hapus FK + kolom lokasi_id dari rak_arsip
     * 6. Hapus tabel lokasi_arsip
     */
    public function up(): void
    {
        // 1. Tabel boks_arsip
        Schema::create('boks_arsip', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rak_id')
                ->constrained('rak_arsip')
                ->cascadeOnDelete();
            $table->string('kode_boks', 50)->unique();
            $table->string('nomor_baris', 20)->nullable();
            $table->string('nomor_boks', 50)->nullable();
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Kolom boks_id di dokumen_arsip
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->foreignId('boks_id')
                ->nullable()
                ->after('rak_id')
                ->constrained('boks_arsip')
                ->nullOnDelete();
        });

        // 3. Data migration: buat boks per rak yang punya dokumen aktif
        $rakIdSet = DB::table('dokumen_arsip')
            ->whereNotNull('rak_id')
            ->select('rak_id', 'nomor_box', 'nomor_slot')
            ->distinct()
            ->get();

        foreach ($rakIdSet as $row) {
            $rak = DB::table('rak_arsip')->where('id', $row->rak_id)->first();
            if (! $rak) {
                continue;
            }

            $kodeBoks = 'BOK-' . strtoupper($rak->kode_rak);
            $boks = DB::table('boks_arsip')
                ->where('kode_boks', $kodeBoks)
                ->first();

            if (! $boks) {
                DB::table('boks_arsip')->insert([
                    'rak_id'        => $row->rak_id,
                    'kode_boks'     => $kodeBoks,
                    'nomor_baris'   => $row->nomor_slot,
                    'nomor_boks'    => $row->nomor_box,
                    'keterangan'    => null,
                    'is_active'     => true,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $boks = DB::table('boks_arsip')->where('kode_boks', $kodeBoks)->first();
            }

            if ($boks) {
                DB::table('dokumen_arsip')
                    ->where('rak_id', $row->rak_id)
                    ->update(['boks_id' => $boks->id]);
            }
        }

        // 4. Hapus kolom lama dari dokumen_arsip
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->dropColumn(['nomor_box', 'nomor_map', 'nomor_slot']);
        });

        // 5. Hapus lokasi_id dari rak_arsip
        Schema::table('rak_arsip', function (Blueprint $table) {
            $table->dropForeign(['lokasi_id']);
            $table->dropColumn('lokasi_id');
        });

        // 6. Hapus tabel lokasi_arsip
        Schema::dropIfExists('lokasi_arsip');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 6. Buat ulang lokasi_arsip
        Schema::create('lokasi_arsip', function (Blueprint $table) {
            $table->id();
            $table->string('kode_lokasi', 50)->unique();
            $table->string('nama_lokasi', 150);
            $table->string('gedung', 150)->nullable();
            $table->string('lantai', 50)->nullable();
            $table->string('ruangan', 100)->nullable();
            $table->text('deskripsi')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 5. Kembalikan lokasi_id ke rak_arsip
        Schema::table('rak_arsip', function (Blueprint $table) {
            $table->foreignId('lokasi_id')
                ->nullable()
                ->constrained('lokasi_arsip')
                ->nullOnDelete()
                ->after('id');
        });

        // 4. Kembalikan kolom lama ke dokumen_arsip
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->string('nomor_box', 50)->nullable()->after('rak_id');
            $table->string('nomor_map', 50)->nullable()->after('nomor_box');
            $table->string('nomor_slot', 50)->nullable()->after('nomor_map');
        });

        // 3. Hapus boks_id dari dokumen_arsip
        Schema::table('dokumen_arsip', function (Blueprint $table) {
            $table->dropForeign(['boks_id']);
            $table->dropColumn('boks_id');
        });

        // 1. Hapus tabel boks_arsip
        Schema::dropIfExists('boks_arsip');
    }
};
