<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreArsipRequest;
use App\Http\Requests\UpdateArsipRequest;
use App\Models\DokumenArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\BoksArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Services\ArsipNumberGeneratorService;
use App\Services\PdfEncryptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        // Hitung berapa banyak dokumen (dalam scope user) yang perlu peringatan retensi
        $basisRetensi = DokumenArsip::query();
        if ($request->user()->role !== 'admin') {
            $basisRetensi->where('seksi_id', $request->user()->seksi_id);
        }
        $retensiHariIni = now()->startOfDay();
        $retensiAlert = $basisRetensi
            ->where('retensi_dipertahankan', false)
            ->whereNotNull('tanggal_kedaluwarsa')
            ->where(function ($q) use ($retensiHariIni) {
                $q->whereDate('tanggal_kedaluwarsa', '<', $retensiHariIni)
                    ->orWhere(function ($sub) use ($retensiHariIni) {
                        $sub->whereDate('tanggal_kedaluwarsa', '>=', $retensiHariIni)
                            ->whereDate('tanggal_kedaluwarsa', '<=', $retensiHariIni->addDays(DokumenArsip::RETENSI_PERINGATAN_HARI));
                    });
            })
            ->count();

        return Inertia::render('Arsip/Index', [
            'arsip' => $query->paginate(10)->withQueryString(),
            'retensiAlert' => $retensiAlert,
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
            'filters' => [
                'seksi_id' => $request->input('seksi_id'),
                'status' => $request->input('status', 'semua'),
                'jenis_dokumen' => $request->input('jenis_dokumen', 'semua'),
                'status_pengecekan' => $request->input('status_pengecekan', 'semua'),
                'status_retensi' => $request->input('status_retensi', 'semua'),
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
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi', 'kode_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreArsipRequest $request)
    {
        $data = $request->safe()->except(['lampiran', 'kode_arsip', 'encrypt_file']);

        $encryptionService = app(PdfEncryptionService::class);

        if ($request->hasFile('lampiran')) {
            $jenisDokumen = $data['jenis_dokumen'] ?? 'umum';
            $shouldEncrypt = in_array($jenisDokumen, DokumenArsip::JENIS_DOKUMEN_KHUSUS, true)
                || $request->boolean('encrypt_file');

            if ($shouldEncrypt) {
                $result = $encryptionService->encryptAndStore($request->file('lampiran'));
                $data['file_path']      = $result['path'];
                $data['is_encrypted']   = true;
                $data['file_hash']      = $result['hash'];
                $data['encrypted_at']   = $result['encrypted_at'];
            } else {
                $data['file_path'] = $request->file('lampiran')->store('lampiran-arsip', 'public');
            }
        }

        DB::transaction(function () use ($request, $data) {
            // Generate kode arsip otomatis (di luar lock, dalam transaction)
            $seksi = Seksi::findOrFail($data['seksi_id']);
            $data['kode_arsip'] = app(ArsipNumberGeneratorService::class)->nextFor($seksi);

            // Tahap 6: hitung ulang tanggal kedaluwarsa sesuai kategori retensi
            if (array_key_exists('retensi_kategori', $data)) {
                $arsip = DokumenArsip::make($data);
                $arsip->perbaruiTanggalKedaluwarsa();
                $data['tanggal_kedaluwarsa'] = $arsip->tanggal_kedaluwarsa;
                $data['retensi_tahun'] = $arsip->retensi_tahun;
            }

            $arsip = DokumenArsip::create([
                ...$data,
                'dicatat_oleh' => $request->user()->id,
            ]);

            $this->catatSirkulasi($arsip, $request->user()->id, 'dibuat', null, 'Dokumen arsip baru dicatat.');
        });

        if (request()->header('X-Inertia')) {
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
            'riwayatPengecekan' => fn ($query) => $query->with('user:id,name,role')->latest('created_at'),
        ]);

        return Inertia::render('Arsip/Show', [
            'arsip' => $arsip,
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi', 'kode_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
            'previousUrl' => request()->header('referer'),
        ]);
    }

    /**
     * Halaman cetak Lembar Arsip — dokumen khusus (surat_tanah / ahli_waris).
     */
    public function print(DokumenArsip $arsip): Response
    {
        $this->assertDocumentAccess(request(), $arsip);

        if (! $arsip->isDokumenKhusus()) {
            abort(404, 'Cetak Lembar Arsip hanya tersedia untuk dokumen Surat Tanah / Ahli Waris.');
        }

        $arsip->load([
            'seksi',
            'rak',
            'boks.rak',
            'riwayatPengecekan' => fn ($query) => $query->with('user:id,name')->latest('created_at')->limit(5),
        ]);

        return Inertia::render('Arsip/Print', [
            'arsip' => $arsip,
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
            'seksiList' => Seksi::orderByOrganisasi()->get(['id', 'nama_seksi', 'kode_seksi']),
            'rakList' => $this->rakList(),
            'boksList' => $this->boksList(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateArsipRequest $request, DokumenArsip $arsip)
    {
        $encryptionService = app(PdfEncryptionService::class);
        $data = $request->safe()->except(['lampiran', 'kode_arsip', 'encrypt_file']);

        if ($request->hasFile('lampiran')) {
            $encryptionService->deleteOldFile($arsip);

            $jenisDokumen = $data['jenis_dokumen'] ?? 'umum';
            $shouldEncrypt = in_array($jenisDokumen, DokumenArsip::JENIS_DOKUMEN_KHUSUS, true)
                || $request->boolean('encrypt_file');

            if ($shouldEncrypt) {
                $result = $encryptionService->encryptAndStore($request->file('lampiran'));
                $data['file_path']    = $result['path'];
                $data['is_encrypted'] = true;
                $data['file_hash']    = $result['hash'];
                $data['encrypted_at'] = $result['encrypted_at'];
            } else {
                $data['file_path']    = $request->file('lampiran')->store('lampiran-arsip', 'public');
                $data['is_encrypted'] = false;
                $data['file_hash']    = null;
                $data['encrypted_at'] = null;
            }
        }

        // Simpan seksi_id lama untuk mendeteksi pindah seksi
        $oldSeksiId = (int) $arsip->seksi_id;
        $newSeksiId = isset($data['seksi_id']) ? (int) $data['seksi_id'] : $oldSeksiId;

        // Tahap 6: selalu hitung ulang tanggal kedaluwarsa jika dokumen punya retensi kategori,
        // atau jika tanggal_surat / retensi_kategori / retensi_tahun ikut terubah di form ini.
        $retensiNeedRecalc = (
            !empty($arsip->retensi_kategori)
            || array_key_exists('retensi_kategori', $data)
            || array_key_exists('retensi_tahun', $data)
            || array_key_exists('tanggal_surat', $data)
        );

        if ($retensiNeedRecalc) {
            $arsip->retensi_kategori = $data['retensi_kategori'] ?? $arsip->retensi_kategori;
            $arsip->retensi_tahun = $data['retensi_tahun'] ?? $arsip->retensi_tahun;
            $arsip->perbaruiTanggalKedaluwarsa(
                isset($data['tanggal_surat']) ? \Illuminate\Support\Carbon::parse($data['tanggal_surat']) : null
            );
            $data['tanggal_kedaluwarsa'] = $arsip->tanggal_kedaluwarsa;
            if (!empty($arsip->retensi_tahun)) {
                $data['retensi_tahun'] = $arsip->retensi_tahun;
            }
        }

        DB::transaction(function () use ($arsip, $data, $oldSeksiId, $newSeksiId) {
            $arsip->update($data);

            // Pindah seksi → generate nomor baru di seksi tujuan
            if ($newSeksiId !== $oldSeksiId) {
                $newSeksi = Seksi::findOrFail($newSeksiId);
                $arsip->update([
                    'kode_arsip' => app(ArsipNumberGeneratorService::class)->nextFor($newSeksi),
                ]);
            }
        });

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
     * Hapus massal. Staff = soft delete; Admin = hard delete permanen.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dokumen_arsip,id'],
        ]);

        $ids = array_map('intval', $request->input('ids'));
        $isAdmin = $request->user()->role === 'admin';

        // Staff hanya boleh menghapus dokumen dari seksi yang dikelolanya; admin bebas.
        $query = $this->filteredQuery($request)->whereIn('id', $ids);

        if ($isAdmin) {
            $encryptionService = app(PdfEncryptionService::class);
            $affected = 0;
            foreach ($query->get() as $doc) {
                $encryptionService->deleteEncrypted($doc);
                $doc->forceDelete();
                $affected++;
            }
        } else {
            $affected = $query->delete();
        }

        $label = $isAdmin ? 'dihapus permanen dari database' : 'berhasil dihapus';
        return redirect()
            ->route('arsip.index')
            ->with('danger', "{$affected} dokumen arsip {$label}.");
    }

    /**
     * Tahap 6 — catat tindakan retensi pada dokumen yang statusnya
     * akan kedaluwarsa / kedaluwarsa. Alurnya terkontrol: hanya lewat
     * endpoint ini, tidak bisa diubah dari form store/update biasa.
     */
    public function retensiTindakan(Request $request, DokumenArsip $arsip): \Illuminate\Http\RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'tindakan' => ['required', Rule::in(['pertahankan'])],
            'catatan_retensi' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = $arsip->statusRetensi();

        if (! in_array($status, [
            DokumenArsip::RETENSI_STATUS_AKAN_KEDALUWARSA,
            DokumenArsip::RETENSI_STATUS_KEDALUWARSA,
        ], true)) {
            return back()->with('danger', 'Dokumen belum memerlukan tindakan retensi.');
        }

        DB::transaction(function () use ($arsip, $request, $status, $validated) {
            $arsip->update([
                'retensi_dipertahankan' => true,
                'retensi_ditindak_waktu' => now(),
                'retensi_ditindak_oleh' => $request->user()->id,
                'catatan_retensi' => $validated['catatan_retensi'] ?? null,
            ]);
        });

        $arsip->load('retensiPelaksana:id,name,role');

        $seksi = $arsip->seksi?->nama_seksi ?? 'tanpa seksi';
        $pelaksana = $request->user()->name;

        return back()->with('success', sprintf(
            'Masa aktif dokumen %s di Seksi %s berhasil dipertahankan oleh %s.',
            $arsip->kode_arsip,
            $seksi,
            $pelaksana
        ));
    }

    /**
     * Simpan catatan pengecekan + update status_pengecekan dokumen.
     */
    public function pengecekan(Request $request, DokumenArsip $arsip): \Illuminate\Http\RedirectResponse
    {
        $this->assertDocumentAccess($request, $arsip);

        $validated = $request->validate([
            'status_pengecekan' => ['required', Rule::in(DokumenArsip::STATUS_PENGECEKAN)],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);

        $statusSebelum = $arsip->status_pengecekan ?? 'belum_dicek';

        DB::transaction(function () use ($arsip, $request, $statusSebelum, $validated) {
            $arsip->update([
                'status_pengecekan' => $validated['status_pengecekan'],
            ]);

            \App\Models\RiwayatPengecekan::create([
                'dokumen_arsip_id' => $arsip->id,
                'user_id' => $request->user()->id,
                'status_sebelum' => $statusSebelum,
                'status_sesudah' => $validated['status_pengecekan'],
                'catatan' => $validated['catatan'] ?? null,
            ]);
        });

        return back()->with('success', 'Pengecekan berhasil dicatat.');
    }

    /**
     * Hapus satu dokumen. Staff = soft delete; Admin = hard delete permanen.
     */
    public function destroy(DokumenArsip $arsip): RedirectResponse
    {
        $this->assertDocumentAccess(request(), $arsip);

        if (request()->user()->role === 'admin') {
            app(PdfEncryptionService::class)->deleteEncrypted($arsip);
            $arsip->forceDelete();
            return redirect()
                ->route('arsip.index')
                ->with('danger', 'Dokumen arsip telah dihapus permanen dari database.');
        }

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

        app(PdfEncryptionService::class)->deleteEncrypted($arsip);
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
     * Preview nomor arsip berikutnya per seksi (endpoint ringan tanpa mutation).
     * Staf hanya boleh melihat nomor untuk seksi yang dikelolanya.
     */
    public function nextNumber(Request $request): JsonResponse
    {
        $user = $request->user();
        $seksiId = $request->integer('seksi_id', 0);

        if ($user->role !== 'admin' && $seksiId !== (int) $user->seksi_id) {
            return response()->json([
                'message' => 'Anda hanya dapat melihat nomor untuk seksi yang Anda kelola.',
            ], 403);
        }

        $seksi = $seksiId > 0 ? Seksi::find($seksiId) : null;
        $seksi ??= ($user->role === 'admin' ? null : Seksi::find($user->seksi_id));

        if ($seksi === null) {
            return response()->json(['message' => 'Seksi tidak ditemukan.'], 404);
        }

        $nextNumber = app(ArsipNumberGeneratorService::class)->nextFor($seksi);

        return response()->json([
            'seksi_id' => $seksi->id,
            'kode_arsip' => $nextNumber,
        ]);
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

        // Tahap 4 — filter klasifikasi dokumen
        if ($request->filled('jenis_dokumen') && $request->input('jenis_dokumen') !== 'semua') {
            $query->where('jenis_dokumen', $request->string('jenis_dokumen'));
        }

        // Tahap 4 — filter status pengecekan
        if ($request->filled('status_pengecekan') && $request->input('status_pengecekan') !== 'semua') {
            $query->where('status_pengecekan', $request->string('status_pengecekan'));
        }

        // Tahap 6 — filter status retensi
        if ($request->filled('status_retensi') && $request->input('status_retensi') !== 'semua') {
            $query->retensiStatus($request->string('status_retensi'));
        }

        if ($request->filled('q')) {
            $search = $request->string('q')->toString();

            $query->where(function ($builder) use ($search) {
                $builder->where('kode_arsip', 'like', "%{$search}%")
                    ->orWhere('nomor_surat', 'like', "%{$search}%")
                    ->orWhere('perihal', 'like', "%{$search}%")
                    ->orWhere('asal_instansi', 'like', "%{$search}%")
                    ->orWhere('tujuan_instansi', 'like', "%{$search}%")
                    ->orWhere('catatan', 'like', "%{$search}%")
                    // Tahap 4 — cari di kolom profiling
                    ->orWhere('nama_pemilik', 'like', "%{$search}%")
                    ->orWhere('nik_pemilik', 'like', "%{$search}%")
                    ->orWhere('nomor_alas_hak', 'like', "%{$search}%")
                    ->orWhere('alamat_objek', 'like', "%{$search}%")
                    ->orWhere('keterangan_profiling', 'like', "%{$search}%");
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

    /**
     * Endpoint terkontrol untuk mengakses file lampiran.
     * - File terenkripsi: di-dekripsi on-the-fly, dicatat ke ActivityLog.
     * - File publik (non-encrypted): langsung di-redirect ke /storage/.
     */
    public function downloadFile(Request $request, DokumenArsip $arsip): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertDocumentAccess($request, $arsip);

        if (blank($arsip->file_path)) {
            abort(404, 'File lampiran tidak tersedia untuk dokumen ini.');
        }

        $download = $request->boolean('download');

        if (! $arsip->is_encrypted) {
            // File publik — arahkan langsung ke storage publik.
            // Pakai Storage::disk('public')->path() agar Storage::fake() di test tetap jalan.
            $realPath = Storage::disk('public')->path($arsip->file_path);
            if (! is_file($realPath)) {
                abort(404, 'File lampiran tidak ditemukan di storage.');
            }

            return response()->file(
                $realPath,
                [
                    'Content-Type'   => $this->guessMimeType($arsip->file_path),
                    'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.basename($arsip->file_path).'"',
                ]
            );
        }

        // File terenkripsi — dekripsi + validasi integritas
        $decryptedBytes = app(PdfEncryptionService::class)->decryptContent($arsip);

        // Catat akses ke ActivityLog secara eksplisit
        \App\Models\ActivityLog::create([
            'user_id'     => $request->user()->id,
            'action'      => 'arsip.file.access',
            'route_name'  => 'arsip.file',
            'method'      => 'GET',
            'description' => sprintf(
                '%s akses file terenkripsi dokumen %s (%s).',
                $request->user()->name,
                $arsip->kode_arsip,
                $download ? 'unduh' : 'pratinjau'
            ),
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'metadata'    => [
                'kode_arsip'   => $arsip->kode_arsip,
                'download'     => $download,
                'is_encrypted' => true,
            ],
            'old_values'  => null,
            'new_values'  => null,
            'target_type' => 'dokumen',
            'target_id'   => $arsip->id,
            'target_label'=> $arsip->kode_arsip,
        ]);

        $filename = basename(str_replace('.enc', '.pdf', $arsip->file_path));

        return response($decryptedBytes, 200, [
            'Content-Type'            => 'application/pdf',
            'Content-Disposition'     => ($download ? 'attachment' : 'inline').'; filename="'.$filename.'"',
            'Cache-Control'           => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'                  => 'no-cache',
            'X-Content-Type-Options'  => 'nosniff',
        ]);
    }

    protected function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf'  => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            default => 'application/octet-stream',
        };
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
