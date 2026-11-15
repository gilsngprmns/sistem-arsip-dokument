<?php

namespace Tests\Feature;

use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetensiArsipTest extends TestCase
{
    use RefreshDatabase;

    private int $seksiSeq = 0;

    private function buatArsipRetensi(User $user, array $overwrites = []): DokumenArsip
    {
        $this->seksiSeq++;
        $kode = 'RT' . $this->seksiSeq;
        $seksi = Seksi::create(['kode_seksi' => $kode, 'nama_seksi' => "Retensi Test $kode"]);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-' . $kode,
            'nama_rak' => 'Rak Retensi ' . $kode,
            'nomor_rak' => (string) $this->seksiSeq,
            'is_active' => true,
        ]);

        return DokumenArsip::create(array_merge([
            'kode_arsip' => 'RET-' . $kode . '-' . strtoupper((string) uniqid()),
            'nomor_surat' => 'R/1/2026',
            'perihal' => 'Dokumen uji retensi',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->subYears(6)->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'tersedia',
            'dicatat_oleh' => $user->id,
        ], $overwrites));
    }

    // ── Model: statusRetensi() ─────────────────────────────────────────────

    public function test_retensi_ringan_5_tahun_kedaluwarsa_setelah_6_tahun(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subYear()->toDateString(),
        ]);

        $this->assertSame(DokumenArsip::RETENSI_STATUS_KEDALUWARSA, $arsip->statusRetensi());
        $this->assertTrue($arsip->is_kedaluwarsa);
        $this->assertFalse($arsip->is_akan_kedaluwarsa);
    }

    public function test_retensi_akan_kedaluwarsa_dalam_30_hari(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->addDays(20)->toDateString(),
        ]);

        $this->assertSame(DokumenArsip::RETENSI_STATUS_AKAN_KEDALUWARSA, $arsip->statusRetensi());
        $this->assertTrue($arsip->is_akan_kedaluwarsa);
    }

    public function test_retensi_permanen_selalu_aktif(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'permanen',
            'tanggal_kedaluwarsa' => null,
        ]);

        $this->assertSame(DokumenArsip::RETENSI_STATUS_AKTIF, $arsip->statusRetensi());
        $this->assertFalse($arsip->is_kedaluwarsa);
        $this->assertFalse($arsip->is_akan_kedaluwarsa);
    }

    public function test_retensi_dipertahankan_menggeser_status_ke_dipertahankan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
            'retensi_dipertahankan' => true,
        ]);

        $this->assertSame(DokumenArsip::RETENSI_STATUS_DIPERTAHANKAN, $arsip->statusRetensi());
        $this->assertTrue($arsip->is_retensi_dipertahankan);
    }

    public function test_retensi_kustom_hitung_tanggal_kedaluwarsa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $tanggalDasar = now()->subYears(2);
        $arsip = $this->buatArsipRetensi($admin, [
            'tanggal_surat' => $tanggalDasar->toDateString(),
            'retensi_kategori' => 'kustom',
            'retensi_tahun' => 3,
        ]);
        $arsip->perbaruiTanggalKedaluwarsa($tanggalDasar);

        $expected = $tanggalDasar->copy()->addYears(3);
        $this->assertSame($expected->toDateString(), $arsip->tanggal_kedaluwarsa->toDateString());
    }

    public function test_permanen_koersi_retensi_tahun_ke_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'permanen',
            'retensi_tahun' => 10,
            'tanggal_surat' => now()->toDateString(),
        ]);
        $arsip->perbaruiTanggalKedaluwarsa(now());

        $this->assertNull($arsip->tanggal_kedaluwarsa);
        $this->assertNull($arsip->retensi_tahun);
    }

    // ── Filter status_retensi di Index ─────────────────────────────────────

    public function test_filter_status_retensi_kedaluwarsa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-KDLWRS',
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
        ]);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-AKTIF',
            'retensi_kategori' => 'permanen',
            'tanggal_kedaluwarsa' => null,
        ]);

        $response = $this->actingAs($admin)->get('/arsip?status_retensi=kedaluwarsa');
        $response->assertOk();
        $response->assertSee('RET-KDLWRS');
        $response->assertDontSee('RET-AKTIF');
    }

    public function test_filter_status_retensi_akan_kedaluwarsa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-30HARI',
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->addDays(15)->toDateString(),
        ]);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-JAUH',
            'retensi_kategori' => 'kustom',
            'retensi_tahun' => 50,
            'tanggal_kedaluwarsa' => now()->addYears(48)->toDateString(),
        ]);

        $response = $this->actingAs($admin)->get('/arsip?status_retensi=akan_kedaluwarsa');
        $response->assertOk();
        $response->assertSee('RET-30HARI');
        $response->assertDontSee('RET-JAUH');
    }

    // ── Tindakan retensi (POST /arsip/{arsip}/retensi-tindakan) ───────────

    public function test_tindakan_retensi_pertahankan_berhasil(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->post(route('arsip.retensi-tindakan', $arsip), [
            'tindakan' => 'pertahankan',
            'catatan_retensi' => 'Dipertahankan untuk audit 2026',
        ]);

        $response->assertRedirect();
        $arsip->refresh();
        $this->assertTrue($arsip->retensi_dipertahankan);
        $this->assertSame($admin->id, $arsip->retensi_ditindak_oleh);
        $this->assertNotNull($arsip->retensi_ditindak_waktu);
    }

    public function test_tindakan_retensi_ditolak_bila_status_aktif(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'permanen',
            'tanggal_kedaluwarsa' => null,
        ]);

        $response = $this->actingAs($admin)->post(route('arsip.retensi-tindakan', $arsip), [
            'tindakan' => 'pertahankan',
        ]);

        $response->assertSessionHas('danger');
        $this->assertFalse($arsip->refresh()->retensi_dipertahankan);
    }

    public function test_tindakan_retensi_mewajibkan_param_tindakan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsipRetensi($admin, [
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->actingAs($admin)->post(route('arsip.retensi-tindakan', $arsip), []);
        $response->assertSessionHasErrors('tindakan');
    }

    public function test_staff_ditolak_tindak_retensi_dokumen_bukan_sesinya(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Buat seksi staff dan staff user
        $seksiStaff = Seksi::create(['kode_seksi' => 'STF1', 'nama_seksi' => 'Staff Section']);
        $staff = User::factory()->create([
            'role' => 'staf',
            'seksi_id' => $seksiStaff->id,
        ]);

        // Arsip milik seksi lain (bukan seksi staff)
        $seksiLain = Seksi::create(['kode_seksi' => 'STF2', 'nama_seksi' => 'Other Section']);
        $rak = RakArsip::create([
            'seksi_id' => $seksiLain->id,
            'kode_rak' => 'RAK-STF',
            'nama_rak' => 'Rak Staff Test',
            'nomor_rak' => '99',
            'is_active' => true,
        ]);
        $arsip = DokumenArsip::create([
            'kode_arsip' => 'RET-STAFF-TEST',
            'nomor_surat' => 'R/99/2026',
            'perihal' => 'Dokumen uji akses staff',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksiLain->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'tersedia',
            'dicatat_oleh' => $admin->id,
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
        ]);

        $response = $this->actingAs($staff)->post(route('arsip.retensi-tindakan', $arsip), [
            'tindakan' => 'pertahankan',
        ]);

        $response->assertForbidden();
    }

    // ── Scope retensiStatus (query builder) ────────────────────────────��───

    public function test_scope_retensi_status_kedaluwarsa(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-KDLWRS',
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
        ]);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-PERMANEN',
            'retensi_kategori' => 'permanen',
            'tanggal_kedaluwarsa' => null,
        ]);

        $results = DokumenArsip::retensiStatus(DokumenArsip::RETENSI_STATUS_KEDALUWARSA)->get();
        $this->assertContains('RET-KDLWRS', $results->pluck('kode_arsip')->toArray());
        $this->assertNotContains('RET-PERMANEN', $results->pluck('kode_arsip')->toArray());
    }

    public function test_scope_retensi_status_dipertahankan(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->buatArsipRetensi($admin, [
            'kode_arsip' => 'RET-DIPERTAHANKAN',
            'retensi_kategori' => 'ringan_5_tahun',
            'tanggal_kedaluwarsa' => now()->subMonth()->toDateString(),
            'retensi_dipertahankan' => true,
        ]);

        $results = DokumenArsip::retensiStatus(DokumenArsip::RETENSI_STATUS_DIPERTAHANKAN)->get();
        $this->assertContains('RET-DIPERTAHANKAN', $results->pluck('kode_arsip')->toArray());
    }
}
