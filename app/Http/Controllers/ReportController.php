<?php

namespace App\Http\Controllers;

use App\Exports\ActivityLogExport;
use App\Exports\RackExport;
use App\Exports\SectionSummaryExport;
use App\Exports\UserExport;
use App\Models\ActivityLog;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    /**
     * Ekspor log aktivitas ke Excel — mengikuti filter query string yang sama
     * dengan halaman /activity-log (tanpa filter = seluruh log).
     */
    public function activityExcel(Request $request)
    {
        $activities = ActivityLog::query()->with('user:id,name')
            ->whereNotIn('route_name', ['password.request', 'password.otp.send', 'password.otp.verify', 'password.otp.reset', 'password.confirm', 'verification.send'])
            ->filterByRequest($request)
            ->latest()
            ->get();

        return Excel::download(
            new ActivityLogExport($activities),
            'laporan-aktivitas-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function usersExcel()
    {
        $users = User::query()->with('seksi:id,nama_seksi')->orderBy('name')->get();

        return Excel::download(
            new UserExport($users),
            'laporan-user-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function racksExcel()
    {
        $racks = RakArsip::query()
            ->withCount('dokumenArsip')
            ->orderBy('kode_rak')
            ->get();

        return Excel::download(
            new RackExport($racks),
            'laporan-rak-'.now()->format('Ymd-His').'.xlsx',
        );
    }

    public function sectionsExcel()
    {
        $sections = Seksi::query()
            ->withCount(['dokumenArsip', 'users'])
            ->withCount(['dokumenArsip as tersedia_count' => fn ($query) => $query->where('status_arsip', 'tersedia')])
            ->withCount(['dokumenArsip as dipinjam_count' => fn ($query) => $query->where('status_arsip', 'dipinjam')])
            ->orderByOrganisasi()
            ->get();

        return Excel::download(
            new SectionSummaryExport($sections),
            'laporan-rekap-seksi-'.now()->format('Ymd-His').'.xlsx',
        );
    }
}
