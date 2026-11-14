<?php

namespace Tests\Feature;

use App\Models\DokumenArsip;
use App\Models\ActivityLog;
use App\Models\BoksArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_creates_activity_log(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'auth.login',
        ]);
    }

    public function test_logout_creates_activity_log(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout');

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_login_logout_appear_in_activity_log_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);

        $this->post('/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response = $this->actingAs($admin)->get('/activity-log');
        $response->assertOk();
        $response->assertSee('auth.login', false);
    }

    public function test_staff_arsip_store_creates_activity_log_visible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'STF-LOG-001',
            'nomor_surat'   => '001/STF/LOG',
            'perihal'       => 'Dokumen Test Staf',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staf->id,
            'action'  => 'arsip.store',
        ]);

        $this->actingAs($admin)->get('/activity-log')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Activity/Index')
                ->where('activities.data.0.action', 'arsip.store')
            );
    }

    public function test_staff_arsip_destroy_creates_activity_log_visible_to_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rak   = RakArsip::create(['seksi_id' => $seksi->id, 'kode_rak' => 'RAK-DEL', 'nama_rak' => 'Rak Del', 'is_active' => true]);
        $boks  = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-DEL', 'is_active' => true]);

        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'STF-DEL-001',
            'nomor_surat'   => '002/STF/DEL',
            'perihal'       => 'Dokumen Hapus',
            'jenis_surat'   => 'keluar',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
            'rak_id'        => $rak->id,
            'boks_id'       => $boks->id,
        ]);

        $arsip = DokumenArsip::where('kode_arsip', 'STF-DEL-001')->first();
        $this->actingAs($staf)->delete("/arsip/{$arsip->id}");

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staf->id,
            'action'  => 'arsip.destroy',
        ]);

        // Order-independent: assert both actions exist regardless of page position.
        // DB is refreshed per test; admin sees only the two actions created above.
        $this->actingAs($admin)->get('/activity-log')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Activity/Index')
                ->where('activities.total', 2)
                ->has('activities.data', 2)
            );
        // Verify both actions are present (regardless of index ordering).
        $log = \App\Models\ActivityLog::query()
            ->whereIn('action', ['arsip.store', 'arsip.destroy'])
            ->where('user_id', $staf->id)
            ->pluck('action')
            ->sort()
            ->values();
        $this->assertEquals(['arsip.destroy', 'arsip.store'], $log->all());
    }

    public function test_staff_arsip_store_via_inertia_header_creates_activity_log(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $this->actingAs($staf)
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/arsip', [
                'kode_arsip'    => 'STF-INERTIA-001',
                'nomor_surat'   => '003/STF/INERTIA',
                'perihal'       => 'Dokumen Inertia Flow',
                'jenis_surat'   => 'masuk',
                'tanggal_surat' => '2026-11-01',
                'seksi_id'      => $seksi->id,
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staf->id,
            'action'  => 'arsip.store',
        ]);
    }

    public function test_staff_arsip_update_via_inertia_header_creates_activity_log(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rak   = RakArsip::create(['seksi_id' => $seksi->id, 'kode_rak' => 'RAK-UPD', 'nama_rak' => 'Rak Upd', 'is_active' => true]);
        $boks  = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-UPD', 'is_active' => true]);

        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'STF-UPD-001',
            'nomor_surat'   => '004/STF/UPD',
            'perihal'       => 'Dokumen Update Inertia',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
            'rak_id'        => $rak->id,
            'boks_id'       => $boks->id,
        ]);

        $arsip = DokumenArsip::where('kode_arsip', 'STF-UPD-001')->first();

        $this->actingAs($staf)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put("/arsip/{$arsip->id}", [
                'kode_arsip'    => 'STF-UPD-001',
                'nomor_surat'   => '004/STF/UPD',
                'perihal'       => 'Dokumen Update Inertia v2',
                'jenis_surat'   => 'masuk',
                'tanggal_surat' => '2026-11-01',
                'seksi_id'      => $seksi->id,
                'rak_id'        => $rak->id,
                'boks_id'       => $boks->id,
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $staf->id,
            'action'  => 'arsip.update',
        ]);
    }

    // ── Fix #93: Deteksi kegagalan ──────────────────────────────────────────

    public function test_failed_arsip_store_logs_gagal_description(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Duplikat']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        // Buat arsip pertama
        DokumenArsip::create([
            'kode_arsip' => 'DUP-001',
            'nomor_surat' => '010/DUP',
            'perihal'     => 'Dokumen duplikat test',
            'jenis_surat' => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'   => $seksi->id,
            'dicatat_oleh' => $staf->id,
        ]);

        // Coba buat dengan kode yang sama → validasi unique gagal
        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'DUP-001',
            'nomor_surat'   => '011/DUP',
            'perihal'       => 'Dokumen duplikat test 2',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.store')
            ->latest()->first();

        $this->assertNotNull($log, 'Log harus dibuat untuk store gagal');
        $this->assertStringStartsWith('Gagal', $log->description);
        $this->assertSame('gagal', $log->metadata['hasil'] ?? null);
    }

    public function test_successful_arsip_store_logs_sukses(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Sukses']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);
        $rak   = RakArsip::create(['seksi_id' => $seksi->id, 'kode_rak' => 'RAK-OK', 'nama_rak' => 'Rak OK', 'is_active' => true]);
        $boks  = BoksArsip::create(['rak_id' => $rak->id, 'kode_boks' => 'BOK-OK', 'is_active' => true]);

        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'OK-001',
            'nomor_surat'   => '020/OK',
            'perihal'       => 'Dokumen sukses test',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
            'rak_id'        => $rak->id,
            'boks_id'       => $boks->id,
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.store')
            ->latest()->first();

        $this->assertNotNull($log);
        $this->assertStringStartsWith('Menambahkan', $log->description, 'Sukses tidak boleh diawali "Gagal"');
        $this->assertSame('sukses', $log->metadata['hasil'] ?? null);
    }

    public function test_failed_tandahilang_logs_gagal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Hilang']);
        $rak = \App\Models\RakArsip::create([
            'seksi_id'  => $seksi->id,
            'kode_rak'  => 'RAK-FAIL',
            'nama_rak'  => 'Rak Fail',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        // Arsip yang sudah hilang
        $arsip = DokumenArsip::create([
            'kode_arsip'     => 'HILANG-FAIL',
            'nomor_surat'    => '030/HILANG',
            'perihal'        => 'Dokumen sudah hilang',
            'jenis_surat'    => 'masuk',
            'tanggal_surat'  => '2026-11-01',
            'seksi_id'       => $seksi->id,
            'rak_id'         => $rak->id,
            'status_arsip'   => 'hilang',
            'dicatat_oleh'   => $admin->id,
        ]);

        $this->actingAs($admin)->post("/arsip/{$arsip->id}/tandahilang", []);

        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('action', 'arsip.tandahilang')
            ->latest()->first();

        $this->assertNotNull($log, 'Log harus dibuat untuk tandahilang gagal');
        $this->assertStringStartsWith('Gagal', $log->description);
        $this->assertSame('gagal', $log->metadata['hasil'] ?? null);
    }

    // ── Fix #94: Pindai QR arsip tercatat di Activity Log ───────────────────

    public function test_scan_by_kode_success_creates_activity_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Scan']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $arsip = DokumenArsip::create([
            'kode_arsip'    => 'SCAN-OK-001',
            'nomor_surat'   => '050/SCAN',
            'perihal'       => 'Dokumen scan sukses',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
            'dicatat_oleh'  => $admin->id,
        ]);

        $this->actingAs($staf)->post('/api/arsip/scan', [
            'kode_arsip' => 'SCAN-OK-001',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'    => $staf->id,
            'action'     => 'arsip.scan',
            'route_name' => 'arsip.scan',
            'method'     => 'POST',
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.scan')
            ->latest()->first();

        $this->assertNotNull($log);
        $this->assertStringStartsWith('Pindai QR arsip', $log->description);
        $this->assertSame('sukses', $log->metadata['hasil'] ?? null);
    }

    public function test_scan_by_kode_not_found_logs_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staf  = User::factory()->create(['role' => 'staf']);

        $this->actingAs($staf)->post('/api/arsip/scan', [
            'kode_arsip' => 'TIDAK-ADA-999',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'    => $staf->id,
            'action'     => 'arsip.scan',
            'route_name' => 'arsip.scan',
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.scan')
            ->latest()->first();

        $this->assertNotNull($log, 'Log harus dibuat untuk scan gagal (kode tidak ditemukan)');
        $this->assertStringStartsWith('Gagal', $log->description);
        $this->assertSame('gagal', $log->metadata['hasil'] ?? null);
    }

    public function test_scan_by_kode_empty_code_logs_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staf  = User::factory()->create(['role' => 'staf']);

        $this->actingAs($staf)->post('/api/arsip/scan', [
            'kode_arsip' => '',
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.scan')
            ->latest()->first();

        $this->assertNotNull($log, 'Log harus dibuat untuk scan dengan kode kosong');
        $this->assertStringStartsWith('Gagal', $log->description);
        $this->assertSame('gagal', $log->metadata['hasil'] ?? null);
    }

    public function test_admin_get_routes_have_friendly_descriptions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // GET /users
        $this->actingAs($admin)->get('/users');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'users.index')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log users.index harus ada');
        $this->assertSame('Membuka daftar pengguna.', $log->description);

        // GET /activity-log
        $this->actingAs($admin)->get('/activity-log');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'activity.index')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log activity.index harus ada');
        $this->assertSame('Membuka log aktivitas.', $log->description);

        // GET /reports/activity (Excel)
        $this->actingAs($admin)->get('/reports/activity');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'reports.activity')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log reports.activity harus ada');
        $this->assertSame('Mengekspor laporan aktivitas.', $log->description);

        // GET /reports/users (Excel)
        $this->actingAs($admin)->get('/reports/users');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'reports.users')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log reports.users harus ada');
        $this->assertSame('Mengekspor laporan pengguna.', $log->description);

        // GET /reports/racks (Excel)
        $this->actingAs($admin)->get('/reports/racks');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'reports.racks')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log reports.racks harus ada');
        $this->assertSame('Mengekspor laporan rak.', $log->description);

        // GET /reports/sections (Excel)
        $this->actingAs($admin)->get('/reports/sections');
        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('route_name', 'reports.sections')
            ->where('method', 'GET')
            ->latest()->first();
        $this->assertNotNull($log, 'Log reports.sections harus ada');
        $this->assertSame('Mengekspor laporan seksi.', $log->description);
    }

    // ── Target filling: fallback dari request input ────────────────────────

    public function test_arsip_update_sets_target(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test Target']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $arsip = DokumenArsip::create([
            'kode_arsip'    => 'TGT-001',
            'nomor_surat'   => '001/TGT',
            'perihal'       => 'Test Target',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
            'dicatat_oleh'  => $staf->id,
        ]);

        $this->actingAs($staf)
            ->withHeaders(['X-Inertia' => 'true'])
            ->put("/arsip/{$arsip->id}", [
                'kode_arsip'    => 'TGT-001',
                'nomor_surat'   => '001/TGT',
                'perihal'       => 'Test Target Updated',
                'jenis_surat'   => 'masuk',
                'tanggal_surat' => '2026-11-01',
                'seksi_id'      => $seksi->id,
            ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $staf->id,
            'action'      => 'arsip.update',
            'target_type' => 'dokumen',
            'target_id'   => $arsip->id,
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.update')
            ->latest()->first();

        $this->assertSame('001/TGT', $log->target_label);
    }

    public function test_arsip_store_sets_target(): void
    {
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test Store']);
        $staf  = User::factory()->create(['role' => 'staf', 'seksi_id' => $seksi->id]);

        $this->actingAs($staf)->post('/arsip', [
            'kode_arsip'    => 'TGT-STORE-001',
            'nomor_surat'   => '002/TGT',
            'perihal'       => 'Test Store Target',
            'jenis_surat'   => 'masuk',
            'tanggal_surat' => '2026-11-01',
            'seksi_id'      => $seksi->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $staf->id,
            'action'      => 'arsip.store',
            'target_type' => 'dokumen',
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $staf->id)
            ->where('action', 'arsip.store')
            ->latest()->first();

        $this->assertSame('002/TGT', $log->target_label);
    }

    public function test_users_store_sets_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test User']);

        $this->actingAs($admin)->post('/users', [
            'name'     => 'Test User Target',
            'email'    => 'testtarget@example.com',
            'password' => 'password',
            'role'     => 'staf',
            'seksi_id' => $seksi->id,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $admin->id,
            'action'      => 'users.store',
            'target_type' => 'user',
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('action', 'users.store')
            ->latest()->first();

        $this->assertSame('Test User Target', $log->target_label);
    }

    public function test_rak_update_sets_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $seksi = Seksi::create(['nama_seksi' => 'Seksi Test Rak']);
        $rak = \App\Models\RakArsip::create([
            'seksi_id'  => $seksi->id,
            'kode_rak'  => 'RAK-TGT-001',
            'nama_rak'  => 'Test Rack',
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put("/rak/{$rak->id}", [
            'kode_rak'  => 'RAK-TGT-001',
            'nama_rak'  => 'Test Rack Updated',
            'seksi_id'  => $seksi->id,
            'nomor_rak' => '1',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'     => $admin->id,
            'action'      => 'rak.update',
            'target_type' => 'rak',
            'target_id'   => $rak->id,
        ]);

        $log = \App\Models\ActivityLog::where('user_id', $admin->id)
            ->where('action', 'rak.update')
            ->latest()->first();

        $this->assertSame('RAK-TGT-001', $log->target_label);
    }

    // ── Pemulihan kata sandi (lupa kata sandi) tercatat di Activity Log ──
    // Proses OTP berjalan tanpa login, jadi dicatat langsung oleh
    // OtpPasswordController, bukan middleware RecordActivity. Baris "aktivitas"
    // dan "selesai" berbagi target_id (email) agar satu percobaan bisa
    // ditelusuri sampai selesai.

    private function seedOtp(string $email): void
    {
        DB::table('password_reset_tokens')->insert([
            'email'      => $email,
            'token'      => hash('sha256', '123456'),
            'created_at' => now(),
        ]);
    }

    public function test_password_reset_flow_is_logged_from_activity_to_finish(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        // 1) Aktivitas: tombol "Kirim Kode Verifikasi". sendOtp mem-persist
        // token acak, jadi ambil kode OTP yang benar dari mail yang di-fake.
        $this->postJson(route('password.otp.send'), ['email' => $user->email])
            ->assertOk();
        $otp = null;
        Mail::assertSent(\App\Mail\OtpResetPasswordMail::class, function ($mail) use ($user, &$otp) {
            $otp = $mail->otp;
            return $mail->hasTo($user->email);
        });
        $this->assertNotNull($otp, 'OTP harus terkirim ke email');

        $this->assertDatabaseHas('activity_logs', [
            'user_id'      => $user->id,
            'action'       => 'password.otp.send',
            'target_id'    => $user->id,
            'target_type'  => 'user',
            'target_label' => $user->name,
        ]);
        // Kunci penelusuran percobaan = metadata.email
        $this->assertSame(
            $user->email,
            ActivityLog::where('action', 'password.otp.send')->latest()->first()->metadata['email']
        );
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action'  => 'password.otp.send',
        ]);
        $this->assertSame(
            'sukses',
            ActivityLog::where('action', 'password.otp.send')->latest()->first()->metadata['hasil']
        );

        // 2) Verifikasi (berhasil). Pakai OTP yang sama dengan yang dikirim.
        $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp'   => $otp,
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action'  => 'password.otp.verify',
        ]);
        $this->assertSame(
            'sukses',
            ActivityLog::where('action', 'password.otp.verify')->latest()->first()->metadata['hasil']
        );

        // 3) Selesai: kata sandi diganti
        $this->postJson(route('password.otp.reset'), [
            'email'                    => $user->email,
            'otp'                      => $otp,
            'password'                 => 'newpassword',
            'password_confirmation'    => 'newpassword',
        ])->assertOk();

        $this->assertDatabaseHas('activity_logs', [
            'user_id'    => $user->id,
            'action'     => 'password.otp.finish',
            'target_id'  => $user->id,
        ]);
        $this->assertSame(
            'sukses',
            ActivityLog::where('action', 'password.otp.finish')->latest()->first()->metadata['hasil']
        );

        // Satu percobaan lengkap (aktivitas → verifikasi → selesai) berbagi
        // kunci user_id sama sehingga bisa ditelusuri sampai selesai.
        $keys = ActivityLog::query()
            ->where('user_id', $user->id)
            ->whereIn('action', ['password.otp.send', 'password.otp.verify', 'password.otp.finish'])
            ->count();
        $this->assertSame(3, $keys);

        // Ketiga baris — termasuk send & verify — harus muncul di halaman
        // /activity-log (bukan tersembunyi oleh filter route), dan opsi
        // send/verify ikut terdaftar di daftar penyaring "aksi".
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/activity-log')->assertOk()
            ->assertInertia(function ($page) {
                $page->component('Activity/Index');
                $page->where('activities.data.0.action', 'password.otp.send');
                $page->where('activities.data.1.action', 'password.otp.verify');
                $page->where('activities.data.2.action', 'password.otp.finish');
                $page->where('actions', function ($actions) {
                    return collect($actions)->contains('password.otp.send')
                        && collect($actions)->contains('password.otp.verify')
                        && collect($actions)->contains('password.otp.finish');
                });
            });
    }

    public function test_failed_otp_verification_logs_gagal_with_email_key(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'fail@example.com']);
        $this->seedOtp($user->email);

        // Kode salah (token di DB = hash '123456', kirim '000000')
        $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp'   => '000000',
        ])->assertStatus(422);

        $log = ActivityLog::where('action', 'password.otp.verify')
            ->where('user_id', $user->id)->latest()->first();

        $this->assertNotNull($log, 'Verifikasi gagal harus tercatat');
        $this->assertSame('gagal', $log->metadata['hasil']);
        $this->assertStringStartsWith('Gagal', $log->description);
        // Tetap berkey email (di metadata) agar percobaan yang gagal tidak hilang,
        // dan bisa direlasikan ke user lewat user_id.
        $this->assertSame($user->email, $log->metadata['email']);
    }

    public function test_unregistered_email_send_is_not_logged(): void
    {
        Mail::fake();

        $this->postJson(route('password.otp.send'), ['email' => 'nope@example.com'])
            ->assertStatus(422)
            ->assertJson(['error' => 'not-registered']);

        $this->assertDatabaseCount('activity_logs', 0);
    }
}
