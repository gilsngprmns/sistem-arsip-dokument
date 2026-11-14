<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RakArsip extends Model
{
    protected $table = 'rak_arsip';

    /** Konstanta gedung — satu-satunya gedung arsip kelurahan (tanpa CRUD). */
    public const GEDUNG = 'Gedung Kelurahan Grogol Selatan';

    /** Konstanta ruang — ruang arsip tetap (tanpa CRUD). */
    public const RUANG = 'Ruang Arsip';

    protected $fillable = [
        'seksi_id',
        'kode_rak',
        'nama_rak',
        'nomor_rak',
        'deskripsi',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function seksi(): BelongsTo
    {
        return $this->belongsTo(Seksi::class, 'seksi_id');
    }

    public function boksArsip(): HasMany
    {
        return $this->hasMany(BoksArsip::class, 'rak_id');
    }

    public function dokumenArsip(): HasMany
    {
        return $this->hasMany(DokumenArsip::class, 'rak_id');
    }
}
