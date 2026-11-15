<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use App\Services\ArsipNumberGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArsipNumberTest extends TestCase
{
    use RefreshDatabase;

    private function siapkanSeksiAndUser(string $kodeSeksi = 'PEM'): array
    {
        $seksi = Seksi::create(['kode_seksi' => $kodeSeksi, 'nama_seksi' => 'Pemerintahan']);
        $admin = User::factory()->create(['role' => 'admin']);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-PEM-01',
            'nama_rak' => 'Rak No. 1',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);
        $boks = BoksArsip::create([
            'rak_id' => $rak->id,
            'kode_boks' => 'BOK-RAK-PEM-01',
            'kapasitas' => 100,
            'is_active' => true,
        ]);

        return [$seksi, $admin, $rak, $boks];
    }

    private function payloadStore(int $seksiId, int $rakId, int $boksId, string $perihal): array
    {
        return [
            'nomor_surat' => '001/TEST/'.now()->year,
            'perihal' => $perihal,
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksiId,
            'rak_id' => $rakId,
            'boks_id' => $boksId,
        ];
    }

    public function test_store_generates_ars_0001_for_first_document(): void
    {
        [$seksi, $admin, $rak, $boks] = $this->siapkanSeksiAndUser('PEM');

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen pertama'));

        $this->assertDatabaseHas('dokumen_arsip', [
            'kode_arsip' => 'ARS-PEM-0001',
            'perihal' => 'Dokumen pertama',
        ]);
    }

    public function test_second_document_gets_0002(): void
    {
        [$seksi, $admin, $rak, $boks] = $this->siapkanSeksiAndUser('PEM');

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen pertama'));
        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen kedua'));

        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'ARS-PEM-0002', 'perihal' => 'Dokumen kedua']);
    }

    public function test_different_seksi_have_separate_sequences(): void
    {
        $seksiA = Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
        $seksiB = Seksi::create(['kode_seksi' => 'EKB', 'nama_seksi' => 'Ekonomi Pembangunan']);
        $admin = User::factory()->create(['role' => 'admin']);
        $rakA = RakArsip::create(['seksi_id' => $seksiA->id, 'kode_rak' => 'RAK-A', 'nama_rak' => 'Rak A', 'is_active' => true]);
        $boksA = BoksArsip::create(['rak_id' => $rakA->id, 'kode_boks' => 'BOK-A', 'kapasitas' => 10, 'is_active' => true]);
        $rakB = RakArsip::create(['seksi_id' => $seksiB->id, 'kode_rak' => 'RAK-B', 'nama_rak' => 'Rak B', 'is_active' => true]);
        $boksB = BoksArsip::create(['rak_id' => $rakB->id, 'kode_boks' => 'BOK-B', 'kapasitas' => 10, 'is_active' => true]);

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksiA->id, $rakA->id, $boksA->id, 'A-1'));
        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksiB->id, $rakB->id, $boksB->id, 'B-1'));

        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'ARS-PEM-0001', 'perihal' => 'A-1']);
        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'ARS-EKB-0001', 'perihal' => 'B-1']);
    }

    public function test_soft_deleted_documents_are_not_reused(): void
    {
        [$seksi, $admin, $rak, $boks] = $this->siapkanSeksiAndUser('PEM');

        // Buat dokumen 0001 lalu soft-delete
        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen 1'));
        $doc1 = DokumenArsip::where('kode_arsip', 'ARS-PEM-0001')->first();
        $doc1->delete(); // soft delete

        // Dokumen berikutnya harus 0002, bukan reuse 0001
        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen 2'));

        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'ARS-PEM-0002', 'perihal' => 'Dokumen 2']);
        // 0001 tetap ada (soft-deleted)
        $this->assertTrue(
            DokumenArsip::withTrashed()->where('kode_arsip', 'ARS-PEM-0001')->exists(),
            'Dokumen 0001 harus tetap ada sebagai soft-deleted'
        );
    }

    public function test_next_number_endpoint_for_staff_returns_own_seksi(): void
    {
        $seksi = Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
        $staf = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $response = $this->actingAs($staf)->getJson('/arsip/next-number?seksi_id=' . $seksi->id);

        $response->assertOk()->assertJson([
            'seksi_id' => $seksi->id,
            'kode_arsip' => 'ARS-PEM-0001',
        ]);
    }

    public function test_next_number_endpoint_for_staff_rejects_other_seksi(): void
    {
        $seksiOwn = Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
        $seksiOther = Seksi::create(['kode_seksi' => 'EKB', 'nama_seksi' => 'Ekonomi']);
        $staf = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksiOwn->id]);

        $response = $this->actingAs($staf)->getJson('/arsip/next-number?seksi_id=' . $seksiOther->id);
        $response->assertStatus(403);
    }

    public function test_next_number_endpoint_for_admin_returns_requested_seksi(): void
    {
        $seksi = Seksi::create(['kode_seksi' => 'KSR', 'nama_seksi' => 'Kesejahteraan Rakyat']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->getJson('/arsip/next-number?seksi_id=' . $seksi->id);

        $response->assertOk()->assertJson([
            'seksi_id' => $seksi->id,
            'kode_arsip' => 'ARS-KSR-0001',
        ]);
    }

    public function test_update_moves_seksi_generates_new_number(): void
    {
        $seksiA = Seksi::create(['kode_seksi' => 'PEM', 'nama_seksi' => 'Pemerintahan']);
        $seksiB = Seksi::create(['kode_seksi' => 'EKB', 'nama_seksi' => 'Ekonomi Pembangunan']);
        $admin = User::factory()->create(['role' => 'admin']);
        $rakA = RakArsip::create(['seksi_id' => $seksiA->id, 'kode_rak' => 'RAK-A', 'nama_rak' => 'Rak A', 'is_active' => true]);
        $boksA = BoksArsip::create(['rak_id' => $rakA->id, 'kode_boks' => 'BOK-A', 'kapasitas' => 10, 'is_active' => true]);
        $rakB = RakArsip::create(['seksi_id' => $seksiB->id, 'kode_rak' => 'RAK-B', 'nama_rak' => 'Rak B', 'is_active' => true]);
        $boksB = BoksArsip::create(['rak_id' => $rakB->id, 'kode_boks' => 'BOK-B', 'kapasitas' => 10, 'is_active' => true]);

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksiA->id, $rakA->id, $boksA->id, 'Dokumen awal'));
        $doc = DokumenArsip::where('perihal', 'Dokumen awal')->first();
        $this->assertSame('ARS-PEM-0001', $doc->kode_arsip);

        // Pindah ke seksi EKB
        $this->actingAs($admin)->put('/arsip/' . $doc->id, [
            'nomor_surat' => $doc->nomor_surat,
            'perihal' => $doc->perihal,
            'jenis_surat' => $doc->jenis_surat,
            'sifat_surat' => 'biasa',
            'tanggal_surat' => $doc->tanggal_surat->toDateString(),
            'seksi_id' => $seksiB->id,
            'rak_id' => $rakB->id,
            'boks_id' => $boksB->id,
        ]);

        $doc->refresh();
        $this->assertSame('ARS-EKB-0001', $doc->kode_arsip);
        $this->assertSame($seksiB->id, $doc->seksi_id);
    }

    public function test_update_same_seksi_keeps_same_number(): void
    {
        [$seksi, $admin, $rak, $boks] = $this->siapkanSeksiAndUser('PEM');

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Dokumen tetap'));
        $doc = DokumenArsip::where('perihal', 'Dokumen tetap')->first();
        $this->assertSame('ARS-PEM-0001', $doc->kode_arsip);

        // Update tanpa pindah seksi
        $this->actingAs($admin)->put('/arsip/' . $doc->id, [
            'nomor_surat' => $doc->nomor_surat,
            'perihal' => 'Dokumen diperbarui',
            'jenis_surat' => $doc->jenis_surat,
            'sifat_surat' => 'penting',
            'tanggal_surat' => $doc->tanggal_surat->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
        ]);

        $doc->refresh();
        $this->assertSame('ARS-PEM-0001', $doc->kode_arsip, 'Kode tidak berubah saat seksi tetap');
    }

    public function test_fallback_when_kode_seksi_is_null(): void
    {
        // Seksi tanpa kode_seksi (misal belum di-backfill)
        $seksi = Seksi::create(['nama_seksi' => 'Pemerintahan', 'kode_seksi' => null]);
        $admin = User::factory()->create(['role' => 'admin']);
        $rak = RakArsip::create(['seksi_id' => $seksi->id, 'kode_rak' => 'RAK-X', 'nama_rak' => 'Rak X', 'is_active' => true]);
        $boks = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-X', 'kapasitas' => 10, 'is_active' => true]);

        $this->actingAs($admin)->post('/arsip', $this->payloadStore($seksi->id, $rak->id, $boks->id, 'Test fallback'));

        // Fallback: inisial "Pemerintahan" = "P"
        $this->assertDatabaseHas('dokumen_arsip', ['kode_arsip' => 'ARS-P-0001', 'perihal' => 'Test fallback']);
    }
}
