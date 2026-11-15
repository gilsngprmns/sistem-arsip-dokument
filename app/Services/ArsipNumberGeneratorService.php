<?php

namespace App\Services;

use App\Models\DokumenArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArsipNumberGeneratorService
{
    /**
     * Hitung nomor arsip berikutnya untuk seksi tertentu.
     *
     * Format: ARS-{KODE_SEKSI}-{URUTAN_4_DIGIT}
     * Contoh: ARS-PEM-0001, ARS-PEM-0002, ARS-EKB-0001
     *
     * Urutan tidak di-reset per tahun — terus berurutan per seksi.
     * Dipanggil di dalam DB::transaction() oleh pemanggil untuk anti-race.
     */
    public function nextFor(Seksi $seksi): string
    {
        $kodeSeksi = $this->resolveKodeSeksi($seksi);
        $prefix = "ARS-{$kodeSeksi}-";

        $lastKode = DokumenArsip::withTrashed()
            ->where('seksi_id', $seksi->id)
            ->where('kode_arsip', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('kode_arsip');

        if ($lastKode === null) {
            $urutan = 1;
        } else {
            $match = preg_match('/(\d{4})$/', $lastKode, $m);
            $urutan = $match ? (int) $m[1] + 1 : 1;
        }

        return $prefix.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Kembalikan peta nomor arsip berikutnya per seksi untuk user tertentu.
     * Admin: semua seksi. Staff: hanya seksi yang dikelolanya.
     *
     * Dipakai endpoint preview (arsip.next-number) — tanpa transaction/lock.
     */
    public function nextNumberMapFor(User $user): array
    {
        if ($user->role === 'admin') {
            $seksiList = Seksi::orderByOrganisasi()->get();
        } else {
            $seksiList = Seksi::whereKey($user->seksi_id)->get();
        }

        $map = [];
        foreach ($seksiList as $seksi) {
            $kodeSeksi = $this->resolveKodeSeksi($seksi);
            $prefix = "ARS-{$kodeSeksi}-";

            $lastKode = DokumenArsip::withTrashed()
                ->where('seksi_id', $seksi->id)
                ->where('kode_arsip', 'like', $prefix.'%')
                ->orderByDesc('id')
                ->value('kode_arsip');

            if ($lastKode === null) {
                $urutan = 1;
            } else {
                $match = preg_match('/(\d{4})$/', $lastKode, $m);
                $urutan = $match ? (int) $m[1] + 1 : 1;
            }

            $map[(int) $seksi->id] = $prefix.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
        }

        return $map;
    }

    /**
     * Ambil kode seksi dari kolom kode_seksi.
     * Fallback: inisial nama seksi (misal "Pemerintahan" → "P",
     * "Ekonomi Pembangunan" → "EP", "Kesejahteraan Rakyat" → "KR").
     *
     * Guard ini memastikan nomor tidak pernah kosong walau data belum
     * di-backfill — angka tetap unik per seksi.
     */
    protected function resolveKodeSeksi(Seksi $seksi): string
    {
        if (!empty($seksi->kode_seksi)) {
            return strtoupper($seksi->kode_seksi);
        }

        $parts = preg_split('/\s+/', $seksi->nama_seksi, -1, PREG_SPLIT_NO_EMPTY);
        $inisial = strtoupper(
            mb_substr(implode('', array_map(fn ($p) => mb_substr($p, 0, 1, 'UTF-8'), $parts)), 0, 3, 'UTF-8')
        );
        return $inisial ?: 'X';
    }
}
