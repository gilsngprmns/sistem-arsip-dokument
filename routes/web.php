<?php

use App\Http\Controllers\ArsipController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\HalamanPublikController;
use App\Http\Controllers\BoksScanController;
use App\Http\Controllers\BoksArsipController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RakArsipController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StafOnlineController;
use App\Http\Controllers\UserManagementController;
use App\Models\DokumenArsip;
use App\Models\AlurKerja;
use App\Models\BerandaSlide;
use App\Models\Faq;
use App\Models\Manfaat;
use App\Models\ActivityLog;
use App\Models\RakArsip;
use App\Models\BoksArsip;
use App\Models\RiwayatSirkulasi;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'berandaSlides' => BerandaSlide::query()->orderBy('sort')->get(['id', 'path', 'sort']),
    ]);
})->name('home');

Route::get('/fitur', function () {
    return Inertia::render('Public/Fitur', [
        'canLogin' => Route::has('login'),
        'manfaat' => Manfaat::query()->orderBy('sort')->get(['id', 'judul', 'deskripsi', 'ikon']),
    ]);
})->name('fitur');

Route::get('/alur-kerja', function () {
    return Inertia::render('Public/AlurKerja', [
        'canLogin' => Route::has('login'),
        'alurKerja' => AlurKerja::query()->orderBy('sort')->get(['id', 'nomor', 'judul', 'deskripsi', 'hasil']),
    ]);
})->name('alur-kerja');

Route::get('/bantuan', function () {
    return Inertia::render('Public/Bantuan', [
        'canLogin' => Route::has('login'),
        'faq' => Faq::query()->orderBy('sort')->get(['id', 'pertanyaan', 'jawaban']),
    ]);
})->name('bantuan');

Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    $isAdmin = $user->role === 'admin';
    $documentQuery = DokumenArsip::query();

    if (! $isAdmin) {
        $documentQuery->where('seksi_id', $user->seksi_id);
    }

    $stats = [
        'total_dokumen' => (clone $documentQuery)->count(),
        'dokumen_tersedia' => (clone $documentQuery)->where('status_arsip', 'tersedia')->count(),
        'dokumen_dipinjam' => (clone $documentQuery)->where('status_arsip', 'dipinjam')->count(),
        'dokumen_hilang' => (clone $documentQuery)->where('status_arsip', 'hilang')->count(),
        'total_user' => $isAdmin ? User::count() : User::where('seksi_id', $user->seksi_id)->count(),
        'total_boks' => BoksArsip::count(),
        'total_rak' => RakArsip::count(),
        'per_seksi' => ($isAdmin ? Seksi::query() : Seksi::query()->whereKey($user->seksi_id))
            ->withCount(['dokumenArsip', 'users'])
            ->orderByOrganisasi()
            ->get()
            ->map(fn ($seksi) => [
                'id' => $seksi->id,
                'nama_seksi' => $seksi->nama_seksi,
                'jumlah_arsip' => $seksi->dokumen_arsip_count,
                'jumlah_user' => $seksi->users_count,
            ])
            ->toArray(),
        'scope' => $isAdmin ? 'Seluruh unit kerja' : ($user->seksi?->nama_seksi ?? 'Seksi belum ditentukan'),
    ];

    $trendGranularity = in_array($request->input('trend_granularity', 'month'), ['day', 'month', 'year'], true)
        ? $request->input('trend_granularity', 'month')
        : 'month';
    $trendDefaults = ['day' => 7, 'month' => 6, 'year' => 3];
    $trendMax = ['day' => 31, 'month' => 12, 'year' => 10];
    $trendPeriod = (int) $request->input('trend_period', 0);
    if ($trendPeriod < 1 || $trendPeriod > $trendMax[$trendGranularity]) {
        $trendPeriod = $trendDefaults[$trendGranularity];
    }

    $trendStart = match ($trendGranularity) {
        'day'  => now()->subDays($trendPeriod - 1)->startOfDay(),
        'year' => now()->subYears($trendPeriod - 1)->startOfYear(),
        default => now()->subMonths($trendPeriod - 1)->startOfMonth(),
    };
    $trendRows = RiwayatSirkulasi::query()
        ->whereIn('jenis_aksi', ['dipinjam', 'dikembalikan'])
        ->where('waktu_aksi', '>=', $trendStart)
        ->when(
            ! $isAdmin,
            fn ($query) => $query->whereHas(
                'dokumenArsip',
                fn ($document) => $document->where('seksi_id', $user->seksi_id),
            ),
        )
        ->get(['waktu_aksi', 'jenis_aksi']);

    $trendKeyFormat = ['day' => 'Y-m-d', 'month' => 'Y-m', 'year' => 'Y'][$trendGranularity];
    $trends = collect(range($trendPeriod - 1, 0, -1))->map(function (int $offset) use ($trendRows, $trendGranularity, $trendKeyFormat, $trendPeriod) {
        $unit = match ($trendGranularity) {
            'day'  => now()->subDays($offset),
            'year' => now()->subYears($offset),
            default => now()->subMonths($offset),
        };
        $key = $unit->format($trendKeyFormat);
        $rows = $trendRows->filter(fn ($row) => optional($row->waktu_aksi)->format($trendKeyFormat) === $key);
        $label = match ($trendGranularity) {
            'day'  => $unit->format('d M'),
            'year' => $unit->format('Y'),
            default => $unit->translatedFormat('M'),
        };

        return [
            'label' => $label,
            'pinjam' => $rows->where('jenis_aksi', 'dipinjam')->count(),
            'kembali' => $rows->where('jenis_aksi', 'dikembalikan')->count(),
        ];
    })->values();

    $notifications = [
        'tanpa_lokasi' => (clone $documentQuery)->whereNull('rak_id')->count(),
        'terlambat' => (clone $documentQuery)->where('status_arsip', 'dipinjam')->whereNotNull('batas_pengembalian')->where('batas_pengembalian', '<', now())->count(),
    ];

    if ($isAdmin) {
        $fullBoks = BoksArsip::query()
            ->where('is_active', true)
            ->withCount('dokumenArsip')
            ->get()
            ->filter(fn ($boks) => ($boks->kapasitas ?? 50) > 0 && $boks->dokumen_arsip_count >= (($boks->kapasitas ?? 50) * 0.8))
            ->count();

        $notifications['boks_hampir_penuh'] = $fullBoks;
        $notifications['aktivitas_penting'] = ActivityLog::query()
            ->whereIn('route_name', ['users.store', 'users.update', 'users.destroy', 'users.force-delete', 'backup.import', 'backup.destroy-bulk'])
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    $adminOverview = null;
    if ($isAdmin) {
        $adminOverview = [
            'user_aktif' => User::where('is_active', true)->count(),
            'user_nonaktif' => User::where('is_active', false)->count(),
            'total_seksi' => Seksi::count(),
            'dokumen_tanpa_lokasi' => DokumenArsip::whereNull('rak_id')->count(),
            'aktivitas_hari_ini' => ActivityLog::where('created_at', '>=', now()->startOfDay())->count(),
            'boks_perhatian' => BoksArsip::query()
                ->where('is_active', true)
                ->with(['rak:id,kode_rak', 'dokumenArsip'])
                ->withCount('dokumenArsip')
                ->get()
                ->filter(function ($boks) { $c = $boks->kapasitas ?? 50; return $c > 0 && $boks->dokumen_arsip_count >= ($c * 0.8); })
                ->sortByDesc(function ($boks) { $c = $boks->kapasitas ?? 50; return $c > 0 ? $boks->dokumen_arsip_count / $c : 0; })
                ->map(function ($b) { $c = $b->kapasitas ?? 50; return tap($b, fn ($b) => $b->pct = round(($b->dokumen_arsip_count / $c) * 100)); })
                ->take(5)
                ->values(),
        ];
    }

    $recentDocuments = (clone $documentQuery)
        ->with(['seksi:id,nama_seksi', 'rak:id,kode_rak,nama_rak'])
        ->latest('created_at')
        ->limit(5)
        ->get(['id', 'kode_arsip', 'perihal', 'status_arsip', 'seksi_id', 'rak_id', 'created_at']);

    $recentActivities = ActivityLog::query()
        ->with('user:id,name,role')
        ->when(! $isAdmin, fn ($query) => $query->where('user_id', $user->id))
        ->latest()
        ->limit(6)
        ->get(['id', 'user_id', 'action', 'description', 'created_at']);

    $activeLoans = (clone $documentQuery)
        ->where('status_arsip', 'dipinjam')
        ->with([
            'seksi:id,nama_seksi',
            'riwayatSirkulasi' => fn ($query) => $query
                ->where('jenis_aksi', 'dipinjam')
                ->latest('waktu_aksi')
                ->limit(1),
        ])
        ->orderByRaw('CASE WHEN batas_pengembalian IS NULL THEN 1 ELSE 0 END')
        ->orderBy('batas_pengembalian')
        ->limit(8)
        ->get(['id', 'kode_arsip', 'perihal', 'status_arsip', 'seksi_id', 'batas_pengembalian'])
        ->map(function (DokumenArsip $document) {
            $deadline = $document->batas_pengembalian;
            $isOverdue = $deadline !== null && $deadline->isPast();
            $daysRemaining = null;

            if ($deadline !== null) {
                $today = now()->startOfDay();
                $due = $deadline->copy()->startOfDay();
                $daysRemaining = (int) $today->diffInDays($due, false);
            }

            return [
                'id' => $document->id,
                'kode_arsip' => $document->kode_arsip,
                'perihal' => $document->perihal,
                'status_arsip' => $document->status_arsip,
                'seksi' => $document->seksi,
                'batas_pengembalian' => $deadline?->toIso8601String(),
                'peminjam' => $document->riwayatSirkulasi->first()?->pihak_terkait,
                'terlambat' => $isOverdue,
                'sisa_hari' => $daysRemaining,
            ];
        })
        ->values();

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'recentDocuments' => $recentDocuments,
        'recentActivities' => $recentActivities,
        'trends' => $trends,
        'trendGranularity' => $trendGranularity,
        'trendPeriod' => $trendPeriod,
        'notifications' => $notifications,
        'adminOverview' => $adminOverview,
        'activeLoans' => $activeLoans,
        'dataHealth' => [
            'tanpa_lokasi' => (clone $documentQuery)->whereNull('rak_id')->count(),
            'tanpa_file' => (clone $documentQuery)->where(function ($query): void {
                $query->whereNull('file_path')->orWhere('file_path', '');
            })->count(),
            'metadata_kosong' => (clone $documentQuery)->where(function ($query): void {
                $query->whereNull('nomor_surat')
                    ->orWhere('nomor_surat', '')
                    ->orWhereNull('tanggal_surat')
                    ->orWhereNull('seksi_id')
                    ->orWhereNull('perihal')
                    ->orWhere('perihal', '');
            })->count(),
            'tanpa_qr' => (clone $documentQuery)->where(function ($query): void {
                $query->whereNull('qr_code_path')->orWhere('qr_code_path', '');
            })->count(),
        ],
    ]);
})->middleware(['auth', 'active', 'verified'])->name('dashboard');

// Halaman hasil scan QR boks — publik (tanpa auth) agar petugas HP bisa
// akses langsung dari kamera tanpa login. Data ditampilkan = lokasi + dokumen.
Route::get('/scan/boks/{kode_boks}', [BoksArsipController::class, 'showByKode'])
    ->name('scan.boks');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/scan', fn () => Inertia::render('Scan/Index'))->name('scan.index');
    Route::get('arsip/export', [ArsipController::class, 'exportExcel'])->name('arsip.export');
    Route::post('arsip/{arsip}/pinjam', [ArsipController::class, 'pinjam'])->name('arsip.pinjam');
    Route::post('arsip/{arsip}/kembalikan', [ArsipController::class, 'kembalikan'])->name('arsip.kembalikan');
    Route::post('arsip/{arsip}/settersedia', [ArsipController::class, 'settersedia'])->name('arsip.settersedia');
    Route::post('arsip/{arsip}/tandahilang', [ArsipController::class, 'tandahilang'])->name('arsip.tandahilang');
    Route::get('arsip/bulk-select-all', [ArsipController::class, 'bulkSelectAll'])
        ->name('arsip.bulk-select-all');
    Route::post('arsip/bulk-destroy', [ArsipController::class, 'bulkDestroy'])
        ->name('arsip.bulk-destroy');
    Route::resource('arsip', ArsipController::class);
    Route::post('/api/arsip/scan', [ArsipController::class, 'scanByKode'])
        ->name('arsip.scan');
    Route::post('/api/boks/scan/{kode_boks}', [BoksScanController::class, '__invoke'])
        ->name('boks.scan');

    Route::middleware('admin')->group(function () {
        // Daftar staf yang sedang online — dipanggil admin via polling ringan
        Route::get('/api/staf-online', [StafOnlineController::class, 'index'])
            ->middleware('throttle:120,1')
            ->name('activity.staf-online');

        // Log aktivitas terbaru — dipanggil halaman /activity-log via polling ringan
        Route::get('/api/activity-log/recent', [ActivityLogController::class, 'recent'])
            ->middleware('throttle:120,1')
            ->name('activity.log-recent');

        Route::post('arsip/{arsip}/force-delete', [ArsipController::class, 'forceDelete'])
            ->name('arsip.force-delete');
        Route::get('/backup', [BackupController::class, 'index'])->name('backup.index');
        Route::get('/backup/export', [BackupController::class, 'export'])->name('backup.export');
        Route::post('/backup/import', [BackupController::class, 'import'])->name('backup.import');
        Route::post('/backup/run', [BackupController::class, 'run'])->name('backup.run');
        Route::get('/backup/files/{file}', [BackupController::class, 'files'])->where(
            'file', 'backup-arsip-[0-9]{8}-[0-9]{6}\.(json|zip)'
        )->name('backup.files');
        Route::post('/backup/destroy-bulk', [BackupController::class, 'destroyBulk'])
            ->name('backup.destroy-bulk');

        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');
        Route::post('/users/{user}/password', [UserManagementController::class, 'resetPassword'])->name('users.password');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/force-delete', [UserManagementController::class, 'forceDelete'])->name('users.force-delete');
        Route::post('/users/destroy-bulk', [UserManagementController::class, 'destroyBulk'])->name('users.destroy-bulk');
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('/reports/activity', [ReportController::class, 'activityExcel'])->name('reports.activity');
        Route::get('/reports/users', [ReportController::class, 'usersExcel'])->name('reports.users');
        Route::get('/reports/racks', [ReportController::class, 'racksExcel'])->name('reports.racks');
        Route::get('/reports/sections', [ReportController::class, 'sectionsExcel'])->name('reports.sections');

        Route::get('/boks', [BoksArsipController::class, 'index'])->name('boks.index');
        Route::post('/boks', [BoksArsipController::class, 'store'])->name('boks.store');
        Route::put('/boks/{boks}', [BoksArsipController::class, 'update'])->name('boks.update');
        Route::delete('/boks/{boks}', [BoksArsipController::class, 'destroy'])->name('boks.destroy');
        Route::get('/boks/{boks}/qr', [BoksArsipController::class, 'qr'])->name('boks.qr');
        Route::post('/boks/destroy-bulk', [BoksArsipController::class, 'destroyBulk'])->name('boks.destroy-bulk');

        Route::get('/rak', [RakArsipController::class, 'index'])->name('rak.index');
        Route::post('/rak', [RakArsipController::class, 'store'])->name('rak.store');
        Route::put('/rak/{rak}', [RakArsipController::class, 'update'])->name('rak.update');
        Route::delete('/rak/{rak}', [RakArsipController::class, 'destroy'])->name('rak.destroy');
        Route::post('/rak/destroy-bulk', [RakArsipController::class, 'destroyBulk'])->name('rak.destroy-bulk');

        // Halaman Publik — kelola konten 4 halaman situs dari admin
        Route::get('/halaman-publik/beranda', [HalamanPublikController::class, 'beranda'])->name('halaman-publik.beranda');
        Route::get('/halaman-publik/manfaat', [HalamanPublikController::class, 'manfaat'])->name('halaman-publik.manfaat');
        Route::get('/halaman-publik/alur-kerja', [HalamanPublikController::class, 'alurKerja'])->name('halaman-publik.alur-kerja');
        Route::get('/halaman-publik/faq', [HalamanPublikController::class, 'faq'])->name('halaman-publik.faq');
        Route::post('/halaman-publik/beranda-slides', [HalamanPublikController::class, 'storeSlide'])->name('beranda.slides.store');
        Route::delete('/halaman-publik/beranda-slides/{slide}', [HalamanPublikController::class, 'destroySlide'])->name('beranda.slides.destroy');
        Route::post('/halaman-publik/manfaat', [HalamanPublikController::class, 'storeManfaat'])->name('manfaat.store');
        Route::put('/halaman-publik/manfaat/{manfaat}', [HalamanPublikController::class, 'updateManfaat'])->name('manfaat.update');
        Route::delete('/halaman-publik/manfaat/{manfaat}', [HalamanPublikController::class, 'destroyManfaat'])->name('manfaat.destroy');
        Route::post('/halaman-publik/alur-kerja', [HalamanPublikController::class, 'storeAlurKerja'])->name('alur_kerja.store');
        Route::put('/halaman-publik/alur-kerja/{alur_kerja}', [HalamanPublikController::class, 'updateAlurKerja'])->name('alur_kerja.update');
        Route::delete('/halaman-publik/alur-kerja/{alur_kerja}', [HalamanPublikController::class, 'destroyAlurKerja'])->name('alur_kerja.destroy');
        Route::post('/halaman-publik/faq', [HalamanPublikController::class, 'storeFaq'])->name('faq.store');
        Route::put('/halaman-publik/faq/{faq}', [HalamanPublikController::class, 'updateFaq'])->name('faq.update');
        Route::delete('/halaman-publik/faq/{faq}', [HalamanPublikController::class, 'destroyFaq'])->name('faq.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
