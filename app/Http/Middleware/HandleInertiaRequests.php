<?php

namespace App\Http\Middleware;

use App\Models\DokumenArsip;
use App\Models\Seksi;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'danger'  => fn () => $request->session()->get('danger'),
                'welcome' => fn () => $request->session()->get('welcome'),
                // Ringkasan hasil pemulihan (metrik) dikirim terpisah agar bisa
                // dirender sebagai panel persisten, bukan flash yang auto-hide.
                'backup_result' => fn () => $request->session()->get('backup_result'),
            ],
            'seksiList' => fn () => $request->user()
                ? Seksi::query()->select('id', 'nama_seksi')->orderByOrganisasi()->get()
                : [],
            // Seksi yang dikelola user (staff); null untuk admin.
            'userSeksi' => fn () => $request->user()
                ? ($request->user()->role !== 'admin'
                    ? Seksi::whereKey($request->user()->seksi_id)->first(['id', 'nama_seksi'])
                    : null)
                : null,
            // Count dokumen: admin lihat global, staff hanya dokumen seksi miliknya.
            'seksiCounts' => function () use ($request) {
                if (! $request->user()) {
                    return ['all' => 0, 'by_seksi' => []];
                }

                $user = $request->user();

                if ($user->role === 'admin') {
                    return [
                        'all' => DokumenArsip::count(),
                        'by_seksi' => Seksi::query()
                            ->withCount('dokumenArsip')
                            ->pluck('dokumen_arsip_count', 'id'),
                    ];
                }

                return [
                    'all' => DokumenArsip::where('seksi_id', $user->seksi_id)->count(),
                    'by_seksi' => [
                        $user->seksi_id => DokumenArsip::where('seksi_id', $user->seksi_id)->count(),
                    ],
                ];
            },
        ];
    }
}
