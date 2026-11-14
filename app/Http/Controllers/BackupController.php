<?php

namespace App\Http\Controllers;

use App\Models\DokumenArsip;
use App\Services\Backup\BackupService;
use App\Services\Backup\BundleBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly BundleBackupService $bundles,
    ) {
    }

    /**
     * Tampilkan halaman cadangan & pemulihan.
     */
    public function index(Request $request): Response
    {
        $limit = max(10, min(50, $request->integer('limit') ?: 10));
        $tersimpan = $this->bundles->listBundles($limit);
        $adaLebihLama = count($tersimpan) === $limit;

        // Indeks "cadangan terakhir" — > 7 hari memicu peringatan merah di UI
        $terbaru = $tersimpan[0]['diambil_pada'] ?? null;
        $umurHari = $terbaru !== null ? now()->setTime(0, 0, 0)->diff(
            \Carbon\Carbon::parse($terbaru)->setTime(0, 0, 0)
        )->days : null;

        return Inertia::render('Backup/Index', [
            'totalArsip' => DokumenArsip::withTrashed()->count(),
            'aktifArsip' => DokumenArsip::count(),
            'daftarBackup' => $tersimpan,
            'limit' => $limit,
            'adaLebihLama' => $adaLebihLama,
            'cadanganTerakhir' => [
                'diambil_pada' => $terbaru,
                'umur_hari' => $umurHari,
                'menunggak' => $umurHari !== null && $umurHari > 7,
            ],
        ]);
    }

    /**
     * Ekspor seluruh arsip (termasuk soft delete) ke berkas cadangan.
     *
     * Hasil berupa envelope self-describing v1: berisi metadata (pembuat,
     * waktu, jumlah baris) + checksum SHA-256 bagian data, agar berkas yang
     * berpindah antar mesin bisa dibuktikan utuh sebelum dipulihkan.
     */
    public function export(): BaseResponse
    {
        $data = $this->backups->buildData();
        $envelope = $this->backups->envelope($data, 'ekspor-web');

        $filename = 'backup-arsip-'.now()->format('Ymd-His').'.json';

        session()->flash('activity_log_metadata', [
            'jenis' => 'ekspor',
            'status' => 'sukses',
            'nama_file' => $filename,
            'versi_format' => $envelope['version'],
            'jumlah_data' => $envelope['meta']['jumlah_data'],
            'checksum_sha256' => $envelope['meta']['checksum_sha256'],
        ]);

        return response(
            json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ],
        );
    }

    /**
     * Unduh satu cadangan tersimpan dari folder cadangan.
     *
     * Hanya nama yang benar-benar ada di folder cadangan yang boleh
     * dibuka (dicek langsung dari disk) — cegah path traversal.
     */
    public function files(string $file): BaseResponse
    {
        $dir = config('backup.path');
        $jalur = realpath($dir.'/'.$file);

        // Nama harus berada di dalam folder cadangan & sesuai pola backup
        if ($jalur === false || ! str_starts_with($jalur, realpath($dir)) || ! preg_match('/^backup-arsip-.+\.(json|zip)$/', $file)) {
            abort(404);
        }

        return response()->download($jalur, $file);
    }

    /**
     * Buat cadangan lengkap (bundel ZIP) "sekarang", lewat jalur web admin.
     */
    public function run(): RedirectResponse
    {
        try {
            // Default command sudah menyertakan file fisik; --no-files untuk membatasinya
            $status = Artisan::call('arsip:cadangkan');
            $output = Artisan::output();
            if ($status !== 0) {
                return back()->with('error', 'Cadangan tidak berhasil dibuat: '.$output);
            }
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal membuat cadangan: '.$e->getMessage());
        }

        session()->flash('activity_log_metadata', [
            'jenis' => 'ekspor',
            'status' => 'sukses',
            'catatan' => 'cadangan terjadwal dibuat manual dari /backup',
        ]);

        return redirect()
            ->route('backup.index')
            ->with('success', 'Cadangan lengkap telah dibuat dan disimpan.');
    }

    /**
     * Pulihkan data dari berkas cadangan.
     *
     * Mendukung dua wujud:
     * - .zip  : bundel lengkap versi 2 (data + referensi + file fisik),
     * - .json : envelope v1 (data saja) atau array v0 lama.
     *
     * Verifikasi integritas dilakukan SEBELUM menyentuh basis data; baris
     * bermasalah di-skip & dicatat, bukan menggagalkan seluruh pemulihan.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file' => ['required', 'file', 'max:40960', 'mimes:json,zip'],
        ], [
            'backup_file.required' => 'Pilih file cadangan (ZIP atau JSON) terlebih dahulu.',
            'backup_file.mimes' => 'File cadangan harus berformat ZIP atau JSON.',
        ]);

        $upload = $request->file('backup_file');
        $namaFile = $upload->getClientOriginalName();
        $ext = strtolower($upload->getClientOriginalExtension());

        // — Jalur bundel ZIP (versi 2) —
        if ($ext === 'zip') {
            $pathUpload = $upload->getRealPath();

            try {
                $hasil = $this->bundles->extractBundle($pathUpload);
            } catch (RuntimeException $e) {
                $this->catatPenolakanImport($namaFile, $e->getMessage());
                return back()->withInput()->withErrors(['backup_file' => $e->getMessage()])->with('error', $e->getMessage());
            }

            try {
                $metrik = $this->bundles->importBundle($hasil['payload'], $hasil['files_root'], $hasil['daftar_file']);
            } finally {
                $this->bundles->cleanupExtraction($hasil['temp_dir']);
            }

            $this->pasangHasilImport($namaFile, 'v2 (bundel ZIP)', 'lulus', $hasil['meta'] ?? [], $metrik);

            $pesan = sprintf(
                'Pemulihan bundel selesai: %d arsip dibuat, %d diperbarui, %d dilewati · %d riwayat disalin · %d file fisik dipulihkan.',
                $metrik['dibuat'],
                $metrik['diperbarui'],
                $metrik['dilewati'],
                $metrik['riwayat']['disalin'] ?? 0,
                $metrik['file_dipulihkan'],
            );

            return redirect()->route('backup.index')->with('success', $pesan);
        }

        // — Jalur JSON (envelope v1 / array v0) —
        $raw = file_get_contents($upload->getRealPath());

        try {
            $parsed = $this->backups->parse($raw);
        } catch (RuntimeException $e) {
            $this->catatPenolakanImport($namaFile, $e->getMessage());
            return back()->withInput()->withErrors(['backup_file' => $e->getMessage()])->with('error', $e->getMessage());
        }

        $metrik = $this->backups->importRows($parsed['rows']);
        $this->pasangHasilImport($namaFile, $parsed['versi'] === 'v0' ? 'v0 (tanpa checksum)' : 'v'.BackupService::VERSION,
            $parsed['versi'] === 'v1' ? 'lulus' : 'tidak tersedia', $parsed['meta'] ?? [], $metrik);

        $pesan = sprintf('Pemulihan selesai: %d arsip dibuat, %d diperbarui', $metrik['dibuat'], $metrik['diperbarui']);
        if ($metrik['dilewati'] > 0) {
            $pesan .= sprintf(', %d dilewati (rinciannya terlihat di bawah)', $metrik['dilewati']);
        }

        return redirect()->route('backup.index')->with('success', $pesan);
    }

    /**
     * Hapus satu atau beberapa berkas cadangan tersimpan.
     */
    public function destroyBulk(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => ['string', 'regex:/^backup-arsip-[0-9]{8}-[0-9]{6}\.(json|zip)$/'],
        ]);

        $files = $request->input('files');
        $dihapus = $this->bundles->deleteBundles($files);

        session()->flash('activity_log_metadata', [
            'jenis' => 'hapus_backup',
            'status' => 'sukses',
            'jumlah_dihapus' => $dihapus,
            'nama_file' => $files,
        ]);

        return redirect()->route('backup.index')
            ->with('success', "$dihapus berkas cadangan berhasil dihapus.");
    }

    private function pasangHasilImport(
        string $nama,
        string $versi,
        string $checksum,
        array $meta,
        array $metrik,
    ): void {
        session()->flash('backup_result', [
            'nama_file' => $nama,
            'versi_format' => $versi,
            'periksa_checksum' => $checksum,
            'total_baris_file' => $metrik['total_baris_file'] ?? ($meta['jumlah_data'] ?? null),
            ...$metrik,
        ]);

        session()->flash('activity_log_metadata', [
            'jenis' => 'impor',
            'status' => 'sukses',
            'nama_file' => $nama,
            'versi_format' => $versi,
            'jumlah_dibuat' => $metrik['dibuat'] ?? 0,
            'jumlah_diperbarui' => $metrik['diperbarui'] ?? 0,
            'jumlah_dilewati' => $metrik['dilewati'] ?? 0,
            'riwayat_disalin' => $metrik['riwayat']['disalin'] ?? 0,
            'file_dipulihkan' => $metrik['file_dipulihkan'] ?? 0,
        ]);
    }

    private function catatPenolakanImport(string $nama, string $alasan): void
    {
        session()->flash('activity_log_metadata', [
            'jenis' => 'impor',
            'status' => 'gagal_verifikasi',
            'nama_file' => $nama,
            'alasan' => $alasan,
        ]);
    }
}
