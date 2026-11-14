<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\Seksi;
use App\Models\User;
use App\Services\Backup\BackupService;
use App\Services\Backup\BundleBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Seksi $seksi;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->seksi = Seksi::create(['nama_seksi' => 'Pemerintahan']);
        $this->tempDir = sys_get_temp_dir().'/backup-test-'.uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir.'/*') ?: []);
        @rmdir($this->tempDir);
        parent::tearDown();
    }

    /** Buat satu berkas berisi JSON; kembalikan [filePath, fileName]. */
    private function writeJson(string $nama, string $isi): string
    {
        $path = $this->tempDir.'/'.$nama;
        file_put_contents($path, $isi);
        return $path;
    }

    private function uploaded(string $path, string $nama): UploadedFile
    {
        return new UploadedFile($path, $nama, 'application/json', null, true);
    }

    private function envelope(array $data): array
    {
        return app(BackupService::class)->envelope($data, 'unit-test');
    }

    public function test_export_menghasilkan_envelope_v1_dan_tercatat_di_activity_log(): void
    {
        $dok = DokumenArsip::factory()->create(['seksi_id' => $this->seksi->id, 'dicatat_oleh' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->get('/backup/export');
        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertIsString($disposition);
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringContainsString('backup-arsip-', $disposition);

        $envelope = json_decode($response->getContent(), true);

        $this->assertSame(BackupService::FORMAT, $envelope['format']);
        $this->assertSame(1, $envelope['version']);
        $this->assertSame(1, $envelope['meta']['jumlah_data']);
        $this->assertTrue(hash_equals(
            $envelope['meta']['checksum_sha256'],
            BackupService::canonicalHash($envelope['data']),
        ), 'Checksum envelope harus sesuai bagian data');

        $this->assertNotEmpty(ActivityLog::where('action', 'backup.export')->get());
        $meta = ActivityLog::where('action', 'backup.export')->first()->metadata;
        $this->assertSame('sukses', $meta['status'] ?? null);
    }

    public function test_import_happy_path_mencatat_metrik_dan_checksum_terverifikasi(): void
    {
        $dibuat = DokumenArsip::factory()->make([
            'seksi_id' => $this->seksi->id,
            'kode_arsip' => 'BARU-001',
        ])->toArray();

        $envelope = $this->envelope([
            $dibuat,
            ['kode_arsip' => 'BARU-002', 'seksi_id' => $this->seksi->id, 'nomor_surat' => '002/RS', 'perihal' => 'b', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01'],
        ]);

        $path = $this->writeJson('ok.json', json_encode($envelope));
        $response = $this->actingAs($this->admin)->post('/backup/import', [
            'backup_file' => $this->uploaded($path, 'ok.json'),
        ]);

        $response->assertRedirect(route('backup.index'));
        $response->assertSessionHas('success');

        $this->assertSame(2, DokumenArsip::where('kode_arsip', 'like', 'BARU-%')->count());

        $result = session('backup_result');
        $this->assertSame(2, $result['dibuat']);
        $this->assertSame(0, $result['diperbarui']);
        $this->assertSame(0, $result['dilewati']);
        $this->assertSame('lulus', $result['periksa_checksum']);
    }

    public function test_import_menolak_checksum_salah_dan_tak_menyentuh_db(): void
    {
        DokumenArsip::factory()->create(['seksi_id' => $this->seksi->id]);
        $before = DokumenArsip::count();

        $envelope = $this->envelope([
            ['kode_arsip' => 'X-1', 'seksi_id' => $this->seksi->id, 'nomor_surat' => 's', 'perihal' => 'p', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01'],
        ]);
        // Ubah isi data TANPA menghitung ulang checksum → hash mismatch
        $envelope['data'][0]['perihal'] = 'diancam';

        $path = $this->writeJson('korup.json', json_encode($envelope));
        $response = $this->actingAs($this->admin)->post('/backup/import', [
            'backup_file' => $this->uploaded($path, 'korup.json'),
        ]);

        $response->assertSessionHasErrors('backup_file');
        $response->assertSessionHas('error');
        $this->assertSame($before, DokumenArsip::count(), 'DB tidak boleh berubah saat verifikasi gagal');

        $penolakanTercatat = ActivityLog::where('action', 'backup.import')->get()
            ->contains(fn ($log) => ($log->metadata['status'] ?? null) === 'gagal_verifikasi');
        $this->assertTrue($penolakanTercatat, 'Penolakan wajib tercatat di ActivityLog');
    }

    public function test_import_menerima_array_mentah_v0_dengan_penanda_tanpa_checksum(): void
    {
        $data = [['kode_arsip' => 'V0-1', 'seksi_id' => $this->seksi->id, 'nomor_surat' => 's', 'perihal' => 'p', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01']];

        $path = $this->writeJson('legacy.json', json_encode($data));
        $response = $this->actingAs($this->admin)->post('/backup/import', [
            'backup_file' => $this->uploaded($path, 'legacy.json'),
        ]);

        $response->assertRedirect(route('backup.index'));
        $this->assertSame(1, DokumenArsip::where('kode_arsip', 'V0-1')->count());
        $this->assertSame('v0 (tanpa checksum)', session('backup_result')['versi_format']);
        $this->assertSame('tidak tersedia', session('backup_result')['periksa_checksum']);
    }

    public function test_baris_bermasalah_dilewati_dan_dicatat_bukan_menggagalkan_semua(): void
    {
        $metrik = app(BackupService::class)->importRows([
            // Valid
            ['kode_arsip' => 'OK-1', 'seksi_id' => $this->seksi->id, 'nomor_surat' => 's', 'perihal' => 'p', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01'],
            // FK gantung
            ['kode_arsip' => 'FKG', 'seksi_id' => 999999, 'nomor_surat' => 's', 'perihal' => 'p', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01'],
            // Duplikat di dalam file → entri kedua dilewati
            ['kode_arsip' => 'OK-1', 'seksi_id' => $this->seksi->id, 'nomor_surat' => 's', 'perihal' => 'p', 'jenis_surat' => 'masuk', 'tanggal_surat' => '2026-01-01'],
        ]);

        $this->assertSame(1, $metrik['dibuat']);
        $this->assertSame(2, $metrik['dilewati']);

        $alasannya = collect($metrik['detil_lewati'])->map(fn ($d) => $d['alasan'])->all();
        $this->assertContains('merujuk seksi (id 999999) yang tidak ada di sistem ini', $alasannya);
        $this->assertContains('kode_arsip duplikat di dalam file — entri pertama yang dipakai', $alasannya);
    }

    public function test_file_terlalu_besar_ditolak_validasi(): void
    {
        // File 10241 KB melewati batas max:10240 (10 MB) → ditolak validasi,
        // basis data tetap utuh.
        $response = $this->actingAs($this->admin)->post('/backup/import', [
            'backup_file' => UploadedFile::fake()->create('besar.json', 10241),
        ]);

        $response->assertSessionHasErrors('backup_file');
        $this->assertSame(0, DokumenArsip::count());
    }

    public function test_non_admin_ditolak_membuka_backup(): void
    {
        $staf = User::factory()->create(['role' => 'staf']);
        $this->actingAs($staf)->get('/backup')->assertForbidden();
    }

    /**
     * Skenario 7 — round-trip bundel ZIP di DB kosong: seksi, lokasi, rak,
     * arsip (dengan riwayat terembed), dan file fisik dipulihkan tanpa
     * FK gantung.
     */
    public function test_round_trip_bundel_zip_di_db_kosong(): void
    {
        $bundles = app(BundleBackupService::class);

        // — Data sumber: satu pohon referensi lengkap + 1 arsip ber-riwayat & ber-file —
        $rak = RakArsip::create([
            'kode_rak' => 'RAK-01', 'nama_rak' => 'Rak Utama',
        ]);
        $boks = BoksArsip::create([
            'kode_boks' => 'BOK-RAK-01', 'rak_id' => $rak->id,
            'nomor_baris' => 'B-01', 'nomor_boks' => 'BOX-01',
        ]);
        $dok = DokumenArsip::factory()->create([
            'seksi_id' => $this->seksi->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
            'kode_arsip' => 'RT-001',
            'dicatat_oleh' => $this->admin->id,
            'file_path' => 'lampiran-arsip/bukti.png',
        ]);

        // Riwayat yang user-nya (admin) hidup di target → wajib disalin utuh
        RiwayatSirkulasi::create([
            'dokumen_arsip_id' => $dok->id,
            'user_id' => $this->admin->id,
            'jenis_aksi' => 'dipinjam',
            'pihak_terkait' => 'Kecamatan',
            'keterangan' => 'Peminjaman uji',
            'waktu_aksi' => now()->subDays(2)->toDateTimeString(),
        ]);

        // File fisik lampiran di storage publik agar ikut terkumpul
        Storage::disk('public')->put('lampiran-arsip/bukti.png', 'KONTEN-UJI');

        // — Ekspor bundel ke folder temp —
        $hasil = $bundles->exportBundle(
            generator: 'roundtrip-test',
            includeFiles: true,
            outDir: $this->tempDir,
        );
        $zipPath = $hasil['path'];
        $this->assertFileExists($zipPath);
        $this->assertSame(1, $hasil['rincian']['dokumen_arsip']);
        $this->assertSame(1, $hasil['rincian']['riwayat_sirkulasi']);
        // 1 lampiran (bukti.png) + 1 QR (dibuat otomatis oleh Observer)
        $this->assertSame(2, $hasil['rincian']['file_fisik']);

        // — Kosongkan DB (simulasi instalasi bersih) lalu restore —
        $this->truncateSemuaTabel();

        // Recreate admin DENGAN id yang sama (id sumber) agar referensi
        // dicatat_oleh & riwayat user_id dapat dipetakan di sisi tujuan.
        $adminBaru = User::factory()->create([
            'id' => $this->admin->id,
            'role' => 'admin',
            'email' => 'restore-admin@example.com',
        ]);

        $ekstrak = $bundles->extractBundle($zipPath);
        $metrik = $bundles->importBundle($ekstrak['payload'], $ekstrak['files_root'], $ekstrak['daftar_file']);
        $bundles->cleanupExtraction($ekstrak['temp_dir']);

        // — Referensi terbentuk tanpa FK gantung —
        $this->assertSame(1, Seksi::count());
        $this->assertSame('Pemerintahan', Seksi::first()->nama_seksi);
        $this->assertSame(1, BoksArsip::where('kode_boks', 'BOK-RAK-01')->count());
        $rakBaru = RakArsip::where('kode_rak', 'RAK-01')->first();
        $this->assertNotNull($rakBaru);
        $boksBaru = BoksArsip::where('kode_boks', 'BOK-RAK-01')->first();
        $this->assertSame($rakBaru->id, $boksBaru->rak_id);

        // — Arsip + riwayat + file fisik —
        $dokBaru = DokumenArsip::where('kode_arsip', 'RT-001')->first();
        $this->assertNotNull($dokBaru);
        $this->assertSame($rakBaru->id, $dokBaru->rak_id);
        $this->assertSame($adminBaru->id, $dokBaru->dicatat_oleh, 'pencatat remap ke user hidup di target');
        $this->assertSame(1, RiwayatSirkulasi::count());
        $this->assertSame('dipinjam', RiwayatSirkulasi::first()->jenis_aksi);
        $this->assertSame('KONTEN-UJI', (string) Storage::disk('public')->get('lampiran-arsip/bukti.png'));

        $this->assertSame($boksBaru->id, $dokBaru->boks_id, 'boks_id remap ke boks hidup di target');

        $this->assertSame(1, $metrik['dibuat']);
        $this->assertSame(1, $metrik['riwayat']['disalin']);
        $this->assertSame(2, $metrik['file_dipulihkan'], 'lampiran + QR dipulihkan');
        $this->assertNotEmpty(
            Storage::disk('public')->files('qrcodes'),
            'QR arsip harus ikut dipulihkan ke storage publik',
        );
    }

    /**
     * Hapus seluruh isi tabel (simulasi instalasi DB bersih). FK dinonaktifkan
     * selama penghapusan agar urutan tabel tidak menjadi masalah di SQLite.
     */
    private function truncateSemuaTabel(): void
    {
        \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF');
        try {
            foreach ([
                'riwayat_sirkulasi', 'activity_logs', 'dokumen_arsip',
                'rak_arsip', 'boks_arsip', 'seksi', 'users',
            ] as $tabel) {
                \Illuminate\Support\Facades\DB::table($tabel)->truncate();
            }
        } finally {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
        }
    }
}
