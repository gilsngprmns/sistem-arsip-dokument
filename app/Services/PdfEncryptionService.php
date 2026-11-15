<?php

namespace App\Services;

use App\Models\DokumenArsip;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Enkripsi file PDF dokumen arsip (AES-256-CBC via Laravel Crypt).
 * File terenkripsi disimpan di disk privat (storage/app/private/arsip-encrypted),
 * tidak dapat diakses via URL publik.
 */
class PdfEncryptionService
{
    /** Direktori relatif di disk 'local' untuk file terenkripsi. */
    public const ENCRYPTED_DIR = 'arsip-encrypted';

    /**
     * Enkripsi file upload, simpan ke disk privat, dan kembalikan metadata.
     *
     * @return array{path: string, hash: string, encrypted_at: string}
     */
    public function encryptAndStore(UploadedFile $file): array
    {
        $originalName = $file->getClientOriginalName();
        $rawBytes = file_get_contents($file->getRealPath());

        if ($rawBytes === false) {
            throw new RuntimeException('Gagal membaca file upload.');
        }

        $sha256 = hash('sha256', $rawBytes);
        $encrypted = Crypt::encryptString($rawBytes);

        // Nama file: hash64 + ekstensi asli agar unik dan aman
        $safeName = substr(hash('sha256', $originalName), 0, 32).'.enc';
        $relativePath = self::ENCRYPTED_DIR.'/'.$safeName;

        Storage::disk('local')->put($relativePath, $encrypted);

        return [
            'path'         => $relativePath,
            'hash'         => $sha256,
            'encrypted_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Baca + dekripsi file terenkripsi dari disk privat.
     * Validasi integritas SHA-256: hash file asli harus cocok
     * dengan hash hasil dekripsi (mendeteksi tamper / korupsi data).
     *
     * @throws RuntimeException jika hash tidak cocok
     * @return string Konten biner PDF setelah dekripsi
     */
    public function decryptContent(DokumenArsip $arsip): string
    {
        if (! $arsip->is_encrypted) {
            throw new RuntimeException('Dokumen ini tidak terenkripsi.');
        }

        $path = $arsip->file_path;
        if (blank($path) || ! Storage::disk('local')->exists($path)) {
            throw new RuntimeException('File terenkripsi tidak ditemukan di storage.');
        }

        $encryptedBytes = Storage::disk('local')->get($path);
        $decryptedBytes = Crypt::decryptString($encryptedBytes);

        if ($arsip->file_hash !== null) {
            $actualHash = hash('sha256', $decryptedBytes);
            if (! hash_equals($arsip->file_hash, $actualHash)) {
                throw new RuntimeException(
                    'Integritas file terenkripsi gagal: checksum SHA-256 tidak cocok. '
                    .'File kemungkinan telah dimodifikasi secara ilegal.'
                );
            }
        }

        return $decryptedBytes;
    }

    /**
     * Hapus file terenkripsi dari disk privat.
     * Dipanggil saat dokumen dihapus (destroy / forceDelete / bulkDestroy).
     */
    public function deleteEncrypted(DokumenArsip $arsip): void
    {
        if ($arsip->is_encrypted && $arsip->file_path) {
            Storage::disk('local')->delete($arsip->file_path);
        }
    }

    /**
     * Hapus file lama (encrypted atau public) sebelum simpan file baru.
     * Dipanggil saat update mengganti lampiran.
     */
    public function deleteOldFile(DokumenArsip $arsip): void
    {
        if ($arsip->is_encrypted) {
            $this->deleteEncrypted($arsip);
        } elseif ($arsip->file_path) {
            if (Storage::disk('public')->exists($arsip->file_path)) {
                Storage::disk('public')->delete($arsip->file_path);
            }
        }
    }
}
