<?php

namespace App\Http\Requests;

use App\Models\DokumenArsip;
use App\Models\Seksi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreArsipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user->role === 'admin') {
            return true;
        }

        return (int) $this->input('seksi_id') === (int) $user->seksi_id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDokumenKhusus = in_array($this->input('jenis_dokumen'), DokumenArsip::JENIS_DOKUMEN_KHUSUS, true);

        // Guard: jenis khusus hanya boleh di Seksi Pemerintahan
        $seksi = Seksi::find($this->input('seksi_id'));
        if ($isDokumenKhusus && $seksi && ! $seksi->isPemerintahan()) {
            $this->merge(['jenis_dokumen' => 'umum']);
            $isDokumenKhusus = false;
        }

        $rules = [
            'nomor_surat' => ['required', 'string', 'max:150'],
            'perihal' => ['required', 'string', 'max:255'],
            'jenis_surat' => ['required', Rule::in(['masuk', 'keluar'])],
            'sifat_surat' => ['nullable', Rule::in(['biasa', 'penting', 'segera', 'rahasia'])],
            'tanggal_surat' => ['required', 'date'],
            'tanggal_diterima' => ['nullable', 'date'],
            'asal_instansi' => ['nullable', 'string', 'max:150'],
            'tujuan_instansi' => ['nullable', 'string', 'max:150'],
            'seksi_id' => ['required', 'exists:seksi,id'],
            'rak_id' => [
                'required',
                Rule::exists('rak_arsip', 'id')->where(function ($query) {
                    $query->where('is_active', true);
                    $seksiId = $this->input('seksi_id');
                    if ($seksiId) {
                        $query->where(function ($inner) use ($seksiId) {
                            $inner->where('seksi_id', $seksiId)->orWhereNull('seksi_id');
                        });
                    }
                }),
            ],
            'boks_id' => [
                'required',
                function ($attribute, $value, $fail) {
                    $boks = \App\Models\BoksArsip::find($value);
                    if (! $boks) {
                        $fail('Boks tidak ditemukan.');
                        return;
                    }
                    $rakId = $this->input('rak_id');
                    if ($rakId && (int) $boks->rak_id !== (int) $rakId) {
                        $fail('Boks harus sesuai dengan rak yang dipilih.');
                        return;
                    }
                    $count = $boks->dokumenArsip()->count();
                    if ($boks->kapasitas !== null && $count >= $boks->kapasitas) {
                        $fail("Boks {$boks->kode_boks} sudah penuh ({$count}/{$boks->kapasitas}). Pilih boks lain.");
                    }
                },
            ],
            'keterangan_posisi' => ['nullable', 'string'],
            'catatan' => ['nullable', 'string'],
            'status_arsip' => ['nullable', Rule::in(['tersedia', 'dipinjam', 'hilang'])],

            // Tahap 4 — Klasifikasi dokumen
            'jenis_dokumen' => ['nullable', Rule::in(['umum', 'surat_tanah', 'ahli_waris'])],

            // Kolom profiling — wajib jika dokumen khusus
            'nama_pemilik' => $isDokumenKhusus
                ? ['required', 'string', 'max:150']
                : ['nullable', 'string', 'max:150'],
            'nik_pemilik' => ['nullable', 'string', 'max:20', 'regex:/^\d{0,20}$/'],
            'alamat_objek' => ['nullable', 'string'],
            'nomor_alas_hak' => ['nullable', 'string', 'max:150'],
            'keterangan_profiling' => ['nullable', 'string'],

            'status_pengecekan' => ['nullable', Rule::in(DokumenArsip::STATUS_PENGECEKAN)],

            // Upload file: dokumen khusus → PDF only; umum → format lama
            'lampiran' => $isDokumenKhusus
                ? ['nullable', 'file', 'mimes:pdf', 'max:5120']
                : ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],

            // Tahap 6 — Klasifikasi masa aktif & retensi arsip
            'retensi_kategori' => ['nullable', Rule::in(DokumenArsip::RETENSI_KATEGORI)],
            'retensi_tahun' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];

        return $rules;
    }

    /**
     * Validasi khusus: jenis_dokumen khusus hanya diizinkan di Seksi Pemerintahan.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $jenis = $this->input('jenis_dokumen', 'umum');
            $seksi = Seksi::find($this->input('seksi_id'));

            if (in_array($jenis, DokumenArsip::JENIS_DOKUMEN_KHUSUS, true) && $seksi && ! $seksi->isPemerintahan()) {
                $validator->errors()->add(
                    'jenis_dokumen',
                    'Klasifikasi Surat Tanah / Ahli Waris hanya tersedia untuk Seksi Pemerintahan.'
                );
            }

            // Tahap 6: kategori kustom wajib memberi durasi tahun (1–100)
            if ($this->input('retensi_kategori') === 'kustom' && $this->input('retensi_tahun') === null) {
                $validator->errors()->add(
                    'retensi_tahun',
                    'Durasi retensi (tahun) wajib diisi untuk kategori Kustom.'
                );
            }
        });
    }
}
