<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DokumenArsip extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'dokumen_arsip';

    /** Opsi status pengecekan */
    public const STATUS_PENGECEKAN = ['belum_dicek', 'dalam_pengecekan', 'terverifikasi', 'perlu_perbaikan'];

    /** Jenis dokumen khusus hanya tersedia di Seksi Pemerintahan */
    public const JENIS_DOKUMEN_KHUSUS = ['surat_tanah', 'ahli_waris'];

    /**
     * Tahap 6 — Klasifikasi masa aktif & retensi arsip.
     *
     * Kategori ringan → durasi tetap 5 tahun.
     * Kategori permanen → tidak ada tanggal kedaluwarsa.
     * Kategori kustom → durasi diambil dari kolom retensi_tahun.
     */
    public const RETENSI_KATEGORI = ['ringan_5_tahun', 'permanen', 'kustom'];
    public const RETENSI_KATEGORI_DEFAULT = 'ringan_5_tahun';
    public const RETENSI_KATEGORI_PERMANEN = 'permanen';
    public const RETENSI_KATEGORI_KUSTOM = 'kustom';
    public const RETENSI_RINGAN_TAHUN = 5;

    /** Status turunan yang dapat dipakai untuk filter daftar. */
    public const RETENSI_STATUS_AKTIF = 'aktif';
    public const RETENSI_STATUS_AKAN_KEDALUWARSA = 'akan_kedaluwarsa';
    public const RETENSI_STATUS_KEDALUWARSA = 'kedaluwarsa';
    public const RETENSI_STATUS_DIPERTAHANKAN = 'dipertahankan';

    /** Ambang peringatan "akan kedaluwarsa" dalam hari. */
    public const RETENSI_PERINGATAN_HARI = 30;

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
        // Tahap 4 — Dokumen Khusus Pemerintahan
        'jenis_dokumen',
        'nama_pemilik',
        'nik_pemilik',
        'alamat_objek',
        'nomor_alas_hak',
        'keterangan_profiling',
        'status_pengecekan',
        // Tahap 5 — Enkripsi File
        'is_encrypted',
        'file_hash',
        'encrypted_at',
        // Tahap 6 — Retensi Arsip
        'retensi_kategori',
        'retensi_tahun',
        'tanggal_kedaluwarsa',
        'retensi_dipertahankan',
        'retensi_ditindak_waktu',
        'retensi_ditindak_oleh',
        'catatan_retensi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_surat' => 'date',
            'tanggal_diterima' => 'date',
            'batas_pengembalian' => 'datetime',
            'is_encrypted' => 'boolean',
            'encrypted_at' => 'datetime',
            'tanggal_kedaluwarsa' => 'date',
            'retensi_dipertahankan' => 'boolean',
            'retensi_ditindak_waktu' => 'datetime',
            'retensi_tahun' => 'integer',
        ];
    }

    /** Apakah dokumen ini jenis khusus (surat_tanah / ahli_waris)? */
    public function isDokumenKhusus(): bool
    {
        return in_array($this->jenis_dokumen, self::JENIS_DOKUMEN_KHUSUS, true);
    }

    /**
     * Tahap 6 — hitung ulang tanggal kedaluwarsa berdasarkan kategori.
     *
     * Dipanggil di controller sebelum menyimpan arsip agar kolom
     * `tanggal_kedaluwarsa` selalu konsisten dengan `retensi_kategori`.
     */
    public function perbaruiTanggalKedaluwarsa(?Carbon $tanggalDasar = null): void
    {
        $kategori = $this->retensi_kategori ?? self::RETENSI_KATEGORI_DEFAULT;
        $dasar = $tanggalDasar ?? $this->tanggal_surat ?? now();

        if ($kategori === self::RETENSI_KATEGORI_PERMANEN) {
            $this->tanggal_kedaluwarsa = null;
            $this->retensi_tahun = null;
            return;
        }

        $tahun = $this->retensi_tahun ?? self::RETENSI_RINGAN_TAHUN;

        if ($kategori === self::RETENSI_KATEGORI_KUSTOM && $tahun < 1) {
            $tahun = 1;
        }

        $this->tanggal_kedaluwarsa = $dasar->copy()->addYears($tahun);
    }

    /**
     * Tahap 6 — status retensi turunan yang dipakai untuk badge & filter.
     * Urutan prioritas:
     *   1. dipertahankan (tindakan admin tercatat)
     *   2. permanen (tidak ada tanggal kedaluwarsa)
     *   3. kedaluwarsa (tanggal sudah lewat)
     *   4. akan_kedaluwarsa (dalam ambang peringatan)
     *   5. aktif (default)
     */
    public function statusRetensi(): string
    {
        if ($this->retensi_dipertahankan) {
            return self::RETENSI_STATUS_DIPERTAHANKAN;
        }

        if ($this->retensi_kategori === self::RETENSI_KATEGORI_PERMANEN || $this->tanggal_kedaluwarsa === null) {
            return self::RETENSI_STATUS_AKTIF;
        }

        $hariIni = now()->startOfDay();
        $kedaluwarsa = $this->tanggal_kedaluwarsa->copy()->startOfDay();

        if ($kedaluwarsa->lt($hariIni)) {
            return self::RETENSI_STATUS_KEDALUWARSA;
        }

        if ($kedaluwarsa->lte($hariIni->addDays(self::RETENSI_PERINGATAN_HARI))) {
            return self::RETENSI_STATUS_AKAN_KEDALUWARSA;
        }

        return self::RETENSI_STATUS_AKTIF;
    }

    /**
     * Tahap 6 — aksesori boolean untuk template.
     */
    public function isKedaluwarsa(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->statusRetensi() === self::RETENSI_STATUS_KEDALUWARSA,
        );
    }

    public function isAkanKedaluwarsa(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->statusRetensi() === self::RETENSI_STATUS_AKAN_KEDALUWARSA,
        );
    }

    public function isRetensiDipertahankan(): Attribute
    {
        return Attribute::make(
            get: fn () => (bool) $this->retensi_dipertahankan,
        );
    }

    public function retensiPelaksana(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retensi_ditindak_oleh');
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

    public function riwayatPengecekan(): HasMany
    {
        return $this->hasMany(RiwayatPengecekan::class, 'dokumen_arsip_id')->with('user:id,name,role');
    }

    /**
     * Tahap 6 — scope query filter status retensi turunan.
     *
     * Dipakai di `ArsipController::filteredQuery()` untuk filter
     * `status_retensi` (aktif/akan_kedaluwarsa/kedaluwarsa/dipertahankan).
     */
    public function scopeRetensiStatus(Builder $query, string $status): Builder
    {
        $hariIni = now()->startOfDay();

        return match ($status) {
            self::RETENSI_STATUS_DIPERTAHANKAN => $query->where('retensi_dipertahankan', true),
            self::RETENSI_STATUS_KEDALUWARSA => $query
                ->where('retensi_dipertahankan', false)
                ->whereNotNull('tanggal_kedaluwarsa')
                ->whereDate('tanggal_kedaluwarsa', '<', $hariIni),
            self::RETENSI_STATUS_AKAN_KEDALUWARSA => $query
                ->where('retensi_dipertahankan', false)
                ->whereNotNull('tanggal_kedaluwarsa')
                ->whereDate('tanggal_kedaluwarsa', '>=', $hariIni)
                ->whereDate('tanggal_kedaluwarsa', '<=', $hariIni->addDays(self::RETENSI_PERINGATAN_HARI)),
            self::RETENSI_STATUS_AKTIF => $query
                ->where(function (Builder $q) use ($hariIni) {
                    $q->where(function ($inner) {
                        $inner->whereNull('tanggal_kedaluwarsa')
                            ->orWhere('retensi_kategori', self::RETENSI_KATEGORI_PERMANEN);
                    })
                        ->orWhere(function ($inner) use ($hariIni) {
                            $inner->whereNotNull('tanggal_kedaluwarsa')
                                ->whereDate('tanggal_kedaluwarsa', '>', $hariIni->addDays(self::RETENSI_PERINGATAN_HARI));
                        })
                        ->where('retensi_dipertahankan', false);
                }),
            default => $query,
        };
    }
}

