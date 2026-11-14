<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenArsip extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dokumen_arsip';

    protected $fillable = [
        'kode_arsip',
        'nomor_surat',
        'perihal',
        'jenis_surat',
        'sifat_surat',
        'tanggal_surat',
        'tanggal_diterima',
        'asal_instansi',
        'tujuan_instansi',
        'seksi_id',
        'rak_id',
        'boks_id',
        'keterangan_posisi',
        'file_path',
        'qr_code_path',
        'status_arsip',
        'batas_pengembalian',
        'dicatat_oleh',
        'catatan',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_diterima' => 'date',
            'batas_pengembalian' => 'datetime',
        ];
    }

    public function seksi(): BelongsTo
    {
        return $this->belongsTo(Seksi::class);
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function rak(): BelongsTo
    {
        return $this->belongsTo(RakArsip::class, 'rak_id');
    }

    public function boks(): BelongsTo
    {
        return $this->belongsTo(BoksArsip::class, 'boks_id');
    }

    public function riwayatSirkulasi(): HasMany
    {
        return $this->hasMany(RiwayatSirkulasi::class);
    }
}
