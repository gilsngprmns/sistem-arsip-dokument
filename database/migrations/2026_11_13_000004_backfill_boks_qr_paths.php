<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

return new class extends Migration
{
    public function up(): void
    {
        Storage::disk('public')->makeDirectory('qrcodes-boks');

        $boksList = DB::table('boks_arsip')->get();

        foreach ($boksList as $boks) {
            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
            $pngPath = "qrcodes-boks/boks-{$safeName}.png";
            $svgPath = "qrcodes-boks/boks-{$safeName}.svg";

            // Skip if already has qr_path in database
            if (!empty($boks->qr_path)) {
                continue;
            }

            // Prefer existing PNG, then SVG
            if (Storage::disk('public')->exists($pngPath)) {
                DB::table('boks_arsip')->where('id', $boks->id)->update(['qr_path' => $pngPath]);
                continue;
            }

            if (Storage::disk('public')->exists($svgPath)) {
                DB::table('boks_arsip')->where('id', $boks->id)->update(['qr_path' => $svgPath]);
                continue;
            }

            // Generate new QR — gunakan path eksplisit agar route() tidak dipanggil saat migration
            $qrUrl = url('/scan/boks/' . urlencode($boks->kode_boks));
            try {
                $contents = QrCode::format('png')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($pngPath, $contents);
                DB::table('boks_arsip')->where('id', $boks->id)->update(['qr_path' => $pngPath]);
            } catch (\Throwable $e) {
                $contents = QrCode::format('svg')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($svgPath, $contents);
                DB::table('boks_arsip')->where('id', $boks->id)->update(['qr_path' => $svgPath]);
            }
        }
    }

    public function down(): void
    {
        DB::table('boks_arsip')->update(['qr_path' => null]);
    }
};
