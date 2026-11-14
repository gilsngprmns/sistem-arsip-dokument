<?php

namespace App\Http\Controllers;

use App\Models\BoksArsip;
use App\Models\RakArsip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class BoksArsipController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Boks/Index', $this->boksData());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rak_id' => ['required', 'exists:rak_arsip,id'],
            'kode_boks' => ['required', 'string', 'max:50', 'unique:boks_arsip,kode_boks'],
            'kapasitas' => ['required', 'integer', 'min:1'],
            'nomor_baris' => ['nullable', 'string', 'max:20'],
            'nomor_boks' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        BoksArsip::create($validated);

        return $this->redirectWithBoks('Boks arsip berhasil ditambahkan.');
    }

    public function update(Request $request, BoksArsip $boks): RedirectResponse
    {
        $validated = $request->validate([
            'rak_id' => ['required', 'exists:rak_arsip,id'],
            'kode_boks' => ['required', 'string', 'max:50', 'unique:boks_arsip,kode_boks,'.$boks->id],
            'kapasitas' => ['required', 'integer', 'min:1'],
            'nomor_baris' => ['nullable', 'string', 'max:20'],
            'nomor_boks' => ['nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        $boks->update($validated);

        return $this->redirectWithBoks('Boks arsip berhasil diperbarui.');
    }

    public function destroy(BoksArsip $boks): RedirectResponse
    {
        if ($boks->dokumenArsip()->count() > 0) {
            return redirect()->route('boks.index')
                ->with('error', "Boks {$boks->kode_boks} masih berisi arsip. Pindahkan arsip terlebih dahulu.");
        }

        $boks->delete();

        return $this->redirectWithBoks('Boks arsip berhasil dihapus.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:boks_arsip,id',
        ])['ids'];

        $data = $this->boksData();

        $deleted = BoksArsip::whereIn('id', $ids)
            ->whereDoesntHave('dokumenArsip')
            ->delete();

        session()->flash('activity_log_metadata', [
            'jenis' => 'hapus_boks',
            'status' => 'sukses',
            'jumlah_dihapus' => $deleted,
            'ids' => $ids,
        ]);

        return redirect()
            ->route('boks.index')
            ->with('success', "$deleted boks arsip berhasil dihapus.")
            ->with('inertia.shared.boks', $data['boks'])
            ->with('inertia.shared.rakList', $data['rakList']);
    }

    /**
     * Halaman QR code untuk satu boks — generate & simpan file PNG,
     * lalu render halaman Inertia untuk ditampilkan/dicetak.
     */
    public function qr(BoksArsip $boks): Response
    {
        $qrUrl = route('scan.boks', $boks->kode_boks, true);

        Storage::disk('public')->makeDirectory('qrcodes-boks');

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
        $filename = "boks-{$safeName}.png";
        $relativePath = "qrcodes-boks/{$filename}";

        if (! Storage::disk('public')->exists($relativePath)) {
            try {
                $contents = QrCode::format('png')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($relativePath, $contents);
            } catch (\Throwable) {
                // Fallback ke SVG bila PNG gagal (GD/Imagick unavailable)
                $relativePath = "qrcodes-boks/boks-{$safeName}.svg";
                $contents = QrCode::format('svg')->size(300)->generate($qrUrl);
                Storage::disk('public')->put($relativePath, $contents);
            }
            // Simpan path ke database agar observer dan halaman lain membaca dari DB
            $boks->update(['qr_path' => $relativePath]);
        }

        $boks->load(['rak:id,kode_rak,nama_rak']);
        $boks->loadCount('dokumenArsip');

        return Inertia::render('Boks/QrCode', [
            'boks' => $boks,
            'qrPath' => $relativePath,
            'qrUrl' => $qrUrl,
        ]);
    }

    /**
     * Cari boks berdasarkan kode_boks (dipanggil dari route scan publik).
     * Data yang ditampilkan = informasi lokasi + daftar dokumen (tidak sensitif).
     */
    public function showByKode(string $kodeBoks): Response
    {
        $boks = BoksArsip::query()
            ->where('kode_boks', $kodeBoks)
            ->where('is_active', true)
            ->first();

        if (! $boks) {
            abort(404, 'Boks tidak ditemukan atau sudah tidak aktif.');
        }

        $boks->load(['rak:id,kode_rak,nama_rak,seksi_id']);
        $boks->loadCount('dokumenArsip');

        $dokumen = $boks->dokumenArsip()
            ->with(['seksi:id,nama_seksi'])
            ->latest('created_at')
            ->get(['id', 'kode_arsip', 'perihal', 'seksi_id', 'status_arsip']);

        return Inertia::render('Scan/BoksResult', [
            'boks' => $boks,
            'dokumen' => $dokumen,
            'previousUrl' => request()->header('referer'),
        ]);
    }

    /**
     * Data prop bersama untuk Inertia::render maupun reload setelah
     * store/update/destroy — Inertia mengambil boksData() pada reload.
     */
    private function boksData(): array
    {
        $boksList = BoksArsip::query()
            ->with('rak:id,kode_rak,nama_rak')
            ->withCount('dokumenArsip')
            ->orderBy('kode_boks')
            ->get();

        // qr_path disimpan oleh BoksArsipObserver::ensureQr().
        // Namun defensive: cek filesystem jika DB masih kosong (migration belum jalan / data lama).
        $boksList = $boksList->map(function ($boks) {
            if (!empty($boks->qr_path)) {
                return $boks;
            }
            $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $boks->kode_boks) ?: 'boks';
            $pngPath = "qrcodes-boks/boks-{$safeName}.png";
            $svgPath = "qrcodes-boks/boks-{$safeName}.svg";
            if (Storage::disk('public')->exists($pngPath)) {
                $boks->qr_path = $pngPath;
            } elseif (Storage::disk('public')->exists($svgPath)) {
                $boks->qr_path = $svgPath;
            }
            return $boks;
        });

        return [
            'boks' => $boksList,
            'rakList' => RakArsip::query()
                ->where('is_active', true)
                ->orderBy('kode_rak')
                ->get(['id', 'kode_rak', 'nama_rak']),
        ];
    }

    private function redirectWithBoks(string $success): RedirectResponse
    {
        $data = $this->boksData();

        return redirect()
            ->route('boks.index')
            ->with('success', $success)
            // Inertia mengambil prop sesi ini otomatis untuk reload —
            // tabel langsung segar setelah simpan/hapus tanpa full-fetch.
            ->with('inertia.shared.boks', $data['boks'])
            ->with('inertia.shared.rakList', $data['rakList']);
    }
}
