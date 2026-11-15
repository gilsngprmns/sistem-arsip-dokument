<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatPengecekan extends Model
{
    protected $table = 'riwayat_pengecekan_arsip';

    protected $fillable = [
        'dokumen_arsip_id',
        'user_id',
        'status_sebelum',
        'status_sesudah',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function dokumenArsip(): BelongsTo
    {
        return $this->belongsTo(DokumenArsip::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
