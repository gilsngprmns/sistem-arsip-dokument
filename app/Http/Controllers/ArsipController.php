<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArsipRequest;
use App\Http\Requests\UpdateArsipRequest;
use App\Models\DokumenArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\BoksArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use App\Exports\DocumentExport;
use Maatwebsite\Excel\Facades\Excel;

class ArsipController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $query = $this->filteredQuery($request);

        return Inertia::render('Arsip/Index', [
            'arsip' => $query->paginate(10)->withQueryString(),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
            'filters' => [
                'seksi_id' => $request->input('seksi_id'),
                'status' => $request->input('status', 'semua'),
                'q' => $request->input('q', ''),
                'tanggal_dari' => $request->input('tanggal_dari', ''),
                'tanggal_sampai' => $request->input('tanggal_sampai', ''),
                'sort' => $request->string('sort', 'terbaru')->toString(),
                'tanpa_lokasi' => $request->boolean('tanpa_lokasi'),
                'terlambat' => $request->boolean('terlambat'),
                'tanpa_file' => $request->boolean('tanpa_file'),
                'metadata_kosong' => $request->boolean('metadata_kosong'),
                'tanpa_qr' => $request->boolean('tanpa_qr'),
            ],
        ]);
    }

    /**
     * Ekspor daftar arsip (mengikuti filter aktif) ke Excel .xlsx.
     */
    public function exportExcel(Request $request)
    {
        $query = $this->filteredQuery($request);

        return Excel::download(
            new DocumentExport($query->get()),
            'arsip-dokumen-'.now()->format('YmdHis').'.xlsx',
        );
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('Arsip/Create', [
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArsipRequest $request)
    {
        $data = $request->safe()->except('lampiran');

        if ($request->hasFile('lampiran')) {
            $data['file_path'] = $request->file('lampiran')->store('lampiran-arsip', 'public');
        }

        DB::transaction(function () use ($request, $data) {
            $arsip = DokumenArsip::create([
                ...$data,
                'dicatat_oleh' => $request->user()->id,
            ]);

            $this->catatSirkulasi($arsip, $request->user()->id, 'dibuat', null, 'Dokumen arsip baru dicatat.');
        });

        if (request()->header('X-Inertia')) {
            // flash() bertahan ke request BERIKUTNYA (full-page-load yang
            // dipicu Inertia::location() ke /arsip), lalu otomatis
            // dikonsumsi dan di-flush. Tidak bocor ke kunjungan berikutnya.
            session()->flash('success', 'Dokumen arsip berhasil ditambahkan.');
            return Inertia::location(route('arsip.index'));
        }

        return redirect()
            ->route('arsip.index')
            ->with('success', 'Dokumen arsip berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(DokumenArsip $arsip): Response
    {
        $this->assertDocumentAccess(request(), $arsip);

        $arsip->load([
            'seksi',
            'rak',
            'boks.rak',
            'riwayatSirkulasi' => fn ($query) => $query->with('user:id,name')->latest('waktu_aksi'),
        ]);

        return Inertia::render('Arsip/Show', [
            'arsip' => $arsip,
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
            'previousUrl' => request()->header('referer'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DokumenArsip $arsip): Response
    {
        $this->assertDocumentAccess(request(), $arsip);

        return Inertia::render('Arsip/Edit', [
            'arsip' => $arsip,
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArsipRequest $request, DokumenArsip $arsip)
    {
        $data = $request->safe()->except('lampiran');

        if ($request->hasFile('lampiran')) {
            if ($arsip->file_path && Storage::disk('public')->exists($arsip->file_path)) {
                Storage::disk('public')->delete($arsip->file_path);
            }

            $data['file_path'] = $request->file('lampiran')->store('lampiran-arsip', 'public');
        }

        $arsip->update($data);

        return redirect()
            ->back()
            ->with('success', 'Dokumen arsip berhasil diperbarui.');
    }

    /**
     * Ambil semua ID dokumen yang cocok dengan filter aktif (tanpa paginasi).
     * Digunakan frontend untuk "Select All" melintasi seluruh halaman.
     */
    public function bulkSelectAll(Request $request): \Illuminate\Http\JsonResponse
    {
        $ids = $this->filteredQuery($request)
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        return response()->json(['ids' => $ids]);
    }

    /**
     * Hapus massal dokumen arsip (soft delete).
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dokumen_arsip,id'],
        ]);

        $ids = array_map('intval', $request->input('ids'));

        // Staff hanya boleh menghapus dokumen dari seksi yang dikelolanya; admin bebas.
        // Gunakan filteredQuery agar scope sesuai, lalu limit ke ID yang diminta.
        $query = $this->filteredQuery($request)
            ->whereIn('id', $ids);

        $affected = $query->delete();

        return redirect()
            ->route('arsip.index')
            ->with('danger', "{$affected} dokumen arsip berhasil dihapus.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess(request(), $arsip);

        $arsip->delete();

        return redirect()
            ->route('arsip.index')
            ->with('danger', 'Dokumen arsip berhasil dihapus.');
    }

    /**
     * Hapus permanen (hard delete) beserta relasi dan file QR-nya.
     */
    public function forceDelete(DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess(request(), $arsip);

        $arsip->forceDelete();

        return redirect()
            ->route('arsip.index')
            ->with('danger', 'Dokumen arsip berhasil dihapus permanen dari database.');
    }

    /**
     * Catat peminjaman dokumen arsip.
     */
    public function pinjam(Request $request, DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'pihak_terkait' => ['required', 'string', 'max:150'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($arsip->status_arsip !== 'tersedia') {
            return back()->withErrors([
                'sirkulasi' => 'Dokumen tidak tersedia untuk dipinjam.',
            ]);
        }

        DB::transaction(function () use ($arsip, $request, $validated) {
            $arsip->update([
                'status_arsip' => 'dipinjam',
                'batas_pengembalian' => now()->addDays(7),
            ]);

            $this->catatSirkulasi(
                $arsip,
                $request->user()->id,
                'dipinjam',
                $validated['pihak_terkait'],
                $validated['keterangan'] ?? null,
            );
        });

        return back()->with('success', 'Peminjaman berhasil dicatat.');
    }

    /**
     * Catat pengembalian dokumen arsip.
     */
    public function kembalikan(Request $request, DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($arsip->status_arsip !== 'dipinjam') {
            return back()->withErrors([
                'sirkulasi' => 'Dokumen tidak sedang dipinjam.',
            ]);
        }

        DB::transaction(function () use ($arsip, $request, $validated) {
            $arsip->update([
                'status_arsip' => 'tersedia',
                'batas_pengembalian' => null,
            ]);

            $this->catatSirkulasi(
                $arsip,
                $request->user()->id,
                'dikembalikan',
                null,
                $validated['keterangan'] ?? 'Dokumen dikembalikan ke rak.',
            );
        });

        return back()->with('success', 'Pengembalian berhasil dicatat.');
    }

    /**
     * Tandai dokumen berstatus 'hilang' kembali menjadi 'tersedia'.
     */
    public function settersedia(Request $request, DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($arsip->status_arsip !== 'hilang') {
            return back()->withErrors([
                'sirkulasi' => 'Dokumen tidak berstatus hilang.',
            ]);
        }

        DB::transaction(function () use ($arsip, $request, $validated) {
            $arsip->update([
                'status_arsip' => 'tersedia',
                'batas_pengembalian' => null,
            ]);

            $this->catatSirkulasi(
                $arsip,
                $request->user()->id,
                'ditemukan',
                null,
                $validated['keterangan'] ?? 'Dokumen ditemukan dan ditandai tersedia kembali.',
            );
        });

        return back()->with('success', 'Dokumen berhasil ditandai tersedia.');
    }

    /**
     * Tandai dokumen sebagai 'hilang' (dari status tersedia/dipinjam).
     */
    public function tandahilang(Request $request, DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($arsip->status_arsip === 'hilang') {
            return back()->withErrors([
                'sirkulasi' => 'Dokumen sudah berstatus hilang.',
            ]);
        }

        DB::transaction(function () use ($arsip, $request, $validated) {
            $arsip->update([
                'status_arsip' => 'hilang',
                'batas_pengembalian' => null,
            ]);

            $this->catatSirkulasi(
                $arsip,
                $request->user()->id,
                'dihilangkan',
                null,
                $validated['keterangan'] ?? 'Dokumen ditandai hilang.',
            );
        });

        return back()->with('success', 'Dokumen berhasil ditandai hilang.');
    }

    /**
     * Cari arsip berdasarkan kode hasil pindaian QR.
     * POST-only: GET + side-effect (tulis RiwayatSirkulasi) non-idempoten
     * dan bisa di-replay cache browser → guard wajib.
     */
    public function scanByKode(Request $request): JsonResponse
    {
        $kode_arsip = $request->post('kode_arsip');

        if (blank($kode_arsip)) {
            return response()->json(
                ['message' => 'Kode arsip wajib diisi'],
                422,
            );
        }

        $arsip = DokumenArsip::with('seksi')
            ->where('kode_arsip', $kode_arsip)
            ->first();

        if (! $arsip) {
            return response()->json(
                ['message' => 'Kode arsip tidak ditemukan'],
                404,
            );
        }

        // Staff hanya boleh memindai dokumen dari seksi yang dikelolanya.
        $user = $request->user();
        if ($user->role !== 'admin' && (int) $arsip->seksi_id !== (int) $user->seksi_id) {
            return response()->json(
                ['message' => 'QR ini tidak termasuk seksi yang Anda kelola.'],
                403,
            );
        }

        // Guard: jangan tulis record 'dipindai' ganda dalam 30 detik
        // untuk user + dokumen yang sama (kamera bisa re-scan cepat).
        $alreadyLogged = RiwayatSirkulasi::where('dokumen_arsip_id', $arsip->id)
            ->where('user_id', $request->user()->id)
            ->where('jenis_aksi', 'dipindai')
            ->where('waktu_aksi', '>=', now()->subSeconds(30))
            ->exists();

        if (! $alreadyLogged) {
            RiwayatSirkulasi::create([
                'dokumen_arsip_id' => $arsip->id,
                'user_id' => $request->user()->id,
                'jenis_aksi' => 'dipindai',
                'pihak_terkait' => null,
                'keterangan' => 'Dokumen dipindai via QR',
                'waktu_aksi' => now(),
            ]);
        }

        return response()->json($arsip);
    }

    /**
     * Proteksi akses untuk staff: dokumen harus milik seksi yang dikelola user.
     * Admin tidak di-restrict. Dipanggil di setiap method yang menerima
     * DokumenArsip via route-model-binding, sebelum operasi apa pun.
     */
    protected function assertDocumentAccess(Request $request, DokumenArsip $arsip): void
    {
        $user = $request->user();

        if ($user->role === 'admin') {
            return;
        }

        if ((int) $arsip->seksi_id !== (int) $user->seksi_id) {
            abort(403, 'Anda hanya dapat mengakses dokumen pada seksi yang Anda kelola.');
        }
    }

    protected function filteredQuery(Request $request): Builder
    {
        $query = DokumenArsip::query()->with(['seksi', 'rak', 'boks.rak']);

        $user = $request->user();

        // Staff: auto-scope ke seksi yang dikelolanya, apapun parameter request.
        if ($user->role !== 'admin') {
            $query->where('seksi_id', $user->seksi_id);
        }

        if ($request->filled('seksi_id')) {
            $query->where('seksi_id', $request->integer('seksi_id'));
        }

        if ($request->filled('status') && $request->status !== 'semua') {
            $query->where('status_arsip', $request->string('status'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();

            $query->where(function ($builder) use ($search) {
                $builder->where('kode_arsip', 'like', "%{$search}%")
                    ->orWhere('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('perihal', 'like', "%{$search}%")
                    ->orWhere('asal_instansi', 'like', "%{$search}%")
                    ->orWhere('tujuan_instansi', 'like', "%{$search}%")
                    ->orWhere('catatan', 'like', "%{$search}%");
            });
        }

        if ($request->filled('tanggal_dari')) {
            $query->whereDate('tanggal_surat', '>=', $request->date('tanggal_dari'));
        }

        if ($request->filled('tanggal_sampai')) {
            $query->whereDate('tanggal_surat', '<=', $request->date('tanggal_sampai'));
        }

        if ($request->boolean('tanpa_lokasi')) {
            $query->whereNull('rak_id');
        }

        if ($request->boolean('terlambat')) {
            $query->where('status_arsip', 'dipinjam')
                ->whereNotNull('batas_pengembalian')
                ->where('batas_pengembalian', '<', now());
        }

        if ($request->boolean('tanpa_file')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('file_path')->orWhere('file_path', '');
            });
        }

        if ($request->boolean('metadata_kosong')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('nomor_surat')
                    ->orWhere('nomor_surat', '')
                    ->orWhereNull('tanggal_surat')
                    ->orWhereNull('seksi_id')
                    ->orWhereNull('perihal')
                    ->orWhere('perihal', '');
            });
        }

        if ($request->boolean('tanpa_qr')) {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('qr_code_path')->orWhere('qr_code_path', '');
            });
        }

        $sort = $request->string('sort', 'terbaru')->toString();
        match ($sort) {
            'terlama' => $query->oldest('tanggal_surat')->oldest('id'),
            'kode' => $query->orderBy('kode_arsip'),
            'batas' => $query->orderByRaw('batas_pengembalian IS NULL')->orderBy('batas_pengembalian')->orderBy('id'),
            default => $query->latest('tanggal_surat')->latest('id'),
        };

        return $query;
    }

    protected function rakList()
    {
        return RakArsip::query()
            ->where('is_active', true)
            ->orderBy('kode_rak')
            ->get(['id', 'seksi_id', 'kode_rak', 'nama_rak', 'nomor_rak']);
    }

    protected function boksList()
    {
        return BoksArsip::query()
            ->with('rak:id,kode_rak,nama_rak')
            ->where('is_active', true)
            ->withCount('dokumenArsip')
            ->orderBy('kode_boks')
            ->get(['id', 'rak_id', 'kode_boks', 'nomor_baris', 'nomor_boks', 'kapasitas']);
    }

    protected function catatSirkulasi(
        DokumenArsip $arsip,
        int $userId,
        string $jenisAksi,
        ?string $pihakTerkait = null,
        ?string $keterangan = null,
    ): void {
        RiwayatSirkulasi::create([
            'dokumen_arsip_id' => $arsip->id,
            'user_id' => $userId,
            'jenis_aksi' => $jenisAksi,
            'pihak_terkait' => $pihakTerkait,
            'keterangan' => $keterangan,
            'waktu_aksi' => now(),
        ]);
    }
}
