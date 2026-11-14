<?php

namespace App\Http\Requests;

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
        return [
            'kode_arsip' => ['required', 'string', 'max:50', Rule::unique('dokumen_arsip', 'kode_arsip')],
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
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
