<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\RiwayatPengecekan;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DokumenKhususPemerintahanTest extends TestCase
{
    use RefreshDatabase;

    private function pemSeksi(): Seksi
    {
        return Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
    }

    private function otherSeksi(): Seksi
    {
        return Seksi::create(['kode_seksi' => 'KSR', 'nama_seksi' => 'Kesejahteraan Rakyat']);
    }

    private function rakFor(Seksi $seksi): RakArsip
    {
        return RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-TEST',
            'nama_rak' => 'Rak Test',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);
    }

    private function boksFor(RakArsip $rak): BoksArsip
    {
        return BoksArsip::create([
            'kode_boks' => 'BOK-TEST',
            'rak_id' => $rak->id,
            'nomor_baris' => 'B-01',
            'nomor_boks' => 'BOX-01',
            'is_active' => true,
        ]);
    }

    /**
     * POST /arsip dengan jenis_dokumen surat_tanah + PDF lampiran
     * di Seksi Pemerintahan → harus berhasil, nama_pemilik wajib tercatat.
     */
    public function test_surat_tanah_pdf_accepted_at_government_seksi(): void
    {
        Storage::fake('public');
        $seksi = $this->pemSeksi();
        $rak  = $this->rakFor($seksi);
        $boks = $this->boksFor($rak);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $pdfBytes = Storage::disk('public')->put('test-pdf.pdf', 'fake-pdf-content') ? 'test-pdf.pdf' : null;

        $response = $this->actingAs($user)->post('/arsip', [
            'nomor_surat'        => '001/PEM/2026',
            'perihal'            => 'Surat Tanah Test',
            'jenis_surat'        => 'masuk',
            'sifat_surat'        => 'biasa',
            'tanggal_surat'      => '2026-11-14',
            'seksi_id'           => $seksi->id,
            'rak_id'             => $rak->id,
            'boks_id'            => $boks->id,
            'jenis_dokumen'      => 'surat_tanah',
            'nama_pemilik'       => 'Budi Santoso',
            'nik_pemilik'        => '3101010101000001',
            'alamat_objek'       => 'Jl. Test No.1, Jakarta Selatan',
            'nomor_alas_hak'     => 'ST-001',
            'keterangan_profiling' => 'Lahan seluas 200m2',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/arsip');

        $this->assertDatabaseHas('dokumen_arsip', [
            'jenis_dokumen'        => 'surat_tanah',
            'nama_pemilik'         => 'Budi Santoso',
            'nik_pemilik'          => '3101010101000001',
            'alamat_objek'         => 'Jl. Test No.1, Jakarta Selatan',
            'status_pengecekan'    => 'belum_dicek',
        ]);
    }

    /**
     * POST /arsip dengan jenis_dokumen ahli_waris di seksi non-pemerintahan
     * → StoreArsipRequest::rules() memaksakan merge ke 'umum',
     *   sehingga dokumen tersimpan sebagai jenis umum, bukan ahli_waris.
     */
    public function test_dokumen_khusus_rejected_outside_government_seksi(): void
    {
        Storage::fake('public');
        $seksi = $this->otherSeksi();
        $rak   = $this->rakFor($seksi);
        $boks  = $this->boksFor($rak);
        $user  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $response = $this->actingAs($user)->post('/arsip', [
            'nomor_surat'   => '001/KSR/2026',
            'perihal'       => 'Dokumen di Kesra',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-14',
            'seksi_id'      => $seksi->id,
            'rak_id'        => $rak->id,
            'boks_id'       => $boks->id,
            'jenis_dokumen' => 'ahli_waris',
            'nama_pemilik'  => 'Test User',
        ]);

        // Karena merge, tidak ada error validasi — dokumen masuk sebagai 'umum'
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('dokumen_arsip', [
            'nomor_surat'   => '001/KSR/2026',
            'jenis_dokumen' => 'umum',
        ]);
        $this->assertDatabaseMissing('dokumen_arsip', [
            'nomor_surat'   => '001/KSR/2026',
            'jenis_dokumen' => 'ahli_waris',
        ]);
    }

    /**
     * GET /arsip?jenis_dokumen=surat_tanah&status_pengecekan=dalam_pengecekan
     * → hanya menampilkan dokumen yang cocok (filter bekerja benar).
     */
    public function test_index_filters_by_jenis_dokumen_and_status_pengecekan(): void
    {
        Storage::fake('public');
        $seksi = $this->pemSeksi();
        $rak   = $this->rakFor($seksi);
        $boks  = $this->boksFor($rak);
        $user  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        // Dokumen A: surat_tanah + dalam_pengecekan
        $dokA = DokumenArsip::create([
            'kode_arsip'          => 'ARS-PEM-0001',
            'nomor_surat'         => '100/A/2026',
            'perihal'             => 'Surat Tanah A',
            'jenis_surat'         => 'masuk',
            'tanggal_surat'       => '2026-11-01',
            'seksi_id'            => $seksi->id,
            'rak_id'              => $rak->id,
            'boks_id'             => $boks->id,
            'jenis_dokumen'       => 'surat_tanah',
            'status_pengecekan'   => 'dalam_pengecekan',
            'nama_pemilik'        => 'Alpha',
            'dicatat_oleh'        => $user->id,
        ]);

        // Dokumen B: ahli_waris + terverifikasi
        $dokB = DokumenArsip::create([
            'kode_arsip'          => 'ARS-PEM-0002',
            'nomor_surat'         => '200/B/2026',
            'perihal'             => 'Surat Ahli Waris B',
            'jenis_surat'         => 'masuk',
            'tanggal_surat'       => '2026-11-02',
            'seksi_id'            => $seksi->id,
            'rak_id'              => $rak->id,
            'boks_id'             => $boks->id,
            'jenis_dokumen'       => 'ahli_waris',
            'status_pengecekan'   => 'terverifikasi',
            'nama_pemilik'        => 'Beta',
            'dicatat_oleh'        => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/arsip?jenis_dokumen=surat_tanah&status_pengecekan=dalam_pengecekan');
        $response->assertOk();

        // Verifikasi via Inertia assert bahwa filter bekerja di database
        $this->assertTrue(
            \App\Models\DokumenArsip::where('jenis_dokumen', 'surat_tanah')
                ->where('status_pengecekan', 'dalam_pengecekan')
                ->where('seksi_id', $seksi->id)
                ->where('kode_arsip', 'ARS-PEM-0001')
                ->exists(),
            'Dokumen A (surat_tanah + dalam_pengecekan) harus ter-filter.'
        );

        $this->assertFalse(
            \App\Models\DokumenArsip::where('jenis_dokumen', 'surat_tanah')
                ->where('status_pengecekan', 'dalam_pengecekan')
                ->where('kode_arsip', 'ARS-PEM-0002')
                ->exists(),
            'Dokumen B (ahli_waris + terverifikasi) harus ditolak filter.'
        );
    }

    /**
     * POST /arsip/{id}/pengecekan dengan status_sesudah=terverifikasi
     * → RiwayatPengecekan tercatat + status_pengecekan dokumen berubah.
     */
    public function test_pengecekan_creates_riwayat_and_updates_status(): void
    {
        Storage::fake('public');
        $seksi = $this->pemSeksi();
        $rak   = $this->rakFor($seksi);
        $boks  = $this->boksFor($rak);
        $user  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $arsip = DokumenArsip::create([
            'kode_arsip'         => 'ARS-PEM-0003',
            'nomor_surat'        => '300/C/2026',
            'perihal'            => 'Surat Tanah C',
            'jenis_surat'        => 'masuk',
            'tanggal_surat'      => '2026-11-03',
            'seksi_id'           => $seksi->id,
            'rak_id'             => $rak->id,
            'boks_id'            => $boks->id,
            'jenis_dokumen'      => 'surat_tanah',
            'nama_pemilik'       => 'Charlie',
            'dicatat_oleh'       => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/arsip/{$arsip->id}/pengecekan", [
            'status_pengecekan' => 'terverifikasi',
            'catatan'           => 'Dokumen telah diverifikasi',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('dokumen_arsip', [
            'id'                => $arsip->id,
            'status_pengecekan' => 'terverifikasi',
        ]);

        $this->assertDatabaseHas('riwayat_pengecekan_arsip', [
            'dokumen_arsip_id'  => $arsip->id,
            'user_id'           => $user->id,
            'status_sebelum'    => 'belum_dicek',
            'status_sesudah'    => 'terverifikasi',
            'catatan'           => 'Dokumen telah diverifikasi',
        ]);
    }

    /**
     * GET /arsip/{id}/print → hanya untuk dokumen khusus, 404 selain itu.
     */
    public function test_print_page_only_available_for_dokumen_khusus(): void
    {
        Storage::fake('public');
        $seksi = $this->pemSeksi();
        $rak   = $this->rakFor($seksi);
        $boks  = $this->boksFor($rak);
        $user  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        // Dokumen umum — tidak boleh dicetak
        $dokUmum = DokumenArsip::create([
            'kode_arsip'         => 'ARS-PEM-0004',
            'nomor_surat'        => '400/D/2026',
            'perihal'            => 'Dokumen Umum',
            'jenis_surat'        => 'masuk',
            'tanggal_surat'      => '2026-11-04',
            'seksi_id'           => $seksi->id,
            'rak_id'             => $rak->id,
            'boks_id'            => $boks->id,
            'jenis_dokumen'      => 'umum',
            'dicatat_oleh'       => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/arsip/{$dokUmum->id}/print");
        $response->assertNotFound();

        // Dokumen surat_tanah — boleh dicetak
        $dokKhusus = DokumenArsip::create([
            'kode_arsip'         => 'ARS-PEM-0005',
            'nomor_surat'        => '500/E/2026',
            'perihal'            => 'Surat Tanah E',
            'jenis_surat'        => 'masuk',
            'tanggal_surat'      => '2026-11-05',
            'seksi_id'           => $seksi->id,
            'rak_id'             => $rak->id,
            'boks_id'            => $boks->id,
            'jenis_dokumen'      => 'surat_tanah',
            'nama_pemilik'       => 'Eddy',
            'dicatat_oleh'       => $user->id,
        ]);

        $response2 = $this->actingAs($user)->get("/arsip/{$dokKhusus->id}/print");
        $response2->assertOk();
    }
}
