<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpResetPasswordMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class OtpPasswordController extends Controller
{
    /**
     * Tampilkan halaman multi-step lupa kata sandi.
     */
    public function index(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Kirim OTP 6 digit ke email user.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = $request->email;

        // Layer 1: jeda antar-request (anti spam), 3 hit / 60 detik
        $throttleKey = 'otp-send:'.$email;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return response()->json([
                'error' => 'email',
                'message' => 'Terlalu banyak permintaan. Tunggu beberapa menit sebelum mencoba lagi.',
            ], 429);
        }
        RateLimiter::hit($throttleKey, 60);

        // Layer 2: kumulatif — 5x dalam 24 jam → blokir
        $resendKey = 'otp-resend:'.mb_strtolower($email);
        if (RateLimiter::tooManyAttempts($resendKey, 5)) {
            $retryAfter = RateLimiter::availableIn($resendKey);
            return response()->json([
                'error' => 'rate-limited',
                'message' => 'Kirim ulang terkunci. Silahkan coba lagi dalam '.ceil($retryAfter / 3600).' jam.',
                'retryAfter' => $retryAfter,
            ], 429);
        }

        // Cek apakah email ada di tabel users.
        // Aturan baru (2026-11-07): email yang tidak terdaftar DITOLAK eksplisit
        // dengan pesan merah di UI (keputusan owner), bukan ditutupi respons sukses.
        $user = User::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'not-registered',
                'message' => 'Email tidak terdaftar di sistem kami. Hubungi admin kelurahan untuk membuat akun Anda.',
            ], 422);
        }

        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $hashedOtp = hash('sha256', $otp);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $hashedOtp,
                'created_at' => now(),
            ]
        );

        Mail::to($email)->send(new OtpResetPasswordMail($otp, $email));

        // Hit kumulatif resend setelah email sukses terkirim
        RateLimiter::hit($resendKey, 86400);

        $this->logOtp($request, 'password.otp.send', 'Membuka pemulihan kata sandi (lupa kata sandi).', [
            'hasil' => 'sukses',
            'metode' => 'otp-email',
            'status' => 200,
        ], $user);

        return response()->json([
            'status' => 'otp-sent',
        ]);
    }

    /**
     * Catat aktivitas pemulihan kata sandi ke Activity Log.
     *
     * Proses reset kata sandi via OTP berjalan tanpa login, sehingga middleware
     * RecordActivity (yang hanya mencatat user terautentikasi) tidak bisa
     * menangkapnya. Karena itu baris log dibuat langsung di sini. Baris log
     * sengaja memakai nama jalur yang TIDAK terdaftar di daftar penyaring
     * halaman Log Activity, agar tetap tampil di /activity-log (halaman itu
     * menyaring per route_name).
     *
     * Setiap baris menggunakan key target sama (id email) sehingga admin bisa
     * menelusuri satu percobaan spesifik dari "aktivitas" (awal) sampai
     * "selesai" (password berhasil diganti).
     */
    private function logOtp(Request $request, string $action, string $description, array $extra, ?User $user = null): void
    {
        $email = $request->input('email');

        // target_id adalah kolom integer. Untuk baris OTP (yang berjalan tanpa
        // login), kunci "email" disimpan di metadata['email'] sebagai pengganti
        // target_id, karena satu percobaan yang sama (awal → verifikasi → selesai)
        // berbagi email yang sama.
        ActivityLog::create([
            'user_id'      => $user?->id,
            'action'       => $action,
            'route_name'   => $action,
            'method'       => 'POST',
            'description'  => $description,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => array_merge(['email' => $email], $extra),
            'old_values'   => null,
            'new_values'   => null,
            'target_type'  => $user?->id ? 'user' : null,
            'target_id'    => $user?->id,
            'target_label' => $user?->name ?? $email,
        ]);
    }

    /**
     * Verifikasi OTP yang dimasukkan user.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
        ]);

        $email = $request->email;
        $otp = $request->otp;

        // Rate limiter: 3x salah → blokir 15 menit (per email)
        $verifyKey = 'otp-verify:'.mb_strtolower($email);
        if (RateLimiter::tooManyAttempts($verifyKey, 3)) {
            $retryAfter = RateLimiter::availableIn($verifyKey);
            return response()->json([
                'error' => 'rate-limited',
                'message' => 'Terlalu banyak percobaan salah. Silahkan tunggu '.ceil($retryAfter / 60).' menit sebelum mencoba lagi.',
                'retryAfter' => $retryAfter,
            ], 429);
        }

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        $user = User::where('email', $email)->first();

        if (!$record) {
            RateLimiter::hit($verifyKey, 900);

            $this->logOtp($request, 'password.otp.verify', 'Gagal verifikasi kode pemulihan kata sandi: tidak ada kode aktif.', [
                'hasil' => 'gagal',
                'status' => 422,
            ], $user);

            return response()->json([
                'error' => 'otp',
                'message' => 'Kode OTP salah atau kadaluarsa.',
            ], 422);
        }

        // Check expiry 3 minutes
        if (now()->diffInMinutes($record->created_at) < -3) {
            DB::table('password_reset_tokens')
                ->where('email', $email)
                ->delete();

            RateLimiter::hit($verifyKey, 900);

            $this->logOtp($request, 'password.otp.verify', 'Gagal verifikasi kode pemulihan kata sandi: kode telah kadaluarsa.', [
                'hasil' => 'gagal',
                'status' => 422,
            ], $user);

            return response()->json([
                'error' => 'otp',
                'message' => 'Kode OTP telah kadaluarsa. Silahkan minta kode baru.',
            ], 422);
        }

        if (!hash_equals($record->token, hash('sha256', $otp))) {
            RateLimiter::hit($verifyKey, 900);

            $this->logOtp($request, 'password.otp.verify', 'Gagal verifikasi kode pemulihan kata sandi: kode tidak cocok.', [
                'hasil' => 'gagal',
                'status' => 422,
            ], $user);

            return response()->json([
                'error' => 'otp',
                'message' => 'Kode OTP salah. Silahkan periksa kembali.',
            ], 422);
        }

        RateLimiter::clear($verifyKey);

        $this->logOtp($request, 'password.otp.verify', 'Verifikasi kode pemulihan kata sandi berhasil.', [
            'hasil' => 'sukses',
            'status' => 200,
        ], $user);

        return response()->json([
            'status' => 'otp-verified',
        ]);
    }

    /**
     * Reset password setelah OTP divalidasi.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $request->email;
        $otp = $request->otp;

        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->first();

        if (!$record || now()->diffInMinutes($record->created_at) < -3
            || !hash_equals($record->token, hash('sha256', $otp))) {
            $this->logOtp($request, 'password.otp.reset', 'Gagal mengatur ulang kata sandi: kode verifikasi salah atau kadaluarsa.', [
                'hasil' => 'gagal',
                'status' => 422,
            ]);

            throw ValidationException::withMessages([
                'otp' => ['Kode OTP salah atau kadaluarsa.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->logOtp($request, 'password.otp.reset', 'Gagal mengatur ulang kata sandi: pengguna tidak ditemukan.', [
                'hasil' => 'gagal',
                'status' => 422,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Pengguna tidak ditemukan.'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->remember_token = Str::random(60);
        $user->save();

        // Hapus token setelah sukses
        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        // Bersihkan counter rate limiter
        RateLimiter::clear('otp-verify:'.mb_strtolower($email));
        RateLimiter::clear('otp-resend:'.mb_strtolower($email));

        $this->logOtp($request, 'password.otp.finish', 'Selesai mengganti kata sandi: akun dikembalikan normal.', [
            'hasil' => 'sukses',
            'status' => 200,
        ], $user);

        return response()->json([
            'status' => 'password-reset',
        ]);
    }
}
