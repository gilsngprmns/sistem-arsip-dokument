<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfEncryptionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function setupSeksi(): array
    {
        $pemSeksi = Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
        $ksrSeksi = Seksi::create(['kode_seksi' => 'KSR', 'nama_seksi' => 'Kesejahteraan Rakyat']);

        $pemRak = RakArsip::create([
            'seksi_id'  => $pemSeksi->id,
            'kode_rak'  => 'RAK-PEM',
            'nama_rak'  => 'Rak Pem',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $pemBoks = BoksArsip::create([
            'kode_boks'    => 'BOK-PEM-01',
            'rak_id'       => $pemRak->id,
            'nomor_baris'  => 'B-01',
            'nomor_boks'   => 'BOX-01',
            'is_active'    => true,
        ]);

        $ksrRak = RakArsip::create([
            'seksi_id'  => $ksrSeksi->id,
            'kode_rak'  => 'RAK-KSR',
            'nama_rak'  => 'Rak Ksr',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $ksrBoks = BoksArsip::create([
            'kode_boks'    => 'BOK-KSR-01',
            'rak_id'       => $ksrRak->id,
            'nomor_baris'  => 'B-01',
            'nomor_boks'   => 'BOX-01',
            'is_active'    => true,
        ]);

        return compact('pemSeksi', 'ksrSeksi', 'pemRak', 'pemBoks', 'ksrRak', 'ksrBoks');
    }

    private function makePdfFile(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('surat_test.pdf', '%PDF-1.4 fake pdf content');
    }

    /**
     * Simpan file terenkripsi ke fake local disk dan return arsip.
     */
    private function createEncryptedArsip(
        array $ctx,
        User $user,
        string $kode,
        string $path,
        string $content = 'pdf-content',
        ?int $ksrSeksiId = null,
    ): DokumenArsip {
        $seksiId = $ksrSeksiId ?? $ctx['pemSeksi']->id;
        $rakId   = $ksrSeksiId ? $ctx['ksrRak']->id : $ctx['pemRak']->id;
        $boksId  = $ksrSeksiId ? $ctx['ksrBoks']->id : $ctx['pemBoks']->id;

        $arsip = DokumenArsip::create([
            'kode_arsip'     => $kode,
            'nomor_surat'    => $kode,
            'perihal'        => 'Test Enkripsi',
            'jenis_surat'    => 'masuk',
            'tanggal_surat'  => '2026-11-15',
            'seksi_id'       => $seksiId,
            'rak_id'         => $rakId,
            'boks_id'        => $boksId,
            'jenis_dokumen'  => 'umum',
            'dicatat_oleh'   => $user->id,
            'is_encrypted'   => true,
            'file_path'      => $path,
            'file_hash'      => hash('sha256', $content),
        ]);

        Storage::disk('local')->put($path, Crypt::encryptString($content));

        return $arsip;
    }

    // ── Test 1: Upload surat_tanah → auto-encrypt ─────────────────────────

    /**
     * POST /arsip dengan jenis_dokumen=surat_tanah + lampiran PDF
     * → is_encrypted=true di DB, file ada di disk local/arsip-encrypted/.
     */
    public function test_surat_tanah_auto_encrypted_on_upload(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $ctx  = $this->setupSeksi();
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $ctx['pemSeksi']->id]);

        $response = $this->actingAs($user)->post('/arsip', [
            'nomor_surat'        => '001/PEM-ENC/2026',
            'perihal'            => 'Surat Tanah Enkripsi Test',
            'jenis_surat'        => 'masuk',
            'sifat_surat'        => 'biasa',
            'tanggal_surat'      => '2026-11-15',
            'seksi_id'           => $ctx['pemSeksi']->id,
            'rak_id'             => $ctx['pemRak']->id,
            'boks_id'            => $ctx['pemBoks']->id,
            'jenis_dokumen'      => 'surat_tanah',
            'nama_pemilik'       => 'Test Owner',
            'nik_pemilik'        => '3101010101000099',
            'alamat_objek'       => 'Jl. Test',
            'nomor_alas_hak'     => 'ST-ENC-001',
            'lampiran'           => $this->makePdfFile(),
        ]);

        $response->assertSessionHasNoErrors();

        $arsip = DokumenArsip::where('nomor_surat', '001/PEM-ENC/2026')->first();
        $this->assertNotNull($arsip);
        $this->assertTrue($arsip->is_encrypted, 'File surat_tanah harus terenkripsi otomatis.');
        $this->assertNotNull($arsip->file_hash, 'Hash SHA-256 harus tersimpan.');
        $this->assertNotNull($arsip->encrypted_at, 'Timestamp enkripsi harus tercatat.');

        Storage::disk('local')->assertExists($arsip->file_path);
    }

    // ── Test 2: Akses admin → 200 + ActivityLog tercatat ────────────────

    /**
     * GET /arsip/{id}/file oleh admin terhadap dokumen terenkripsi
     * → 200, Content-Type application/pdf, ActivityLog tercatat.
     */
    public function test_admin_can_access_encrypted_file_and_audit_log_recorded(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $ctx   = $this->setupSeksi();
        $admin = User::factory()->create(['role' => 'admin']);

        $arsip = $this->createEncryptedArsip(
            $ctx, $admin, 'ARS-ENC-001',
            'arsip-encrypted/admin-test.enc',
            'admin-test-pdf-content',
        );

        $response = $this->actingAs($admin)->get("/arsip/{$arsip->id}/file");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $cacheHeader = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheHeader);
        $this->assertStringContainsString('no-cache', $cacheHeader);
        $this->assertStringContainsString('must-revalidate', $cacheHeader);

        $this->assertDatabaseHas('activity_logs', [
            'action'      => 'arsip.file.access',
            'user_id'     => $admin->id,
            'target_id'   => $arsip->id,
            'target_type' => 'dokumen',
        ]);
    }

    // ── Test 3: Staf seksi lain ditolak (403) ────────────────────────────

    /**
     * GET /arsip/{id}/file oleh staf KSR terhadap dokumen di seksi KSR → 200.
     * GET /arsip/{id}/file oleh staf PEM terhadap dokumen KSR → 403.
     */
    public function test_other_seksi_staff_cannot_access_encrypted_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $ctx     = $this->setupSeksi();
        $pemUser = User::factory()->create(['role' => 'staf', 'seksi_id' => $ctx['pemSeksi']->id]);
        $ksrUser = User::factory()->create(['role' => 'staf', 'seksi_id' => $ctx['ksrSeksi']->id]);

        // Dokumen di seksi KSR, terenkripsi, file di fake disk
        $arsip = $this->createEncryptedArsip(
            $ctx, $ksrUser, 'ARS-ENC-002',
            'arsip-encrypted/other-seksi.enc',
            'other-seksi-content',
            ksrSeksiId: $ctx['ksrSeksi']->id,
        );

        // Staff KSR (pemilik) boleh akses — file ada di fake disk
        $responseOwner = $this->actingAs($ksrUser)->get("/arsip/{$arsip->id}/file");
        $responseOwner->assertOk();

        // Staff PEM ditolak
        $responseOther = $this->actingAs($pemUser)->get("/arsip/{$arsip->id}/file");
        $responseOther->assertForbidden();
    }

    // ── Test 4: File publik (non-encrypted) tetap via endpoint ──────────

    /**
     * GET /arsip/{id}/file terhadap dokumen non-encrypted
     * → 200, file di-read dari disk public, tanpa ActivityLog.
     */
    public function test_public_file_served_through_download_endpoint(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $ctx  = $this->setupSeksi();
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $ctx['pemSeksi']->id]);

        $arsip = DokumenArsip::create([
            'kode_arsip'     => 'ARS-ENC-003',
            'nomor_surat'    => '003/PUB/2026',
            'perihal'        => 'Dokumen Publik',
            'jenis_surat'    => 'masuk',
            'tanggal_surat'  => '2026-11-15',
            'seksi_id'       => $ctx['pemSeksi']->id,
            'rak_id'         => $ctx['pemRak']->id,
            'boks_id'        => $ctx['pemBoks']->id,
            'jenis_dokumen'  => 'umum',
            'dicatat_oleh'   => $user->id,
            'file_path'      => 'lampiran-arsip/pub-test.pdf',
            'is_encrypted'   => false,
        ]);

        Storage::disk('public')->put('lampiran-arsip/pub-test.pdf', '%PDF-1.4 public');

        $response = $this->actingAs($user)->get("/arsip/{$arsip->id}/file");
        $response->assertOk();

        $this->assertDatabaseMissing('activity_logs', [
            'action'    => 'arsip.file.access',
            'user_id'   => $user->id,
            'target_id' => $arsip->id,
        ]);
    }

    // ── Test 5: destroy (admin) hapus file terenkripsi dari disk ────────

    /**
     * DELETE /arsip/{id} oleh admin terhadap dokumen terenkripsi
     * → file terenkripsi dihapus dari disk, record terhapus permanen.
     */
    public function test_admin_destroy_removes_encrypted_file_from_disk(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $ctx   = $this->setupSeksi();
        $admin = User::factory()->create(['role' => 'admin']);

        $arsip = $this->createEncryptedArsip(
            $ctx, $admin, 'ARS-ENC-004',
            'arsip-encrypted/del-test.enc',
            'del-test-content',
        );

        Storage::disk('local')->assertExists($arsip->file_path);

        $response = $this->actingAs($admin)->delete("/arsip/{$arsip->id}");
        $response->assertRedirect();

        Storage::disk('local')->assertMissing($arsip->file_path);

        $this->assertNull(
            DokumenArsip::withTrashed()->find($arsip->id),
            'Dokumen harus terhapus permanen setelah admin destroy.'
        );
    }
}
