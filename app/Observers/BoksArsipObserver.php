<?php

namespace App\Observers;

use App\Models\BoksArsip;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BoksArsipObserver
{
    /**
     * Pastikan QR boks tersedia setiap kali arsip masuk/keluar dari boks.
     */
    public function created(BoksArsip $boks): void
    {
        $this->ensureQr($boks);
    }

    public function updated(BoksArsip $boks): void
    {
        $this->ensureQr($boks);
    }

    public function deleted(BoksArsip $boks): void
    {
        $this->deleteQrFiles($boks);
    }

    public function restored(BoksArsip $boks): void
    {
        $this->ensureQr($boks);
    }

    /**
     * Generate QR PNG (fallback SVG) untuk boks jika belum ada.
     * Simpan qr_path ke database agar modal dapat menampilkan gambar.
     */
    public function ensureQr(BoksArsip $boks): void
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
        $pngPath = "qrcodes-boks/boks-{$safeName}.png";
        $svgPath = "qrcodes-boks/boks-{$safeName}.svg";
        $qrUrl = route('scan.boks', $boks->kode_boks, true);

        // Jika sudah ada path di DB, gunakan itu
        if (!empty($boks->qr_path) && Storage::disk('public')->exists($boks->qr_path)) {
            return;
        }

        // Cek file PNG di storage
        if (Storage::disk('public')->exists($pngPath)) {
            $boks->update(['qr_path' => $pngPath]);
            return;
        }

        Storage::disk('public')->makeDirectory('qrcodes-boks');

        // Cek file SVG di storage
        if (Storage::disk('public')->exists($svgPath)) {
            $boks->update(['qr_path' => $svgPath]);
            return;
        }

        // Generate QR baru
        try {
            $contents = QrCode::format('png')->size(300)->generate($qrUrl);
            Storage::disk('public')->put($pngPath, $contents);
            $boks->update(['qr_path' => $pngPath]);
        } catch (\Throwable) {
            $contents = QrCode::format('svg')->size(300)->generate($qrUrl);
            Storage::disk('public')->put($svgPath, $contents);
            $boks->update(['qr_path' => $svgPath]);
        }
    }

    protected function deleteQrFiles(BoksArsip $boks): void
    {
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
        $pngPath = "qrcodes-boks/boks-{$safeName}.png";
        $svgPath = "qrcodes-boks/boks-{$safeName}.svg";

        if (Storage::disk('public')->exists($pngPath)) {
            Storage::disk('public')->delete($pngPath);
        }
        if (Storage::disk('public')->exists($svgPath)) {
            Storage::disk('public')->delete($svgPath);
        }
    }
}
