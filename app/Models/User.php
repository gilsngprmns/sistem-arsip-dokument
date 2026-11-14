<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Batas maksimum percobaan login sebelum akun diblokir otomatis.
     */
    public const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Pesan standar penolakan login untuk akun nonaktif / terkunci.
     */
    public const LOCKED_ACCOUNT_MESSAGE = 'Akun Anda telah dinonaktifkan karena alasan keamanan. Silakan hubungi Admin Tata Usaha untuk pemulihan.';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'avatar_path',
        'password',
        'seksi_id',
        'role',
        'is_active',
        'login_attempts',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'login_attempts' => 'integer',
        ];
    }

    public function seksi(): BelongsTo
    {
        return $this->belongsTo(Seksi::class);
    }

    public function dokumenArsip(): HasMany
    {
        return $this->hasMany(DokumenArsip::class, 'dicatat_oleh');
    }

    public function riwayatSirkulasi(): HasMany
    {
        return $this->hasMany(RiwayatSirkulasi::class);
    }
}
