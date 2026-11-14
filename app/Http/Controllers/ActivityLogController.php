<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'tanggal_dari' => trim((string) $request->query('tanggal_dari', '')),
            'tanggal_sampai' => trim((string) $request->query('tanggal_sampai', '')),
            'pengguna' => trim((string) $request->query('pengguna', '')),
            'aksi' => trim((string) $request->query('aksi', '')),
            'q' => trim((string) $request->query('q', '')),
        ];

        return Inertia::render('Activity/Index', [
            'activities' => ActivityLog::query()
                ->with('user:id,name,email,role')
                ->whereNotIn('route_name', [
                    'password.request',
                    'password.otp.reset',
                    'password.confirm',
                    'verification.send',
                ])
                ->filterByRequest($request)
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => ActivityLog::query()
                ->select('action')
                ->whereNotNull('action')
                ->where('action', '!=', '')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }

    /**
     * Endpoint ringan untuk polling log terbaru (tanpa pagination).
     * Dipanggil frontend admin dengan polling tiap ~10 detik
     * agar halaman /activity-log memperbarui otomatis tanpa refresh.
     */
    public function recent(Request $request): JsonResponse
    {
        $limit   = min(max((int) $request->query('limit', 5), 1), 20);
        $sinceId = $request->query('since_id') !== null && $request->query('since_id') !== ''
            ? (int) $request->query('since_id')
            : null;

        $cacheKey = 'activity-log-recent-'.($sinceId !== null ? $sinceId : 'all');
        $entries = Cache::remember($cacheKey, 5, function () use ($limit, $sinceId) {
            $query = ActivityLog::query()
                ->with('user:id,name,email,role')
                ->whereNotIn('route_name', [
                    'password.request',
                    'password.otp.reset',
                    'password.confirm',
                    'verification.send',
                ]);

            if ($sinceId !== null) {
                $query->where('id', '>', $sinceId);
            }

            return $query->latest()->limit($limit)->get([
                    'id',
                    'user_id',
                    'action',
                    'route_name',
                    'description',
                    'target_type',
                    'target_id',
                    'target_label',
                    'ip_address',
                    'created_at',
                ])
                ->map(function ($log) {
                    return [
                        'id'            => $log->id,
                        'user_name'     => $log->user?->name ?: 'Sistem',
                        'user_role'     => $log->user?->role,
                        'user_email'    => $log->user?->email,
                        'action'        => $log->action,
                        'route_name'    => $log->route_name,
                        'description'   => $log->description,
                        'target_type'   => $log->target_type,
                        'target_id'     => $log->target_id,
                        'target_label'  => $log->target_label,
                        'ip_address'    => $log->ip_address,
                        'created_at'    => $log->created_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        });

        $lastId = $entries ? (int) $entries[0]['id'] : ($sinceId ?? 0);

        return response()->json([
            'entries' => $entries,
            'last_id' => $lastId,
        ]);
    }
}
