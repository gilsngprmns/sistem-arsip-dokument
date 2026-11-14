<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TandaiHilangTest extends TestCase
{
    use RefreshDatabase;

    private function buatArsip(User $admin, string $status = 'tersedia'): DokumenArsip
    {
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-TEST',
            'nama_rak' => 'Rak Test',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        return DokumenArsip::create([
            'kode_arsip' => 'TEST-'.strtoupper($status),
            'nomor_surat' => '999/TEST/2026',
            'perihal' => 'Dokumen uji tandahilang',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'status_arsip' => $status,
            'dicatat_oleh' => $admin->id,
        ]);
    }

    public function test_teredia_dapat_ditandai_hilang(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsip($admin, 'tersedia');

        $response = $this->actingAs($admin)->post(route('arsip.tandahilang', $arsip), [
            'keterangan' => 'Tidak ditemukan saat audit rak',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dokumen_arsip', ['id' => $arsip->id, 'status_arsip' => 'hilang']);
        $this->assertDatabaseHas('riwayat_sirkulasi', [
            'dokumen_arsip_id' => $arsip->id,
            'jenis_aksi' => 'dihilangkan',
        ]);
        $this->assertNull(DokumenArsip::find($arsip->id)->batas_pengembalian);
    }

    public function test_dipinjam_dapat_ditandai_hilang(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsip($admin, 'dipinjam');
        $arsip->update(['batas_pengembalian' => now()->subDays(10)]);

        $this->actingAs($admin)->post(route('arsip.tandahilang', $arsip), []);

        $this->assertDatabaseHas('dokumen_arsip', ['id' => $arsip->id, 'status_arsip' => 'hilang']);
        $this->assertNull(DokumenArsip::find($arsip->id)->batas_pengembalian);
    }

    public function test_sudah_hilang_tak_bisa_ditandai_lagi(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $arsip = $this->buatArsip($admin, 'hilang');

        $response = $this->actingAs($admin)->post(route('arsip.tandahilang', $arsip), []);

        $response->assertSessionHasErrors('sirkulasi');
        $this->assertDatabaseHas('dokumen_arsip', ['id' => $arsip->id, 'status_arsip' => 'hilang']);
    }

    public function test_filter_status_hilang_menampilkan_dokumen(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $hilang = $this->buatArsip($admin, 'tersedia');
        $this->actingAs($admin)->post(route('arsip.tandahilang', $hilang), []);

        $response = $this->actingAs($admin)->get('/arsip?status=hilang');
        $response->assertOk();
        $response->assertSee('TEST-TERSEDIA');
    }

    public function test_store_menerima_status_arsip_valid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $rak   = RakArsip::create(['seksi_id' => $seksi->id, 'kode_rak' => 'RAK-HILANG', 'nama_rak' => 'Rak Hilang', 'is_active' => true]);
        $boks  = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-HILANG', 'kapasitas' => 10, 'is_active' => true]);

        $response = $this->actingAs($admin)->post(route('arsip.store'), [
            'kode_arsip' => 'STORE-HILANG',
            'nomor_surat' => '1/TEST/2026',
            'perihal' => 'Dokumen uji store',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
            'status_arsip' => 'hilang',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'STORE-HILANG', 'status_arsip' => 'hilang']);
    }

    public function test_store_menolak_status_arsip_invalid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);

        $response = $this->actingAs($admin)->post(route('arsip.store'), [
            'kode_arsip' => 'STORE-INVALID',
            'nomor_surat' => '2/TEST/2026',
            'perihal' => 'Dokumen uji store invalid',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'salah',
        ]);

        $response->assertSessionHasErrors('status_arsip');
        $this->assertDatabaseMissing('dokumen_arsip', ['kode_arsip' => 'STORE-INVALID']);
    }
}
