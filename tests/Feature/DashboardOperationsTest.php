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

class DashboardOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exposes_active_loans_and_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-001',
            'nama_rak' => 'Rak Utama',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $overdue = DokumenArsip::create([
            'kode_arsip' => 'ARSIP-OVERDUE',
            'nomor_surat' => '001/TEST/2026',
            'perihal' => 'Dokumen terlambat',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'dipinjam',
            'batas_pengembalian' => now()->subDays(2),
            'dicatat_oleh' => $admin->id,
        ]);

        RiwayatSirkulasi::create([
            'dokumen_arsip_id' => $overdue->id,
            'user_id' => $admin->id,
            'jenis_aksi' => 'dipinjam',
            'pihak_terkait' => 'Budi Santoso',
            'waktu_aksi' => now(),
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-NO-RAK',
            'nomor_surat' => '002/TEST/2026',
            'perihal' => 'Dokumen tanpa rak',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'tersedia',
            'rak_id' => null,
            'dicatat_oleh' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.dokumen_hilang', 0)
                ->where('notifications.tanpa_lokasi', 1)
                ->where('notifications.terlambat', 1)
                ->has('activeLoans', 1)
                ->where('activeLoans.0.kode_arsip', 'ARSIP-OVERDUE')
                ->where('activeLoans.0.peminjam', 'Budi Santoso')
                ->where('activeLoans.0.terlambat', true)
                ->has('trends', 6)
                ->where('trends.5.pinjam', 1)
                ->where('trends.5.kembali', 0)
                ->where('dataHealth.tanpa_lokasi', 1)
                ->where('dataHealth.tanpa_file', 2)
                ->where('dataHealth.metadata_kosong', 0)
                ->where('dataHealth.tanpa_qr', 0)
            );
    }

    public function test_staff_dashboard_is_scoped_with_operational_widgets(): void
    {
        $seksiA = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $seksiB = Seksi::create(['nama_seksi' => 'Keuangan']);
        $staff = User::factory()->create([
            'role' => 'staf',
            'seksi_id' => $seksiA->id,
        ]);
        $rak = RakArsip::create([
            'seksi_id' => $seksiA->id,
            'kode_rak' => 'RAK-001',
            'nama_rak' => 'Rak Utama',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $ownLoan = DokumenArsip::create([
            'kode_arsip' => 'ARSIP-OWN',
            'nomor_surat' => '010/TEST/2026',
            'perihal' => 'Pinjaman seksi sendiri',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksiA->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'dipinjam',
            'batas_pengembalian' => now()->addDays(2),
            'dicatat_oleh' => $staff->id,
        ]);

        RiwayatSirkulasi::create([
            'dokumen_arsip_id' => $ownLoan->id,
            'user_id' => $staff->id,
            'jenis_aksi' => 'dipinjam',
            'pihak_terkait' => 'Staf Pelayanan',
            'waktu_aksi' => now(),
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-HILANG',
            'nomor_surat' => '011/TEST/2026',
            'perihal' => 'Dokumen hilang',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksiA->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'hilang',
            'dicatat_oleh' => $staff->id,
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-OTHER',
            'nomor_surat' => '012/TEST/2026',
            'perihal' => 'Milik seksi lain',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksiB->id,
            'status_arsip' => 'tersedia',
            'rak_id' => null,
            'dicatat_oleh' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.dokumen_hilang', 1)
                ->where('stats.dokumen_dipinjam', 1)
                ->where('notifications.tanpa_lokasi', 0)
                ->missing('notifications.rak_hampir_penuh')
                ->has('activeLoans', 1)
                ->where('activeLoans.0.kode_arsip', 'ARSIP-OWN')
                ->has('trends', 6)
                ->where('trends.5.pinjam', 1)
            );
    }

    public function test_arsip_index_supports_tanpa_lokasi_and_terlambat_filters(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rak = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-001',
            'nama_rak' => 'Rak Utama',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-NO-RAK',
            'nomor_surat' => '001/TEST/2026',
            'perihal' => 'Tanpa lokasi',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'tersedia',
            'rak_id' => null,
            'dicatat_oleh' => $user->id,
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-LATE',
            'nomor_surat' => '002/TEST/2026',
            'perihal' => 'Terlambat',
            'jenis_surat' => 'keluar',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'dipinjam',
            'batas_pengembalian' => now()->subDay(),
            'dicatat_oleh' => $user->id,
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-OK',
            'nomor_surat' => '003/TEST/2026',
            'perihal' => 'Masih aman',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'rak_id' => $rak->id,
            'status_arsip' => 'dipinjam',
            'batas_pengembalian' => now()->addDays(3),
            'dicatat_oleh' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/arsip?tanpa_lokasi=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Arsip/Index')
                ->has('arsip.data', 1)
                ->where('arsip.data.0.kode_arsip', 'ARSIP-NO-RAK')
                ->where('filters.tanpa_lokasi', true)
            );

        $this->actingAs($user)
            ->get('/arsip?terlambat=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Arsip/Index')
                ->has('arsip.data', 1)
                ->where('arsip.data.0.kode_arsip', 'ARSIP-LATE')
                ->where('filters.terlambat', true)
            );
    }

    public function test_admin_boks_attention_shows_only_boks_above_80_percent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);

        // 85% of capacity = 17 / 20 → should appear in boks_perhatian
        $rackHigh = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-HIGH',
            'nama_rak' => 'Rak 85%',
            'nomor_rak' => 'H1',
            'is_active' => true,
        ]);
        $boksHigh = BoksArsip::create([
            'rak_id' => $rackHigh->id,
            'kode_boks' => 'BOK-HIGH',
            'kapasitas' => 20,
            'is_active' => true,
        ]);
        for ($i = 0; $i < 17; $i++) {
            DokumenArsip::create([
                'kode_arsip' => "ARSIP-HIGH-{$i}",
                'nomor_surat' => "H0{$i}/TEST/2026",
                'perihal' => "Dokumen boks 85% #{$i}",
                'jenis_surat' => 'masuk',
                'sifat_surat' => 'biasa',
                'tanggal_surat' => now()->toDateString(),
                'seksi_id' => $seksi->id,
                'rak_id' => $rackHigh->id,
                'boks_id' => $boksHigh->id,
                'status_arsip' => 'tersedia',
                'dicatat_oleh' => $admin->id,
            ]);
        }

        // 50% of capacity = 10 / 20 → should NOT appear
        $rackLow = RakArsip::create([
            'seksi_id' => $seksi->id,
            'kode_rak' => 'RAK-LOW',
            'nama_rak' => 'Rak 50%',
            'nomor_rak' => 'L1',
            'is_active' => true,
        ]);
        $boksLow = BoksArsip::create([
            'rak_id' => $rackLow->id,
            'kode_boks' => 'BOK-LOW',
            'kapasitas' => 20,
            'is_active' => true,
        ]);
        for ($i = 0; $i < 10; $i++) {
            DokumenArsip::create([
                'kode_arsip' => "ARSIP-LOW-{$i}",
                'nomor_surat' => "L0{$i}/TEST/2026",
                'perihal' => "Dokumen boks 50% #{$i}",
                'jenis_surat' => 'masuk',
                'sifat_surat' => 'biasa',
                'tanggal_surat' => now()->toDateString(),
                'seksi_id' => $seksi->id,
                'rak_id' => $rackLow->id,
                'boks_id' => $boksLow->id,
                'status_arsip' => 'tersedia',
                'dicatat_oleh' => $admin->id,
            ]);
        }

        $this->actingAs($admin)->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('notifications.boks_hampir_penuh', 1)
                ->has('adminOverview.boks_perhatian', 1)
                ->where('adminOverview.boks_perhatian.0.kode_boks', 'BOK-HIGH')
                ->where('adminOverview.boks_perhatian.0.pct', 85)
            );
    }

    public function test_arsip_index_supports_data_health_filters(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Pelayanan']);
        $user = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-NO-FILE',
            'nomor_surat' => '100/TEST/2026',
            'perihal' => 'Tanpa file',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'tersedia',
            'file_path' => null,
            'qr_code_path' => 'qr/ARSIP-NO-FILE.png',
            'dicatat_oleh' => $user->id,
        ]);

        DokumenArsip::create([
            'kode_arsip' => 'ARSIP-COMPLETE',
            'nomor_surat' => '101/TEST/2026',
            'perihal' => 'Lengkap',
            'jenis_surat' => 'masuk',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'tersedia',
            'file_path' => 'lampiran-arsip/complete.pdf',
            'qr_code_path' => 'qr/ARSIP-COMPLETE.png',
            'dicatat_oleh' => $user->id,
        ]);

        $withoutQr = DokumenArsip::create([
            'kode_arsip' => 'ARSIP-NO-QR',
            'nomor_surat' => '102/TEST/2026',
            'perihal' => 'Tanpa QR',
            'jenis_surat' => 'keluar',
            'sifat_surat' => 'biasa',
            'tanggal_surat' => now()->toDateString(),
            'seksi_id' => $seksi->id,
            'status_arsip' => 'tersedia',
            'file_path' => 'lampiran-arsip/no-qr.pdf',
            'dicatat_oleh' => $user->id,
        ]);

        $withoutQr->updateQuietly(['qr_code_path' => null]);

        $this->actingAs($user)
            ->get('/arsip?tanpa_file=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Arsip/Index')
                ->has('arsip.data', 1)
                ->where('arsip.data.0.kode_arsip', 'ARSIP-NO-FILE')
                ->where('filters.tanpa_file', true)
            );

        $this->actingAs($user)
            ->get('/arsip?tanpa_qr=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Arsip/Index')
                ->has('arsip.data', 1)
                ->where('arsip.data.0.kode_arsip', 'ARSIP-NO-QR')
                ->where('filters.tanpa_qr', true)
            );
    }
}
