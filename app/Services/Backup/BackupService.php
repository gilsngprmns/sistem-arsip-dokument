<?php

namespace App\Services\Backup;

use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Service bersama untuk pembuatan & pemulihan cadangan arsip.
 *
 * Kontrak format:
 * - Envelope v1: {"format":"sip-arsip-backup","version":1,
 *                 "meta":{dibuat_oleh, dibuat_pada, generator, jumlah_data, checksum_sha256},
 *                 "data":[...]}
 * - v0 (legacy): array mentah baris dokumen, tanpa checksum.
 *
 * Hash kanonik dihitung dari bentuk compact (JSON_UNESCAPED_UNICODE) agar
 * hasil hash sama di sisi export dan import — JANGAN hash bentuk pretty-print.
 */
class BackupService
{
    public const FORMAT = 'sip-arsip-backup';

    public const VERSION = 1;

    /** Batas entri detail "dilewati" yang dilaporkan. */
    private const DETAIL_CAP = 50;

    /**
     * Ambil seluruh arsip (termasuk soft delete) sebagai array baris flat
     * untuk dijadikan isi backup.
     *
     * @return list<array<string, mixed>>
     */
    public function buildData(): array
    {
        return DokumenArsip::withTrashed()
            ->get()
            ->map(fn ($row) => Arr::except($row->toArray(), ['seksi', 'riwayat_sirkulasi', 'riwayatSirkulasi', 'pencatat']))
            ->values()
            ->all();
    }

    /**
     * Bungkus payload menjadi envelope self-describing v1 + checksum SHA-256.
     *
     * @param  list<array<string, mixed>>  $data
     * @return array{format: string, version: int, meta: array<string, mixed>, data: list<array<string, mixed>>}
     */
    public function envelope(array $data, string $generator, ?string $dibuatOleh = null): array
    {
        $meta = [
            'dibuat_oleh' => $dibuatOleh,
            'dibuat_pada' => now()->toIso8601String(),
            'generator' => $generator,
            'jumlah_data' => count($data),
            'checksum_sha256' => self::canonicalHash($data),
        ];

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'meta' => $meta,
            'data' => $data,
        ];
    }

    /**
     * Hash kanonik bagian data envelope — satu-satunya fungsi pembentuk hash
     * yang dipakai di kedua ujung (export & import).
     *
     * @param  list<array<string, mixed>>  $data
     */
    public static function canonicalHash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Parse isi file backup. Melempar RuntimeException bila file ditolak.
     *
     * @return array{rows: list<array<string, mixed>>, versi: 'v1'|'v0', meta: ?array<string, mixed>}
     */
    public function parse(string $raw): array
    {
        $content = json_decode($raw, true);

        if (! is_array($content)) {
            throw new RuntimeException('Isi file backup tidak valid atau bukan JSON array.');
        }

        // Envelope v1: {"format":"sip-arsip-backup","meta":{...},"data":[...]}
        if (($content['format'] ?? null) === self::FORMAT) {
            $data = $content['data'] ?? null;
            $meta = $content['meta'] ?? null;

            if (! is_array($data) || ! is_array($meta)) {
                throw new RuntimeException('Struktur envelope rusak: bagian "data"/"meta" hilang atau salah tipe.');
            }

            $periksa = (string) ($meta['checksum_sha256'] ?? '');
            if ($periksa === '' || ! hash_equals($periksa, self::canonicalHash($data))) {
                throw new RuntimeException(
                    'Gagal verifikasi integritas: checksum file backup tidak cocok — file terpotong, terkorupsi, atau diubah. Restore ditolak.'
                );
            }

            return ['rows' => $data, 'versi' => 'v1', 'meta' => $meta];
        }

        // v0 legacy: array mentah baris dokumen
        if (array_is_list($content)) {
            return ['rows' => $content, 'versi' => 'v0', 'meta' => null];
        }

        throw new RuntimeException('Format file backup tidak dikenal (harus array dokumen atau envelope sip-arsip-backup).');
    }

    /**
     * Impor baris ke basis data. Baris bermasalah di-skip + dicatat,
     * baris valid tetap masuk — kegagalan sebagian tidak menggagalkan semua.
     *
     * Catatan: JSON v1 hanya berisi baris dokumen (tanpa referensi seksi/rak),
     * sehingga restore hanya bekerja andal di mesin dengan id referensi yang
     * sama. Untuk migrasi antar-mesin, gunakan bundel ZIP (v2) yang
     * menyertakan seksi, rak, lokasi, dan remap FK otomatis.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed> Metrik: dibuat, diperbarui, dilewati, detil_lewati, peringatan
     */
    public function importRows(array $rows): array
    {
        $seksiHidup = Seksi::pluck('id')->all();
        $rakHidup = RakArsip::pluck('id')->all();
        $userHidup = User::pluck('id')->all();

        $skipped = [];
        $peringatan = [];
        $seenKode = [];
        $payloads = [];

        // — Pravalidasi: jangan andalkan error database —
        foreach ($rows as $index => $row) {
            if (! is_array($row) || empty($row['kode_arsip'])) {
                $this->catatSkip($skipped, 'baris #'.($index + 1), 'bukan objek / tanpa kode_arsip');
                continue;
            }

            $kode = (string) $row['kode_arsip'];

            if (isset($seenKode[$kode])) {
                $this->catatSkip($skipped, $kode, 'kode_arsip duplikat di dalam file — entri pertama yang dipakai');
                continue;
            }
            $seenKode[$kode] = true;

            $payload = Arr::except($row, ['id', 'seksi', 'riwayat_sirkulasi', 'riwayatSirkulasi', 'pencatat']);

            // seksi_id wajib (NOT NULL) & harus ada di sistem tujuan
            if (empty($payload['seksi_id'])) {
                $this->catatSkip($skipped, $kode, 'tanpa referensi seksi (wajib)');
                continue;
            }
            if (! in_array($payload['seksi_id'], $seksiHidup, true)) {
                $this->catatSkip($skipped, $kode, 'merujuk seksi (id '.$payload['seksi_id'].') yang tidak ada di sistem ini');
                continue;
            }

            // rak_id opsional, tapi bila ada harus cocok
            if (! empty($payload['rak_id']) && ! in_array($payload['rak_id'], $rakHidup, true)) {
                $payload['rak_id'] = null;
                $peringatan[] = $kode.': rak sumber tidak ada di sistem ini — posisi rak dikosongkan';
            }

            // pencatat yang tidak ada di target → kosongkan, jangan buang arsipnya
            if (! empty($payload['dicatat_oleh']) && ! in_array($payload['dicatat_oleh'], $userHidup, true)) {
                $payload['dicatat_oleh'] = null;
                $peringatan[] = $kode.': pencatat (user id '.$row['dicatat_oleh'].') tidak ada di sistem ini — kolom dikosongkan';
            }

            // duplikat kode di database = undefined behavior updateOrCreate; tolak eksplisit
            $jumlahDiDb = DokumenArsip::withTrashed()->where('kode_arsip', $kode)->count();
            if ($jumlahDiDb > 1) {
                $this->catatSkip($skipped, $kode, "terdapat {$jumlahDiDb} arsip dengan kode sama di sistem — perbaiki duplikat dahulu");
                continue;
            }

            $payloads[$kode] = $payload;
        }

        // — Tulis: satu transaksi, tapi 1 baris bermasalah tidak menggagalkan sisanya —
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($payloads, &$created, &$updated, $skipped): void {
            foreach ($payloads as $kode => $payload) {
                try {
                    $model = DokumenArsip::withTrashed()->updateOrCreate(['kode_arsip' => $kode], $payload);
                    $model->wasRecentlyCreated ? $created++ : $updated++;
                } catch (Throwable $e) {
                    $this->catatSkip($skipped, $kode, 'gagal ditulis ke basis data: '.mb_substr($e->getMessage(), 0, 200));
                }
            }
        });

        return [
            'dibuat' => $created,
            'diperbarui' => $updated,
            'dilewati' => count($skipped),
            'detil_lewati' => array_slice($skipped, 0, self::DETAIL_CAP),
            'peringatan' => $peringatan,
        ];
    }

    /**
     * @param  array<int, array{alamat: string, alasan: string}>  $skipped
     */
    private function catatSkip(array &$skipped, string $alamat, string $alasan): void
    {
        if (count($skipped) < self::DETAIL_CAP) {
            $skipped[] = ['alamat' => $alamat, 'alasan' => $alasan];
        }
    }
}
