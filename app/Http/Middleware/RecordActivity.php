<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\AlurKerja;
use App\Models\BerandaSlide;
use App\Models\DokumenArsip;
use App\Models\Faq;
use App\Models\Manfaat;
use App\Models\BoksArsip;
use App\Models\RakArsip;
use App\Models\Seksi;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class RecordActivity
{
    private ?array $requestInput = null;
    private ?string $routeName = null;

    public function handle(Request $request, Closure $next): Response
    {
        // Snapshot session before $next() untuk deteksi perubahan (gagal)
        $errorsBefore = $request->session()->get('errors');
        $errorBefore  = $request->session()->get('error');

        $this->routeName = $request->route()?->getName();
        $this->requestInput = $request->except([
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'remember',
            'avatar',
            'lampiran',
            'backup_file',
            'foto',
        ]);

        $oldValues = $this->snapshot($request);
        $response  = $next($request);

        // Tentukan apakah aksi gagal
        $errorsAfter = $request->session()->get('errors');
        $errorAfter  = $request->session()->get('error');

        $newErrors = $errorsAfter instanceof \Illuminate\Support\ViewErrorBag
            && $errorsAfter->isNotEmpty()
            && $errorsAfter !== $errorsBefore;

        $newError = $errorAfter !== null && $errorAfter !== $errorBefore;

        $status = $response->getStatusCode();
        $isInertiaLocation = $status === 409 && $response->headers->get('X-Inertia-Location') !== null;

        // Gagal: 4xx/5xx (kecuali 409 Inertia), atau ada flash error baru
        $failed = (!$isInertiaLocation && $status >= 400) || $newErrors || $newError;

        $recordable = $status < 400 || $isInertiaLocation;

        if ($request->user() && $this->shouldRecord($request) && $recordable) {
            $metadata = [
                'path'   => $request->path(),
                'status' => $status,
                'hasil'  => $failed ? 'gagal' : 'sukses',
            ];

            // Ambil pesan error pertama untuk deskripsi
            if ($failed) {
                if ($newErrors) {
                    $metadata['error'] = $errorsAfter->first();
                } elseif ($newError) {
                    $metadata['error'] = $errorAfter;
                }
            }

            // Meta khas (mis. hasil ekspor/impor backup) diisi oleh controller
            // melalui session()->flash('activity_log_metadata', [...]).
            $custom = $request->session()->get('activity_log_metadata');
            if (is_array($custom)) {
                $metadata = $custom + $metadata;
            }

            $model   = $this->routeModel($request);
            $target  = $this->targetFor($model);
            $descRaw = $this->description($request, $model);

            // Bila gagal, prefix "Gagal" + pesan error
            $desc = $failed
                ? 'Gagal ' . rtrim($descRaw, '.') . (($metadata['error'] ?? null) ? ': ' . $metadata['error'] : '') . '.'
                : $descRaw;

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => $request->route()?->getName() ?? strtolower($request->method()),
                'route_name'   => $request->route()?->getName(),
                'method'       => $request->method(),
                'description'   => $desc,
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => $metadata,
                'old_values'   => $oldValues,
                'new_values'   => $this->snapshot($request),
                'target_type'  => $target[0],
                'target_id'    => $target[1],
                'target_label' => $target[2],
            ]);

            $request->session()->forget('activity_log_metadata');
        }

        // Khusus arsip.scan: catat juga yang gagal (kode tidak ditemukan / kosong).
        // Middleware biasa mengabaikan response ≥ 400 agar log tidak penuh
        // dengan error, tapi scan QR adalah aksi bernilai audit — kegagalan
        // pencarian dokumen juga harus tercatat.
        if ($request->user() && $request->route()?->getName() === 'arsip.scan') {
            $scanMeta = [
                'path'   => $request->path(),
                'status' => $status,
                'hasil'  => $failed ? 'gagal' : 'sukses',
            ];
            if ($failed) {
                $scanMeta['error'] = $errorsAfter?->first() ?? $errorAfter ?? 'Kode arsip tidak ditemukan';
            }

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'arsip.scan',
                'route_name'   => 'arsip.scan',
                'method'       => 'POST',
                'description'  => $failed
                    ? 'Gagal pindai QR arsip: ' . $scanMeta['error'] . '.'
                    : 'Pindai QR arsip.',
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => $scanMeta,
                'old_values'   => null,
                'new_values'   => null,
                'target_type'  => null,
                'target_id'    => null,
                'target_label' => null,
            ]);
        }

        return $response;
    }

    /**
     * Model (bila ada) yang ter-bind pada parameter route — dipakai
     * snapshot nilai & pencatatan target.
     */
    private function routeModel(Request $request): ?Model
    {
        return collect($request->route()?->parameters() ?? [])
            ->first(fn ($value) => $value instanceof Model);
    }

    /**
     * Target aksi: [target_type, target_id, target_label]. NULL bila
     * rute tidak membawa model (mis. backup, scan QR, cadangan CLI).
     */
    private function targetFor(?Model $model): array
    {
        // 1.优先使用路由绑定的模型
        if ($model) {
            return match (true) {
                $model instanceof DokumenArsip => ['dokumen', $model->id, $model->nomor_surat ?: ('Dokumen #'.$model->id)],
                $model instanceof User => ['user', $model->id, $model->name],
                $model instanceof RakArsip => ['rak', $model->id, $model->kode_rak],
                $model instanceof BoksArsip => ['boks', $model->id, $model->kode_boks],
                $model instanceof Manfaat => ['halaman_publik', $model->id, $model->judul],
                $model instanceof AlurKerja => ['halaman_publik', $model->id, 'Langkah '.$model->nomor.' — '.$model->judul],
                $model instanceof BerandaSlide => ['halaman_publik', $model->id, 'Slide foto #'.$model->id],
                $model instanceof Faq => ['halaman_publik', $model->id, mb_substr($model->pertanyaan, 0, 80)],
                default => [null, null, null],
            };
        }

        // 2.Fallback: 基于 route_name + request 数据推导 target
        $input = $this->requestInput ?? [];
        $routeName = $this->routeName;

        return match (true) {
            str_starts_with($routeName, 'arsip.') => $this->targetFromArsipInput($input),
            str_starts_with($routeName, 'users.') => $this->targetFromUserInput($input),
            str_starts_with($routeName, 'rak.') => $this->targetFromRakInput($input),
            str_starts_with($routeName, 'boks.') => $this->targetFromBoksInput($input),
            str_starts_with($routeName, 'halaman-publik.')
                || str_starts_with($routeName, 'beranda.')
                || str_starts_with($routeName, 'manfaat.')
                || str_starts_with($routeName, 'alur_kerja.')
                || str_starts_with($routeName, 'faq.') => $this->targetFromPublicPageInput($input),
            default => [null, null, null],
        };
    }

    private function targetFromArsipInput(array $input): array
    {
        $id = $input['id'] ?? null;
        if ($id) {
            $arsip = DokumenArsip::find($id);
            if ($arsip) {
                return ['dokumen', $arsip->id, $arsip->nomor_surat ?: ('Dokumen #'.$arsip->id)];
            }
        }
        $nomor = $input['nomor_surat'] ?? null;
        $kode = $input['kode_arsip'] ?? null;
        return ['dokumen', $id, $nomor ?: $kode ?: null];
    }

    private function targetFromUserInput(array $input): array
    {
        $id = $input['id'] ?? null;
        if ($id) {
            $user = User::find($id);
            if ($user) {
                return ['user', $user->id, $user->name];
            }
        }
        return ['user', $id, $input['name'] ?? $input['email'] ?? null];
    }

    private function targetFromRakInput(array $input): array
    {
        $id = $input['id'] ?? null;
        if ($id) {
            $rak = RakArsip::find($id);
            if ($rak) {
                return ['rak', $rak->id, $rak->kode_rak];
            }
        }
        return ['rak', $id, $input['kode_rak'] ?? null];
    }

    private function targetFromBoksInput(array $input): array
    {
        $id = $input['id'] ?? null;
        if ($id) {
            $boks = BoksArsip::find($id);
            if ($boks) {
                return ['boks', $boks->id, $boks->kode_boks];
            }
        }
        return ['boks', $id, $input['kode_boks'] ?? null];
    }

    private function targetFromPublicPageInput(array $input): array
    {
        $id = $input['id'] ?? null;
        if ($id) {
            return ['halaman_publik', $id, 'ID:'.$id];
        }
        return ['halaman_publik', null, null];
    }

    private function snapshot(Request $request): ?array
    {
        $model = $this->routeModel($request);

        if ($model instanceof Model) {
            return Arr::except($model->getAttributes(), [
                'password',
                'remember_token',
                'created_at',
                'updated_at',
                'deleted_at',
            ]);
        }

        $input = $request->except([
            '_token',
            '_method',
            'password',
            'password_confirmation',
            'remember',
            'avatar',
            'lampiran',
            'backup_file',
            'foto',
        ]);

        return $input === [] ? null : $input;
    }

    private function shouldRecord(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        // Ekspor/unduh cadangan adalah GET — dicatat juga karena data keluar dari sistem.
        if ($request->isMethod('GET') && in_array($routeName, ['backup.export', 'backup.files'], true)) {
            return true;
        }

        // Halaman utama (GET) bernilai audit — semua item sidebar admin & staff
        // tercatat agar jejak kunjungan页面 lengkap. Tidak termasuk:
        //   • hal public (home/fitur/alur-kerja/bantuan) — guest-only
        //   • password/verification/reset — blacklist sudah di bawah
        //   • auth show/create (login, register, forgot-password, reset-password)
        //   • report CSV download (sudah dicatat di shouldRecord via backup.export/files pattern)
        $recordableGet = in_array($routeName, [
            // Admin — halaman utama
            'dashboard',
            'arsip.index',
            'arsip.show',
            'arsip.create',
            'arsip.edit',
            'arsip.export',
            'rak.index',
            'rak.store',
            'rak.update',
            'boks.index',
            'boks.store',
            'boks.update',
            'backup.index',
            'backup.export',
            'backup.files',
            'users.index',
            'activity.index',
            'halaman-publik.beranda',
            'halaman-publik.manfaat',
            'halaman-publik.alur-kerja',
            'halaman-publik.faq',
            // Staff — operasional
            'scan.index',
            'profile.edit',
            // Reports (GET CSV export)
            'reports.activity',
            'reports.users',
            'reports.racks',
            'reports.sections',
        ], true);

        if ($request->isMethod('GET') && $recordableGet) {
            return true;
        }

        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)
            && ! $request->is('login', 'logout', 'forgot-password/*', 'reset-password', 'confirm-password', 'email/verification-notification')
            && ! in_array($routeName, [
                'login',
                'logout',
                'password.request',
                'password.otp.send',
                'password.otp.verify',
                'password.otp.reset',
                'password.confirm',
                'verification.send',
            ], true);
    }

    private function namaSeksi(?int $id): ?string
    {
        if (!$id) return null;
        return Seksi::find($id)?->nama_seksi;
    }

    private function description(Request $request, ?Model $model = null): string
    {
        $routeName = $request->route()?->getName();

        $seksiIdFromModel = fn() => $model?->seksi_id !== null ? (int) $model->seksi_id : null;

        switch ($routeName) {
            case 'users.store':
                $nama = $this->namaSeksi((int) $request->input('seksi_id'));
                return $nama !== null
                    ? "Menambahkan pengguna baru di Seksi $nama."
                    : 'Menambahkan pengguna baru.';

            case 'users.update':
                $nama = $this->namaSeksi($seksiIdFromModel());
                return $nama !== null
                    ? "Memperbarui data pengguna di Seksi $nama."
                    : 'Memperbarui data pengguna.';

            case 'users.destroy':
                return 'Menonaktifkan pengguna.';
            case 'users.activate':
                return 'Mengaktifkan pengguna.';
            case 'users.password':
                return 'Meriset kata sandi pengguna.';
            case 'users.force-delete':
                return 'Menghapus permanen pengguna.';

            case 'arsip.store':
                $nama = $this->namaSeksi((int) $request->input('seksi_id'));
                return $nama !== null
                    ? "Menambahkan dokumen arsip ke Seksi $nama."
                    : 'Menambahkan dokumen arsip.';

            case 'arsip.update':
                $nama = $this->namaSeksi($seksiIdFromModel() ?? (int) $request->input('seksi_id'));
                return $nama !== null
                    ? "Memperbarui dokumen arsip di Seksi $nama."
                    : 'Memperbarui dokumen arsip.';

            case 'arsip.destroy':
                $nama = $this->namaSeksi($seksiIdFromModel());
                return $nama !== null
                    ? "Menghapus dokumen arsip dari Seksi $nama."
                    : 'Menghapus dokumen arsip.';

            case 'arsip.force-delete':
                return 'Menghapus permanen dokumen arsip.';
            case 'arsip.pinjam':
                return 'Mencatat peminjaman dokumen.';
            case 'arsip.kembalikan':
                return 'Mencatat pengembalian dokumen.';
            case 'arsip.settersedia':
                return 'Mengembalikan status arsip menjadi tersedia.';
            case 'arsip.tandahilang':
                return 'Menandai dokumen arsip hilang.';
            case 'arsip.scan':
                return 'Pindai QR arsip.';
            case 'backup.export':
                return 'Mengunduh berkas cadangan arsip.';
            case 'backup.import':
                return 'Memulihkan data dari berkas cadangan.';
            case 'backup.run':
                return 'Membuat cadangan lengkap (bundel).';
            case 'backup.files':
                return 'Mengunduh cadangan tersimpan.';
            case 'arsip.cadangkan':
                return 'Mencadangkan basis data arsip.';
            case 'profile.update':
                return 'Memperbarui profil.';
            case 'profile.destroy':
                return 'Menghapus akun sendiri.';

            // ── GET admin pages ──────────────────────────────────────
            case 'dashboard':
                return 'Membuka dashboard.';
            case 'arsip.index':
                return 'Membuka daftar semua arsip.';
            case 'arsip.show':
                $nama = $this->namaSeksi($seksiIdFromModel());
                return $nama !== null
                    ? "Melihat dokumen arsip di Seksi $nama."
                    : 'Melihat dokumen arsip.';
            case 'arsip.create':
                return 'Membuka form rekam berkas baru.';
            case 'arsip.edit':
                return 'Membuka form edit arsip.';
            case 'arsip.export':
                return 'Mengekspor daftar arsip ke Excel.';
            case 'rak.index':
                return 'Membuka daftar rak arsip.';
            case 'boks.index':
                return 'Membuka daftar boks arsip.';
            case 'backup.index':
                return 'Membuka halaman backup.';
            case 'users.index':
                return 'Membuka daftar pengguna.';
            case 'activity.index':
                return 'Membuka log aktivitas.';
            case 'halaman-publik.beranda':
                return 'Membuka halaman foto carousel beranda.';
            case 'halaman-publik.manfaat':
                return 'Membuka halaman kartu manfaat.';
            case 'halaman-publik.alur-kerja':
                return 'Membuka halaman langkah prosedur.';
            case 'halaman-publik.faq':
                return 'Membuka halaman FAQ bantuan.';
            case 'scan.index':
                return 'Membuka halaman pindai QR berkas.';
            case 'profile.edit':
                return 'Membuka halaman profil.';
            case 'reports.activity':
                return 'Mengekspor laporan aktivitas.';
            case 'reports.users':
                return 'Mengekspor laporan pengguna.';
            case 'reports.racks':
                return 'Mengekspor laporan rak.';
            case 'reports.sections':
                return 'Mengekspor laporan seksi.';

            case 'rak.store':
                $nama = $this->namaSeksi((int) $request->input('seksi_id'));
                return $nama !== null
                    ? "Menambahkan rak arsip di Seksi $nama."
                    : 'Menambahkan rak arsip.';

            case 'rak.update':
                $nama = $this->namaSeksi($seksiIdFromModel());
                return $nama !== null
                    ? "Memperbarui rak arsip di Seksi $nama."
                    : 'Memperbarui rak arsip.';

            case 'rak.destroy':
                $nama = $this->namaSeksi($seksiIdFromModel());
                return $nama !== null
                    ? "Menghapus rak arsip dari Seksi $nama."
                    : 'Menghapus rak arsip.';

            case 'boks.store':
                return 'Menambahkan boks arsip.';
            case 'boks.update':
                return 'Memperbarui boks arsip.';
            case 'boks.destroy':
                return 'Menghapus boks arsip.';
            case 'boks.destroy-bulk':
                return 'Menghapus massal boks arsip.';
            case 'rak.destroy-bulk':
                return 'Menghapus massal rak arsip.';
            case 'users.destroy-bulk':
                return 'Menghapus massal pengguna.';
            case 'beranda.slides.store':
                return 'Menambahkan foto carousel beranda.';
            case 'beranda.slides.destroy':
                return 'Menghapus foto carousel beranda.';
            case 'manfaat.store':
                return 'Menambahkan kartu manfaat.';
            case 'manfaat.update':
                return 'Memperbarui kartu manfaat.';
            case 'manfaat.destroy':
                return 'Menghapus kartu manfaat.';
            case 'alur_kerja.store':
                return 'Menambahkan langkah prosedur.';
            case 'alur_kerja.update':
                return 'Memperbarui langkah prosedur.';
            case 'alur_kerja.destroy':
                return 'Menghapus langkah prosedur.';
            case 'faq.store':
                return 'Menambahkan FAQ bantuan.';
            case 'faq.update':
                return 'Memperbarui FAQ bantuan.';
            case 'faq.destroy':
                return 'Menghapus FAQ bantuan.';

            default:
                return sprintf('%s %s', $request->method(), $request->path());
        }
    }
}