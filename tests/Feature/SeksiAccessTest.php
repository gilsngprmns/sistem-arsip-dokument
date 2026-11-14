<?php

namespace Tests\Feature;

use App\Models\BoksArsip;
use App\Models\DokumenArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeksiAccessTest extends TestCase
{
    use RefreshDatabase;

    private function createStaf(string $seksiNama, ?int $seksiId = null): array
    {
        $seksiA = Seksi::create(['nama_seksi' => 'Pemerintahan']);
        $seksiB = Seksi::create(['nama_seksi' => 'Ekonomi dan Pembangunan']);

        $target = $seksiId ? $seksiId : $seksiA->id;
        $staf = User::factory()->create([
            'role' => 'staf',
            'seksi_id' => $target,
        ]);

        return [$staf, $seksiA, $seksiB];
    }

    private function createDokumen(Seksi $seksi, int $userId): DokumenArsip
    {
        return DokumenArsip::create([
            'kode_arsip' => 'ARSIP-' . uniqid(),
            'nomor_surat' => '001/ARSIP/2026',
            'perihal' => 'Dokumen uji akses',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-01-01',
            'tanggal_diterima' => '2026-01-01',
            'seksi_id' => $seksi->id,
            'dicatat_oleh' => $userId,
        ]);
    }

    // -----------------------------------------------------------------------
    // filteredQuery: staff hanya melihat dokumen seksi sendiri
    // -----------------------------------------------------------------------

    public function test_staff_index_only_returns_own_seksi_documents(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');

        $this->createDokumen($seksiA, $staf->id);
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $response = $this->actingAs($staf)->get('/arsip');
        $response->assertOk();
        $response->assertDontSee($otherDok->kode_arsip);
    }

    public function test_staff_cannot_access_other_seksi_via_query_param(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');

        $this->createDokumen($seksiA, $staf->id);
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        // Staff coba akses ?seksi_id={seksiB} — tetap harus di-scope ke seksiA
        $response = $this->actingAs($staf)->get('/arsip?seksi_id=' . $seksiB->id);
        $response->assertOk();
        $response->assertDontSee($otherDok->kode_arsip);
    }

    public function test_admin_index_returns_all_documents(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $admin = User::factory()->create(['role' => 'admin']);

        $dokA = $this->createDokumen($seksiA, $admin->id);
        $dokB = $this->createDokumen($seksiB, $admin->id);

        $response = $this->actingAs($admin)->get('/arsip');
        $response->assertOk();
        $response->assertSee($dokA->kode_arsip);
        $response->assertSee($dokB->kode_arsip);
    }

    // -----------------------------------------------------------------------
    // show / edit / destroy: staff hanya bisa akses dokumen seksi sendiri
    // -----------------------------------------------------------------------

    public function test_staff_cannot_view_document_from_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $this->actingAs($staf)->get('/arsip/' . $otherDok->id)->assertForbidden();
    }

    public function test_staff_cannot_edit_document_from_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $this->actingAs($staf)->get('/arsip/' . $otherDok->id . '/edit')->assertForbidden();
    }

    public function test_staff_cannot_destroy_document_from_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $this->actingAs($staf)->delete('/arsip/' . $otherDok->id)->assertForbidden();
        $this->assertDatabaseHas('dokumen_arsip', ['id' => $otherDok->id]);
    }

    public function test_staff_can_access_own_seksi_document(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $ownDok = $this->createDokumen($seksiA, $staf->id);

        $this->actingAs($staf)->get('/arsip/' . $ownDok->id)->assertOk();
    }

    // -----------------------------------------------------------------------
    // store / update: staff hanya bisa buat/ubah dokumen di seksi sendiri
    // -----------------------------------------------------------------------

    public function test_staff_cannot_create_document_in_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');

        $response = $this->actingAs($staf)->post('/arsip', [
            'kode_arsip' => 'ARSIP-OTHER-' . uniqid(),
            'nomor_surat' => '002/ARSIP/2026',
            'perihal' => 'Percobaan akses lintas seksi',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-01-01',
            'seksi_id' => $seksiB->id,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('dokumen_arsip', ['perihal' => 'Percobaan akses lintas seksi']);
    }

    public function test_staff_can_create_document_in_own_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $rak  = RakArsip::create(['seksi_id' => $seksiA->id, 'kode_rak' => 'RAK-OWN', 'nama_rak' => 'Rak Own', 'is_active' => true]);
        $boks = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-OWN', 'kapasitas' => 10, 'is_active' => true]);

        $response = $this->actingAs($staf)->post('/arsip', [
            'kode_arsip' => 'ARSIP-OWN-' . uniqid(),
            'nomor_surat' => '003/ARSIP/2026',
            'perihal' => 'Dokumen seksi sendiri',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-01-01',
            'seksi_id' => $seksiA->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
        ]);

        $response->assertRedirect('/arsip');
        $this->assertDatabaseHas('dokumen_arsip', ['perihal' => 'Dokumen seksi sendiri']);
    }

    public function test_staff_cannot_update_document_to_move_to_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $ownDok = $this->createDokumen($seksiA, $staf->id);

        $response = $this->actingAs($staf)->put('/arsip/' . $ownDok->id, [
            'kode_arsip' => $ownDok->kode_arsip,
            'nomor_surat' => $ownDok->nomor_surat,
            'perihal' => 'Diperbarui',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-01-01',
            'seksi_id' => $seksiB->id, // coba pindah ke seksi lain
        ]);

        $response->assertForbidden();
        $this->assertEquals($seksiA->id, $ownDok->fresh()->seksi_id);
    }

    public function test_admin_can_create_document_in_any_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $admin = User::factory()->create(['role' => 'admin']);
        $rak  = RakArsip::create(['seksi_id' => $seksiB->id, 'kode_rak' => 'RAK-ADMIN', 'nama_rak' => 'Rak Admin', 'is_active' => true]);
        $boks = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-ADMIN', 'is_active' => true]);

        $response = $this->actingAs($admin)->post('/arsip', [
            'kode_arsip' => 'ARSIP-ADMIN-' . uniqid(),
            'nomor_surat' => '004/ARSIP/2026',
            'perihal' => 'Dokumen admin',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => '2026-01-01',
            'seksi_id' => $seksiB->id,
            'rak_id' => $rak->id,
            'boks_id' => $boks->id,
        ]);

        $response->assertRedirect('/arsip');
        $this->assertDatabaseHas('dokumen_arsip', ['perihal' => 'Dokumen admin']);
    }

    // -----------------------------------------------------------------------
    // pinjam / kembalikan / settersedia / tandahilang: staff hanya akses seksi sendiri
    // -----------------------------------------------------------------------

    public function test_staff_cannot_pinjam_document_from_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $this->actingAs($staf)->post('/arsip/' . $otherDok->id . '/pinjam', [
            'pihak_terkait' => 'Peminjam',
        ])->assertForbidden();
    }

    public function test_staff_can_pinjam_own_seksi_document(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $ownDok = $this->createDokumen($seksiA, $staf->id);

        $this->actingAs($staf)->post('/arsip/' . $ownDok->id . '/pinjam', [
            'pihak_terkait' => 'Peminjam',
        ])->assertRedirect();
        $this->assertEquals('dipinjam', $ownDok->fresh()->status_arsip);
    }

    // -----------------------------------------------------------------------
    // scanByKode: staff hanya bisa scan dokumen dari seksi sendiri
    // -----------------------------------------------------------------------

    public function test_staff_cannot_scan_document_from_other_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $otherDok = $this->createDokumen($seksiB, $staf->id);

        $response = $this->actingAs($staf)->post('/api/arsip/scan', [
            'kode_arsip' => $otherDok->kode_arsip,
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('seksi yang Anda kelola', $response->json('message'));
    }

    public function test_staff_can_scan_own_seksi_document(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $ownDok = $this->createDokumen($seksiA, $staf->id);

        $response = $this->actingAs($staf)->post('/api/arsip/scan', [
            'kode_arsip' => $ownDok->kode_arsip,
        ]);

        $response->assertOk();
        $this->assertEquals($ownDok->id, $response->json('id'));
    }

    public function test_admin_can_scan_document_from_any_seksi(): void
    {
        [$staf, $seksiA, $seksiB] = $this->createStaf('Pemerintahan');
        $admin = User::factory()->create(['role' => 'admin']);
        $dok = $this->createDokumen($seksiB, $admin->id);

        $this->actingAs($admin)->post('/api/arsip/scan', [
            'kode_arsip' => $dok->kode_arsip,
        ])->assertOk();
    }
}
