<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

return new class extends Migration
{
    /**
     * Paksa konversi semua QR boks ke format PNG.
     * Mengabaikan file SVG yang sudah ada, selalu generate PNG baru.
     */
    public function up(): void
    {
        Storage::disk('public')->makeDirectory('qrcodes-boks');

        $boksList = DB::table('boks_arsip')->get();

        foreach ($boksList as $boks) {
            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
            $pngPath = "qrcodes-boks/boks-{$safeName}.png";
            $svgPath = "qrcodes-boks/boks-{$safeName}.svg";

            // Buat ulang QR selalu dalam format PNG
            // Abaikan file lama (SVG/PNG), timpa dengan PNG baru
            $qrUrl = url('/scan/boks/' . urlencode($boks->kode_boks));

            try {
                $contents = QrCode::format('png')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($pngPath, $contents);
                DB::table('boks_arsip')->where('id', $boks->id)->update([
                    'qr_path' => $pngPath,
                ]);

                // Hapus file SVG lama jika ada
                if (Storage::disk('public')->exists($svgPath)) {
                    Storage::disk('public')->delete($svgPath);
                }
            } catch (\Throwable $e) {
                // Jika PNG generation gagal total, simpan SVG sebagai fallback
                $contents = QrCode::format('svg')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($svgPath, $contents);
                DB::table('boks_arsip')->where('id', $boks->id)->update([
                    'qr_path' => $svgPath,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Kembalikan ke null — tidak perlu restore SVG lama
        DB::table('boks_arsip')->update(['qr_path' => null]);
    }
};
