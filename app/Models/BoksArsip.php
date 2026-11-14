<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoksArsip extends Model
{
    protected $table = 'boks_arsip';

    protected $fillable = [
        'rak_id',
        'kode_boks',
        'kapasitas',
        'nomor_baris',
        'nomor_boks',
        'keterangan',
        'is_active',
        'qr_path',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'kapasitas' => 'integer',
        ];
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(RakArsip::class, 'rak_id');
    }

    public function dokumenArsip(): HasMany
    {
        return $this->hasMany(DokumenArsip::class, 'boks_id');
    }
}
