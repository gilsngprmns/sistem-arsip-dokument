<?php

namespace App\Services\Backup;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\Seksi;
use App\Models\User;
use FilesystemIterator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Bundel cadangan lengkap versi 2: satu ZIP berisi
 *  - data.json     : envelope (metadata + checksum SHA-256) berisi seksi, lokasi,
 *                    rak, dokumen (riwayat sirkulasi terembed per dokumen),
 *  - manifest.json : rincian konten + daftar file fisik dengan SHA-256,
 *  - files/        : salinan file fisik lampiran & QR dari storage publik.
 *
 * Restore berjalan dengan urutan relasi yang benar (lokasi → seksi → rak →
 * dokumen → riwayat → file fisik) sehingga pemulihan lintas-mesin tidak
 * meninggalkan FK gantung. Hash kanonik memakai satu helper bersama:
 * BackupService::canonicalHash().
 */
class BundleBackupService
{
    public const BUNDLE_VERSION = 2;

    /** Batas jumlah entri ZIP saat ekstrak — cegah zip bomb. */
    private const MAX_ZIP_ENTRIES = 2000;

    /** Batas entri detail yang dilaporkan di metrik. */
    private const DETAIL_CAP = 50;

    /* ------------------------------------------------------------------
     |  Ekspor
     * --------------------------------------------------------------- */

    /**
     * Susun payload bundel (semua tabel terkait; riwayat terembed per dokumen).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function buildPayload(): array
    {
        return [
            'seksi' => Seksi::all()
                ->map(fn ($m) => Arr::except($m->toArray(), ['created_at', 'updated_at']))
                ->values()->all(),
            'rak_arsip' => RakArsip::all()
                ->map(fn ($m) => Arr::except($m->toArray(), ['created_at', 'updated_at']))
                ->values()->all(),
            'boks_arsip' => BoksArsip::all()
                ->map(fn ($m) => Arr::except($m->toArray(), ['created_at', 'updated_at']))
                ->values()->all(),
            'dokumen_arsip' => DokumenArsip::withTrashed()->with('riwayatSirkulasi')
                ->get()
                ->map(function ($row) {
                    $data = Arr::except($row->toArray(), ['seksi', 'riwayat_sirkulasi', 'riwayatSirkulasi', 'pencatat']);
                    unset($data['id']);

                    // Id sumber disimpan di baris induk; riwayat tanpa id —
                    // kunci asing diganti saat import di sisi tujuan.
                    $data['riwayat_sirkulasi'] = $row->riwayatSirkulasi
                        ->map(fn ($r) => Arr::only($r->toArray(), ['user_id', 'jenis_aksi', 'pihak_terkait', 'keterangan', 'waktu_aksi']))
                        ->values()->all();

                    return $data;
                })
                ->values()->all(),
        ];
    }

    /**
     * Kumpulkan file fisik yang tercatat & benar-benar ada di storage publik.
     *
     * @return list<array{path: string, sha256: string, ukuran: int}>
     */
    public function gatherFiles(): array
    {
        $jalur = DokumenArsip::withTrashed()
            ->whereNotNull('file_path')->pluck('file_path')
            ->merge(DokumenArsip::withTrashed()->whereNotNull('qr_code_path')->pluck('qr_code_path'))
            ->filter()
            ->unique()
            ->values();

        return $jalur
            ->filter(fn (string $p) => Storage::disk('public')->exists($p))
            ->map(function (string $p) {
                $absolut = storage_path('app/public/'.$p);

                return [
                    'path' => $p,
                    'sha256' => hash_file('sha256', $absolut),
                    'ukuran' => File::size($absolut),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Buat satu bundel ZIP lengkap di direktori tujuan. Hasil atomik:
     * gagal di tengah → berkas separuh dihapus.
     *
     * @return array{nama_file: string, path: string, rincian: array<string, mixed>}
     */
    public function exportBundle(
        string $generator = 'manual',
        ?string $dibuatOleh = null,
        bool $includeFiles = true,
        ?string $outDir = null,
    ): array {
        $outDir = $outDir ?: config('backup.path');
        if (! is_dir($outDir) && ! @mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            throw new RuntimeException('Direktori cadangan tidak dapat dibuat: '.$outDir);
        }

        $payload = $this->buildPayload();
        $daftarFile = $includeFiles ? $this->gatherFiles() : [];

        $meta = [
            'dibuat_oleh' => $dibuatOleh,
            'dibuat_pada' => now()->toIso8601String(),
            'generator' => $generator,
            'jumlah_data' => count($payload['dokumen_arsip']),
            'include_files' => $includeFiles,
            'checksum_sha256' => BackupService::canonicalHash($payload),
        ];

        $namaFile = 'backup-arsip-'.now()->format('Ymd-His').'.zip';
        $rincian = [
            'seksi' => count($payload['seksi']),
            'boks_arsip' => count($payload['boks_arsip'] ?? []),
            'rak_arsip' => count($payload['rak_arsip']),
            'dokumen_arsip' => count($payload['dokumen_arsip']),
            'riwayat_sirkulasi' => collect($payload['dokumen_arsip'])->sum(fn (array $d) => count($d['riwayat_sirkulasi'] ?? [])),
            'file_fisik' => count($daftarFile),
        ];

        // Staging: tulis semua → zip → pindahkan ke nama akhir (atomik)
        $staging = storage_path('app/tmp/bundle-'.uniqid('', true));
        File::makeDirectory($staging, 0755, true);

        try {
            File::put($staging.'/data.json', json_encode([
                'format' => BackupService::FORMAT,
                'version' => self::BUNDLE_VERSION,
                'meta' => $meta,
                'data' => $payload,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            File::put($staging.'/manifest.json', json_encode([
                'nama_file' => $namaFile,
                'dibuat_pada' => $meta['dibuat_pada'],
                'checksum_sha256' => $meta['checksum_sha256'],
                'rincian_konten' => $rincian,
                'daftar_file' => $daftarFile,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            foreach ($daftarFile as $entry) {
                $tujuan = $staging.'/files/'.$entry['path'];
                // ensureDirectoryExists mengecek is_directory dulu — mkdir()
                // recursive pada direktori yang sudah ada memicu E_WARNING
                // "File exists", yang di artisan di-escalate jadi ErrorException.
                File::ensureDirectoryExists(dirname($tujuan), 0755, true);
                copy(storage_path('app/public/'.$entry['path']), $tujuan);
            }

            $tmpZip = $staging.'/bundel.zip';
            $zip = new ZipArchive();
            if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
                throw new RuntimeException('Tidak dapat membuat arsip ZIP.');
            }

            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staging, FilesystemIterator::SKIP_DOTS)) as $file) {
                /** @var SplFileInfo $file */
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($staging) + 1));
                if (is_file($file->getPathname())) {
                    $zip->addFile($file->getPathname(), $rel);
                }
            }
            $zip->close();

            $finalPath = $outDir.'/'.$namaFile;
            if (! rename($tmpZip, $finalPath)) {
                throw new RuntimeException('Pergeseran berkas cadangan gagal.');
            }

            // Manifest sisi (sidecar) agar daftar di UI tidak perlu membongkar ZIP
            File::put($outDir.'/'.$namaFile.'.manifest.json', json_encode([
                'nama_file' => $namaFile,
                'diambil_pada' => $meta['dibuat_pada'],
                'rincian_konten' => $rincian,
                'total_size_bytes' => File::size($finalPath),
            ], JSON_PRETTY_PRINT));
        } finally {
            File::deleteDirectory($staging);
        }

        $rincian['total_size_bytes'] = File::size($finalPath);

        return [
            'nama_file' => $namaFile,
            'path' => $finalPath,
            'rincian' => $rincian,
        ];
    }

    /**
     * Daftar bundel tersimpan terbaru (dari sidecar manifest).
     *
     * @return list<array<string, mixed>>
     */
    public function listBundles(int $limit = 10): array
    {
        $dir = config('backup.path');
        if (! is_dir($dir)) {
            return [];
        }

        $daftar = [];
        foreach (glob($dir.'/backup-arsip-*.manifest.json') ?: [] as $sidecar) {
            $isi = json_decode((string) File::get($sidecar), true);
            if (! is_array($isi) || empty($isi['nama_file']) || ! File::exists($dir.'/'.$isi['nama_file'])) {
                continue;
            }

            $daftar[] = [
                'nama_file' => $isi['nama_file'],
                'diambil_pada' => $isi['diambil_pada'] ?? (string) date('c', filemtime($dir.'/'.$isi['nama_file'])),
                'rincian' => $isi['rincian_konten'] ?? null,
                'ukuran_bytes' => $isi['total_size_bytes'] ?? File::size($dir.'/'.$isi['nama_file']),
            ];
        }

        usort($daftar, fn (array $a, array $b) => strcmp($b['diambil_pada'], $a['diambil_pada']));

        return array_slice($daftar, 0, $limit);
    }

    /**
     * Hapus satu atau beberapa bundel backup berdasarkan nama filenya.
     *
     * Menghapus berkas ZIP/JSON beserta sidecar manifest-nya.
     *
     * @param  array<string>  $namaFile  Nama file backup yang akan dihapus
     * @return int Jumlah file yang berhasil dihapus
     */
    public function deleteBundles(array $namaFile): int
    {
        $dir = config('backup.path');
        if (! is_dir($dir)) {
            return 0;
        }

        $dihapus = 0;
        foreach ($namaFile as $name) {
            $name = basename($name); // cegah path traversal
            if (! preg_match('/^backup-arsip-[0-9]{8}-[0-9]{6}\.(json|zip)$/', $name)) {
                continue;
            }
            $zipPath = $dir.'/'.$name;
            if (File::exists($zipPath)) {
                File::delete($zipPath);
                $dihapus++;
            }
            // juga hapus sidecar manifest jika ada
            $manifestPath = $zipPath.'.manifest.json';
            if (File::exists($manifestPath)) {
                File::delete($manifestPath);
                $dihapus++;
            }
        }

        return $dihapus;
    }

    /**
     * Hapus bundel lebih tua dari N hari (berkas + sidecar-nya).
     */
    public function applyRetention(int $retainDays): int
    {
        $dir = config('backup.path');
        if (! is_dir($dir)) {
            return 0;
        }

        $batas = now()->subDays($retainDays)->getTimestamp();
        $dihapus = 0;

        foreach (glob($dir.'/backup-arsip-*.zip') ?: [] as $zipPath) {
            if (filemtime($zipPath) < $batas) {
                @unlink($zipPath);
                @unlink($zipPath.'.manifest.json');
                $dihapus++;
            }
        }

        return $dihapus;
    }

    /* ------------------------------------------------------------------
     |  Impor
     * --------------------------------------------------------------- */

    /**
     * Bongkar ZIP bundel ke temp dir dengan verifikasi integritas penuh
     * (struktur, checksum data, checksum tiap file fisik). Melempar
     * RuntimeException bila ada yang tidak cocok; temp dir selalu dibersihkan.
     *
     * @return array{payload: array<string, mixed>, meta: array<string, mixed>, files_root: string, daftar_file: list<array<string, mixed>>, temp_dir: string}
     */
    public function extractBundle(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Berkas tidak dapat dibuka sebagai ZIP bundel cadangan.');
        }

        if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
            $zip->close();
            throw new RuntimeException('Bundel mengandung terlalu banyak entri — import ditolak.');
        }

        $tempDir = storage_path('app/tmp/bundle-extract-'.uniqid('', true));
        File::makeDirectory($tempDir, 0755, true);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nama = $zip->getNameIndex($i);
                if (! is_string($nama) || $nama === '') {
                    continue; // entri direktori kosong / nama aneh
                }
                // Cegah zip slip
                if (str_contains($nama, '..') || str_starts_with($nama, '/') || preg_match('#^[A-Za-z]:#', $nama)) {
                    throw new RuntimeException('Bundel mengandung nama file tidak aman: '.$nama);
                }
                if (! $zip->extractTo($tempDir, $nama)) {
                    throw new RuntimeException('Gagal mengekstrak berkas: '.$nama);
                }
            }
            $zip->close();
        } catch (RuntimeException $e) {
            $zip->close();
            File::deleteDirectory($tempDir);

            throw $e;
        }

        if (! file_exists($tempDir.'/data.json')) {
            File::deleteDirectory($tempDir);
            throw new RuntimeException('Bundel tidak memuat data.json.');
        }

        $envelope = json_decode((string) File::get($tempDir.'/data.json'), true);
        [$payload, $meta] = $this->verifikasiEnvelope(is_array($envelope) ? $envelope : null);

        $manifest = json_decode((string) File::get($tempDir.'/manifest.json') ?: 'null', true);
        $daftarFile = is_array($manifest['daftar_file'] ?? null) ? $manifest['daftar_file'] : [];

        // Verifikasi per-file fisik (checksum harus sama sejak diekspor)
        $filesRoot = $tempDir.'/files';
        foreach ($daftarFile as $entry) {
            if (! is_array($entry) || ! is_string($entry['path'] ?? null) || empty($entry['sha256'])) {
                File::deleteDirectory($tempDir);
                throw new RuntimeException('Manifest bundel tidak lengkap (entri file bermasalah).');
            }

            $asal = $filesRoot.'/'.$entry['path'];
            if (! file_exists($asal)) {
                File::deleteDirectory($tempDir);
                throw new RuntimeException('File fisik "'.$entry['path'].'" tidak ditemukan dalam bundel.');
            }
            if (! hash_equals((string) $entry['sha256'], hash_file('sha256', $asal))) {
                File::deleteDirectory($tempDir);
                throw new RuntimeException('Integritas file fisik "'.$entry['path'].'" gagal (checksum tidak cocok).');
            }
        }

        return [
            'payload' => $payload,
            'meta' => $meta,
            'files_root' => $filesRoot,
            'daftar_file' => $daftarFile,
            'temp_dir' => $tempDir,
        ];
    }

    /**
     * Hapus temp dir hasil ekstrak.
     */
    public function cleanupExtraction(string $tempDir): void
    {
        File::deleteDirectory($tempDir);
    }

    /**
     * Validasi envelope versi 2: struktur + checksum SHA-256 bagian data.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>} [payload, meta]
     */
    private function verifikasiEnvelope(?array $envelope): array
    {
        if (($envelope['format'] ?? null) !== BackupService::FORMAT || ($envelope['version'] ?? null) !== self::BUNDLE_VERSION) {
            throw new RuntimeException('Bundel tidak dikenal: versi format tidak didukung.');
        }

        $payload = $envelope['data'] ?? null;
        $meta = $envelope['meta'] ?? null;

        if (! is_array($payload) || ! is_array($meta)) {
            throw new RuntimeException('Struktur bundel rusak: bagian data/meta hilang.');
        }

        $periksa = (string) ($meta['checksum_sha256'] ?? '');
        if ($periksa === '' || ! hash_equals($periksa, BackupService::canonicalHash($payload))) {
            throw new RuntimeException(
                'Gagal verifikasi integritas bundel: checksum tidak cocok — berkas terpotong atau diubah. Restore ditolak.'
            );
        }

        return [$payload, $meta];
    }

    /**
     * Pulihkan seluruh isi payload bundel. Urutan relasi benar; baris
     * bermasalah di-skip & dicatat, bukan menggagalkan keseluruhan.
     *
     * @param  array<string, list<array<string, mixed>>>  $payload
     * @param  string|null  $filesRoot  Direktori ekstraksi file fisik; null = lewati
     * @param  list<array<string, mixed>>  $daftarFile
     * @return array<string, mixed> Metrik lengkap
     */
    public function importBundle(array $payload, ?string $filesRoot = null, array $daftarFile = [], bool $dryRun = false): array
    {
        $riwayatHidup = RiwayatSirkulasi::select('dokumen_arsip_id', 'user_id', 'jenis_aksi', 'waktu_aksi')
            ->get()
            ->map(fn ($r) => implode('|', [$r->dokumen_arsip_id, $r->user_id, $r->jenis_aksi, $r->waktu_aksi]))
            ->flip()->all();

        $userHidup = User::pluck('id')->all();

        $skipped = [];
        $peringatan = [];
        $statRef = ['boks' => $this->stat(), 'seksi' => $this->stat(), 'rak' => $this->stat()];
        $statDok = $this->stat();
        $statRiwayat = $this->stat() + ['disalin' => 0];
        $statFile = ['disalin' => 0, 'dilewati' => 0];

        $petaBoks = [];
        $petaSeksi = [];
        $petaRak = [];

        DB::transaction(function () use ($payload, $dryRun, $userHidup, &$skipped, &$peringatan, &$statRef, &$statDok, &$statRiwayat, &$riwayatHidup, &$petaBoks, &$petaSeksi, &$petaRak): void {
            $riwayatAntre = []; // kode_arsip => daftar baris riwayat (tanpa id, kunci dokumen sumber)

            // — 1. Seksi (kunci: nama_seksi) —
            foreach (($payload['seksi'] ?? []) as $row) {
                if (! is_array($row) || empty($row['nama_seksi'])) {
                    continue;
                }
                $petaSeksi[$row['id'] ?? null] = $this->upsertReferensi(
                    Seksi::class, 'nama_seksi', $row,
                    ['keterangan'],
                    $dryRun, $statRef['seksi'],
                );
            }

            // — 2. Rak (kunci: kode_rak) dengan remap FK seksi —
            foreach (($payload['rak_arsip'] ?? []) as $row) {
                if (! is_array($row) || empty($row['kode_rak'])) {
                    continue;
                }

                $id = $row['id'] ?? null;
                $seksiLama = $row['seksi_id'] ?? null;

                $fields = [
                    'nama_rak' => $row['nama_rak'] ?? null,
                    'nomor_rak' => $row['nomor_rak'] ?? null,
                    'deskripsi' => $row['deskripsi'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                    'seksi_id' => $seksiLama !== null ? ($petaSeksi[$seksiLama] ?? null) : null,
                ];

                $petaRak[$id] = $this->upsertReferensi(
                    RakArsip::class, 'kode_rak', $row,
                    ['nama_rak', 'nomor_rak', 'deskripsi', 'is_active', 'seksi_id'],
                    $dryRun,
                    $statRef['rak'],
                    fieldsOverride: $fields,
                );
            }

            // — 3. Boks (kunci: kode_boks) dengan remap FK rak —
            foreach (($payload['boks_arsip'] ?? []) as $row) {
                if (! is_array($row) || empty($row['kode_boks'])) {
                    continue;
                }

                $id = $row['id'] ?? null;
                $rakLama = $row['rak_id'] ?? null;

                $fields = [
                    'rak_id' => $rakLama !== null ? ($petaRak[$rakLama] ?? null) : null,
                    'kapasitas' => $row['kapasitas'] ?? null,
                    'nomor_baris' => $row['nomor_baris'] ?? null,
                    'nomor_boks' => $row['nomor_boks'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'is_active' => $row['is_active'] ?? true,
                ];

                $petaBoks[$id] = $this->upsertReferensi(
                    BoksArsip::class, 'kode_boks', $row,
                    ['rak_id', 'kapasitas', 'nomor_baris', 'nomor_boks', 'keterangan', 'is_active'],
                    $dryRun, $statRef['boks'],
                    fieldsOverride: $fields,
                );
            }

            // — 4. Dokumen (kunci: kode_arsip) dengan remap referensi —
            $seenKode = [];
            foreach (($payload['dokumen_arsip'] ?? []) as $row) {
                if (! is_array($row) || empty($row['kode_arsip'])) {
                    $this->catatSkip($skipped, 'arsip tanpa kode', 'tanpa kode_arsip');
                    continue;
                }

                $kode = (string) $row['kode_arsip'];

                if (isset($seenKode[$kode])) {
                    $this->catatSkip($skipped, $kode, 'kode_arsip duplikat di dalam file — entri pertama yang dipakai');
                    continue;
                }
                $seenKode[$kode] = true;

                $payloadBaris = Arr::except($row, ['id', 'seksi', 'riwayat_sirkulasi', 'riwayatSirkulasi', 'pencatat']);

                $seksiLama = $payloadBaris['seksi_id'] ?? null;
                if ($seksiLama === null || ! array_key_exists($seksiLama, $petaSeksi)) {
                    $this->catatSkip($skipped, $kode, 'merujuk seksi yang tidak ada di sistem ini');
                    continue;
                }
                $payloadBaris['seksi_id'] = $petaSeksi[$seksiLama];

                $rakLama = $payloadBaris['rak_id'] ?? null;
                if ($rakLama !== null) {
                    if (array_key_exists($rakLama, $petaRak)) {
                        $payloadBaris['rak_id'] = $petaRak[$rakLama];
                    } else {
                        $payloadBaris['rak_id'] = null;
                        $peringatan[] = $kode.': rak sumber tidak ada di sistem ini — posisi rak dikosongkan';
                    }
                }

                $boksLama = $payloadBaris['boks_id'] ?? null;
                if ($boksLama !== null) {
                    if (array_key_exists($boksLama, $petaBoks)) {
                        $payloadBaris['boks_id'] = $petaBoks[$boksLama];
                    } else {
                        $payloadBaris['boks_id'] = null;
                    }
                }

                if (! empty($payloadBaris['dicatat_oleh']) && ! in_array($payloadBaris['dicatat_oleh'], $userHidup, true)) {
                    $payloadBaris['dicatat_oleh'] = null;
                    $peringatan[] = $kode.': pencatat tidak ada di sistem ini — kolom dikosongkan';
                }

                if (! empty($row['riwayat_sirkulasi'])) {
                    $riwayatAntre[$kode] = $row['riwayat_sirkulasi'];
                }

                if ($dryRun) {
                    $ada = DokumenArsip::withTrashed()->where('kode_arsip', $kode)->exists();
                    $ada ? $statDok['diperbarui']++ : $statDok['dibuat']++;
                    continue;
                }

                try {
                    $model = DokumenArsip::withTrashed()->updateOrCreate(['kode_arsip' => $kode], $payloadBaris);
                    $model->wasRecentlyCreated ? $statDok['dibuat']++ : $statDok['diperbarui']++;
                } catch (Throwable $e) {
                    $this->catatSkip($skipped, $kode, 'gagal ditulis: '.mb_substr($e->getMessage(), 0, 200));
                }
            }

            // — 5. Riwayat sirkulasi (per dokumen yang masuk; dedupe kunci penuh) —
            $docIdsByKode = $dryRun ? [] : DokumenArsip::withTrashed()->pluck('id', 'kode_arsip')->all();

            foreach ($riwayatAntre as $kode => $daftar) {
                if ($dryRun) {
                    $statRiwayat['disalin'] += count($daftar);
                    continue;
                }

                $docId = $docIdsByKode[$kode] ?? null;
                if ($docId === null) {
                    // induk tidak masuk → riwayat tak dapat dipasang
                    $statRiwayat['dilewati'] += count($daftar);
                    continue;
                }

                foreach ($daftar as $r) {
                    if (! is_array($r)) {
                        continue;
                    }

                    $user = $r['user_id'] ?? null;
                    if ($user === null || ! in_array($user, $userHidup, true)) {
                        $statRiwayat['dilewati']++;
                        $peringatan[] = $kode.': riwayat yang tidak punya petugas (user) di sistem ini — dilewati';
                        continue;
                    }

                    $kunci = implode('|', [$docId, $user, $r['jenis_aksi'] ?? '', $r['waktu_aksi'] ?? '']);
                    if (isset($riwayatHidup[$kunci])) {
                        $statRiwayat['dilewati']++;
                        continue;
                    }

                    try {
                        RiwayatSirkulasi::create([
                            'dokumen_arsip_id' => $docId,
                            'user_id' => $user,
                            'jenis_aksi' => $r['jenis_aksi'] ?? null,
                            'pihak_terkait' => $r['pihak_terkait'] ?? null,
                            'keterangan' => $r['keterangan'] ?? null,
                            'waktu_aksi' => $r['waktu_aksi'] ?? now()->toDateTimeString(),
                        ]);
                        $statRiwayat['disalin']++;
                        $riwayatHidup[$kunci] = true;
                    } catch (Throwable) {
                        $statRiwayat['dilewati']++;
                    }
                }
            }
        });

        // — 6. File fisik (di luar transaksi DB; path terverifikasi saat ekstrak) —
        if ($filesRoot !== null) {
            if ($dryRun) {
                $statFile['disalin'] = count($daftarFile);
            } else {
                foreach ($daftarFile as $entry) {
                    if (! is_array($entry) || ! is_string($entry['path'] ?? null)) {
                        continue;
                    }

                    $asal = $filesRoot.'/'.$entry['path'];
                    if (! file_exists($asal)) {
                        $statFile['dilewati']++;
                        $peringatan[] = 'File fisik "'.$entry['path'].'" tidak ditemukan dalam bundel';
                        continue;
                    }

                    try {
                        Storage::disk('public')->put($entry['path'], file_get_contents($asal));
                        $statFile['disalin']++;
                    } catch (Throwable) {
                        $statFile['dilewati']++;
                        $peringatan[] = 'Gagal memulihkan file fisik "'.$entry['path'].'".';
                    }
                }
            }
        }

        return [
            'referensi' => $statRef,
            'dibuat' => $statDok['dibuat'],
            'diperbarui' => $statDok['diperbarui'],
            'dilewati' => count($skipped),
            'detil_lewati' => array_slice($skipped, 0, self::DETAIL_CAP),
            'riwayat' => $statRiwayat,
            'file_dipulihkan' => $statFile['disalin'],
            'file_dilewati' => $statFile['dilewati'],
            'peringatan' => array_slice($peringatan, 0, 100),
        ];
    }

    /* ------------------------------------------------------------------
     |  Util
     * --------------------------------------------------------------- */

    /** @return array{dibuat: int, diperbarui: int, dilewati: int} */
    private function stat(): array
    {
        return ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0];
    }

    /**
     * Upsert entitas referensial berdasarkan kunci kode/nama; kembalikan
     * id entitas di sisi tujuan.
     *
     * @param  class-string  $kelas
     * @return int|null
     */
    private function upsertReferensi(
        string $kelas,
        string $kolomKunci,
        array $baris,
        array $kolom,
        bool $dryRun,
        array &$stat,
        ?array $fieldsOverride = null,
    ): ?int {
        $kunci = $baris[$kolomKunci] ?? null;
        if ($kunci === null || $kunci === '') {
            return null;
        }

        $fields = $fieldsOverride ?? Arr::only($baris, $kolom);

        if ($dryRun) {
            $ada = $kelas::where($kolomKunci, $kunci)->exists();
            $ada ? $stat['diperbarui']++ : $stat['dibuat']++;

            return (int) ($kelas::where($kolomKunci, $kunci)->value('id') ?? 0);
        }

        $model = $kelas::updateOrCreate([$kolomKunci => $kunci], $fields);
        $model->wasRecentlyCreated ? $stat['dibuat']++ : $stat['diperbarui']++;

        return $model->getKey();
    }

    /**
     * @param  list<array{alamat: string, alasan: string}>  $skipped
     */
    private function catatSkip(array &$skipped, string $alamat, string $alasan): void
    {
        if (count($skipped) < self::DETAIL_CAP) {
            $skipped[] = ['alamat' => $alamat, 'alasan' => $alasan];
        }
    }
}
