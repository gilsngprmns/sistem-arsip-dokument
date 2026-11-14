<?php

namespace App\Http\Controllers;

use App\Models\RakArsip;
use App\Models\Seksi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RakArsipController extends Controller
{
    public function index(): Response
    {
        $rak = RakArsip::query()
            ->with('seksi:id,nama_seksi')
            ->orderBy('kode_rak')
            ->get();

        return Inertia::render('Rak/Index', [
            'rak' => $rak,
            'seksiOptions' => Seksi::query()
                ->orderByOrganisasi()
                ->get(['id', 'nama_seksi']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'seksi_id' => ['required', 'exists:seksi,id'],
            'kode_rak' => ['required', 'string', 'max:50', 'unique:rak_arsip,kode_rak'],
            'nama_rak' => ['required', 'string', 'max:150'],
            'nomor_rak' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        RakArsip::create($validated);

        return redirect()->route('rak.index')->with('success', 'Rak arsip berhasil ditambahkan.');
    }

    public function update(Request $request, RakArsip $rak): RedirectResponse
    {
        $validated = $request->validate([
            'seksi_id' => ['required', 'exists:seksi,id'],
            'kode_rak' => ['required', 'string', 'max:50', 'unique:rak_arsip,kode_rak,'.$rak->id],
            'nama_rak' => ['required', 'string', 'max:150'],
            'nomor_rak' => ['nullable', 'string', 'max:50'],
            'deskripsi' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $rak->update($validated);

        return redirect()->route('rak.index')->with('success', 'Rak arsip berhasil diperbarui.');
    }

    public function destroy(RakArsip $rak): RedirectResponse
    {
        if ($rak->dokumenArsip()->count() > 0) {
            return redirect()->route('rak.index')
                ->with('error', "Rak {$rak->kode_rak} masih berisi arsip. Pindahkan arsip terlebih dahulu.");
        }

        $rak->delete();

        return redirect()->route('rak.index')->with('danger', 'Rak arsip berhasil dihapus.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:rak_arsip,id',
        ])['ids'];

        $deleted = RakArsip::whereIn('id', $ids)
            ->whereDoesntHave('dokumenArsip')
            ->delete();

        session()->flash('activity_log_metadata', [
            'jenis' => 'hapus_rak',
            'status' => 'sukses',
            'jumlah_dihapus' => $deleted,
            'ids' => $ids,
        ]);

        return redirect()->route('rak.index')
            ->with('danger', "$deleted rak arsip berhasil dihapus.");
    }
}
