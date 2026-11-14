<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $attemptedEmail = $this->string('email')->toString();
        $existingUser = User::where('email', $attemptedEmail)->first();

        // Tolak login untuk akun yang sudah dinonaktifkan (manual admin atau
        // otomatis oleh lockout brute-force) — pesan spesifik, bukan generik.
        if ($existingUser && ! $existingUser->is_active) {
            throw ValidationException::withMessages([
                'email' => User::LOCKED_ACCOUNT_MESSAGE,
            ]);
        }

        $authenticated = Auth::attempt([
            'email' => $attemptedEmail,
            'password' => $this->string('password')->toString(),
            'is_active' => true,
        ], $this->boolean('remember'));

        if ($authenticated) {
            // Login berhasil — reset counter percobaan (jangan biarkan
            // nilai lama dari sesi sebelumnya mengotori statistik berikutnya).
            $this->user()->update(['login_attempts' => 0]);
            RateLimiter::clear($this->throttleKey());

            return;
        }

        // Login gagal: increment counter di DB, lalu BACA ULANG nilainya
        // dari database (jangan percaya return value increment()).
        // Mencapai batas → kunci akun permanen.
        if ($existingUser) {
            User::whereKey($existingUser->id)->increment('login_attempts');

            // Ambil nilai akhir yang tersimpan di DB — sumber kebenaran mutlak.
            $newAttempts = (int) User::whereKey($existingUser->id)
                ->value('login_attempts');
            $existingUser->login_attempts = $newAttempts;

            if ($newAttempts >= User::MAX_LOGIN_ATTEMPTS) {
                // Set is_active=false secara eksplisit & terpisah, agar pasti
                // tersimpan meski increment di atas hanya sempat menaikkan counter.
                $existingUser->is_active = false;
                $existingUser->login_attempts = $newAttempts;
                $existingUser->save();
                throw ValidationException::withMessages([
                    'email' => User::LOCKED_ACCOUNT_MESSAGE,
                ]);
            }
        }

        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
