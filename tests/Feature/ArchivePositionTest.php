<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArchivePositionTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_can_be_saved_with_rack_and_boks(): void
    {
        Storage::fake('public');

        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-001',
            'nama_rak' => 'Rak Utama',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);
        $boks = BoksArsip::create([
            'kode_boks' => 'BOK-RAK-001',
            'rak_id' => $rak->id,
            'nomor_baris' => 'B-01',
            'nomor_boks' => 'BOX-01',
            'keterangan' => 'Boks default rak utama',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post('/arsip', [
            'kode_arsip' => 'ARSIP-001',
            'nomor_surat' => '001/ARSIP/2026',
            'perihal' => 'Dokumen pengujian',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-09-25',
            'tanggal_diterima' => '2026-09-25',
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
            'keterangan_posisi' => 'Rak bagian tengah',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect('/arsip');

        $this->assertDatabaseHas('dokumen_arsip', [
            'kode_arsip' => 'ARSIP-001',
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
        ]);

        $this->assertSame('BOK-RAK-001', DokumenArsip::firstOrFail()->boks->kode_boks);
    }

    public function test_archive_rejects_rack_from_another_seksi(): void
    {
        Storage::fake('public');

        $seksiA = Seksi::create(['nama_seksi' => 'Pemerintahan']);
        $seksiB = Seksi::create(['nama_seksi' => 'Kesejahteraan Rakyat']);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksiA->id]);
        $rakB = RakArsip::create([
            'seksi_id' => $seksiB->id,
            'kode_rak' => 'RAK-KSR-01',
            'nama_rak' => 'Rak No. 1 Seksi Kesejahteraan',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);
        $boksB = BoksArsip::create([
            'kode_boks' => 'BOK-RAK-KSR-01',
            'rak_id' => $rakB->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->from('/arsip/create')->post('/arsip', [
            'kode_arsip' => 'ARSIP-002',
            'nomor_surat' => '002/ARSIP/2026',
            'perihal' => 'Dokumen salah rak',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-09-25',
            'seksi_id' => $seksiA->id,
            'rak_id' => $rakB->id,
            'boks_id' => $boksB->id,
        ]);

        $response->assertSessionHasErrors('rak_id');
        $this->assertDatabaseMissing('dokumen_arsip', ['kode_arsip' => 'ARSIP-002']);
    }

    public function test_boks_from_different_rack_is_rejected(): void
    {
        Storage::fake('public');

        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rakA = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-A',
            'nama_rak' => 'Rak A',
            'nomor_rak' => 'A1',
            'is_active' => true,
        ]);
        $rakB = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-B',
            'nama_rak' => 'Rak B',
            'nomor_rak' => 'B1',
            'is_active' => true,
        ]);
        $boksB = BoksArsip::create([
            'kode_boks' => 'BOK-RAK-B',
            'rak_id' => $rakB->id,
            'nomor_baris' => 'B-01',
            'nomor_boks' => 'BOX-B01',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->from('/arsip/create')->post('/arsip', [
            'kode_arsip' => 'ARSIP-003',
            'nomor_surat' => '003/ARSIP/2026',
            'perihal' => 'Dokumen salah boks',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-09-25',
            'seksi_id' => $seksi->id,
            'rak_id' => $rakA->id,
            'boks_id' => $boksB->id,
        ]);

        $response->assertSessionHasErrors('boks_id');
        $this->assertDatabaseMissing('dokumen_arsip', ['kode_arsip' => 'ARSIP-003']);
    }
}