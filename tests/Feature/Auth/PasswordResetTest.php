<?php

namespace Tests\Feature\Auth;

use App\Mail\OtpResetPasswordMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_otp_can_be_sent_to_valid_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Clear throttle untuk user ini
        RateLimiter::clear('otp-send:'.$user->email);

        $response = $this->postJson(route('password.otp.send'), [
            'email' => $user->email,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'otp-sent']);

        Mail::assertSent(OtpResetPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Token harus tersimpan sebagai hash di database (bukan OTP plain)
        $token = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($token);
        // Hash tidak boleh sama dengan string OTP 6 digit
        $this->assertNotEquals('123456', $token->token);
        $this->assertEquals(64, strlen($token->token)); // SHA-256 produces 64 char hex
    }

    public function test_otp_send_rejects_unregistered_email_with_error(): void
    {
        Mail::fake();

        $response = $this->postJson(route('password.otp.send'), [
            'email' => 'nonexistent@example.com',
        ]);

        // Aturan 2026-11-07: email tak terdaftar ditolak eksplisit (banner merah di UI),
        // bukan lagi respons sukses untuk menutupi (keputusan owner).
        $response->assertStatus(422);
        $response->assertJson(['error' => 'not-registered']);

        // Tidak ada email yang dikirim
        Mail::assertNotSent(OtpResetPasswordMail::class);
    }

    public function test_otp_can_be_verified_correctly(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Simpan token OTP yang sudah di-hash langsung ke database
        $otpCode = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', $otpCode),
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => $otpCode,
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'otp-verified']);
    }

    public function test_invalid_otp_fails_verification(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '000000',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'otp', 'message' => 'Kode OTP salah. Silahkan periksa kembali.']);
    }

    public function test_password_can_be_reset_with_valid_otp(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Simpan token OTP
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now(),
        ]);

        $response = $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'password-reset']);

        // Verifikasi password baru sudah tersimpan
        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));

        // Token harus dihapus setelah sukses
        $this->assertNull(DB::table('password_reset_tokens')->where('email', $user->email)->first());
    }

    public function test_password_reset_fails_with_invalid_otp(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson(route('password.otp.reset'), [
            'email' => $user->email,
            'otp' => '000000',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('otp');
    }

    public function test_otp_expires_after_3_minutes(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Simpan token dengan created_at 4 menit yang lalu (melebihi batas 3 menit)
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now()->subMinutes(4),
        ]);

        $response = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        // Setelah melewati 3 menit, token dianggap kadaluarsa
        $response->assertStatus(422);
        $response->assertJson(['error' => 'otp', 'message' => 'Kode OTP telah kadaluarsa. Silahkan minta kode baru.']);
    }

    public function test_otp_still_valid_within_3_minutes(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        // Token dibuat 2 menit yang lalu, masih di dalam batas 3 menit
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now()->subMinutes(2),
        ]);

        $response = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '123456',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'otp-verified']);
    }

    public function test_verify_otp_blocked_after_3_failures(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now(),
        ]);

        $key = 'otp-verify:'.mb_strtolower($user->email);
        RateLimiter::clear($key);

        // 3x salah → 422 setiap kali
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->postJson(route('password.otp.verify'), [
                'email' => $user->email,
                'otp' => '000000',
            ]);
            $resp->assertStatus(422);
        }

        // Request ke-4 → 429 rate-limited
        $resp = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '123456',
        ]);
        $resp->assertStatus(429);
        $resp->assertJson(['error' => 'rate-limited']);
        $resp->assertJsonStructure(['retryAfter']);

        RateLimiter::clear($key);
    }

    public function test_resend_blocked_after_5_requests(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'test@example.com']);

        $sendKey = 'otp-send:'.$user->email;
        $resendKey = 'otp-resend:'.mb_strtolower($user->email);
        RateLimiter::clear($sendKey);
        RateLimiter::clear($resendKey);

        // 5x sendOtp sukses → hit resend key
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::clear($sendKey);
            $resp = $this->postJson(route('password.otp.send'), [
                'email' => $user->email,
            ]);
            $resp->assertOk();
        }

        // Request ke-6 → 429 rate-limited
        RateLimiter::clear($sendKey);
        $resp = $this->postJson(route('password.otp.send'), [
            'email' => $user->email,
        ]);
        $resp->assertStatus(429);
        $resp->assertJson(['error' => 'rate-limited']);
        $resp->assertJsonStructure(['retryAfter']);

        RateLimiter::clear($resendKey);
    }

    public function test_successful_verify_clears_verify_rate_limit(): void
    {
        $user = User::factory()->create(['email' => 'test@example.com']);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => hash('sha256', '123456'),
            'created_at' => now(),
        ]);

        $key = 'otp-verify:'.mb_strtolower($user->email);
        RateLimiter::clear($key);

        // Hit limiter 2x dengan OTP salah
        for ($i = 0; $i < 2; $i++) {
            $this->postJson(route('password.otp.verify'), [
                'email' => $user->email,
                'otp' => '000000',
            ]);
        }
        $this->assertSame(2, RateLimiter::attempts($key));

        // Verifikasi dengan OTP benar → clear
        $resp = $this->postJson(route('password.otp.verify'), [
            'email' => $user->email,
            'otp' => '123456',
        ]);
        $resp->assertOk();
        $this->assertSame(0, RateLimiter::attempts($key));

        RateLimiter::clear($key);
    }
}