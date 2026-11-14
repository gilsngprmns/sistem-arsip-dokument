<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill boks_id dokumen lama yang masih NULL.
     *
     * Dokumen yang dibuat sebelum boks_id wajib punya rak_id tapi tanpa
     * boks_id, sehingga hitung dokumen per boks selalu 0 sementara per
     * rak terisi. Distribusi round-robin ke boks aktif rak tersebut
     * (urutan kode_boks) agar muatan terbagi merata.
     */
    public function up(): void
    {
        $raks = DB::table('dokumen_arsip')
            ->whereNull('boks_id')
            ->whereNotNull('rak_id')
            ->distinct()
            ->pluck('rak_id');

        foreach ($raks as $rakId) {
            $boksIds = DB::table('boks_arsip')
                ->where('rak_id', $rakId)
                ->where('is_active', true)
                ->orderBy('kode_boks')
                ->pluck('id')
                ->all();

            if (empty($boksIds)) {
                continue;
            }

            $dokIds = DB::table('dokumen_arsip')
                ->where('rak_id', $rakId)
                ->whereNull('boks_id')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            foreach ($dokIds as $i => $dokId) {
                DB::table('dokumen_arsip')
                    ->where('id', $dokId)
                    ->update(['boks_id' => $boksIds[$i % count($boksIds)]]);
            }
        }
    }

    public function down(): void
    {
        // Data historis — pengisian boks_id bersifat manual; tidak di-rollback.
    }
};
