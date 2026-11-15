<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seksi', function (Blueprint $table) {
            $table->string('kode_seksi', 10)->unique()->nullable()->after('id');
        });

        // Backfill 3 seksi seeder sesuai pola yang sudah hidup di RakArsipSeeder.
        $backfill = [
            'Pemerintahan'         => 'PEM',
            'Ekonomi Pembangunan'  => 'EKB',
            'Kesejahteraan Rakyat' => 'KSR',
        ];

        foreach ($backfill as $nama => $kode) {
            DB::table('seksi')
                ->where('nama_seksi', $nama)
                ->whereNull('kode_seksi')
                ->update(['kode_seksi' => $kode]);
        }

        // Seksi yang masih belum punya kode: turunkan dari inisial nama
        $nullRows = DB::table('seksi')->whereNull('kode_seksi')->get();
        foreach ($nullRows as $row) {
            $parts = preg_split('/\s+/', $row->nama_seksi, -1, PREG_SPLIT_NO_EMPTY);
            $kode = strtoupper(
                mb_substr(implode('', array_map(fn ($p) => mb_substr($p, 0, 1, 'UTF-8'), $parts)), 0, 3, 'UTF-8') ?: 'X'
            );
            if (DB::table('seksi')->where('kode_seksi', $kode)->exists()) {
                $kode .= '-' . $row->id;
            }
            DB::table('seksi')->where('id', $row->id)->update(['kode_seksi' => $kode]);
        }
    }

    public function down(): void
    {
        Schema::table('seksi', function (Blueprint $table) {
            $table->dropColumn('kode_seksi');
        });
    }
};
