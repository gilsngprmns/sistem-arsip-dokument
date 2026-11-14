<?php

namespace App\Http\Controllers;

use App\Models\Seksi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'q' => $request->string('q')->toString(),
            'role' => $request->string('role')->toString(),
            'status' => $request->string('status')->toString(),
            'seksi_id' => $request->string('seksi_id')->toString(),
        ];

        $users = User::query()
            ->with('seksi:id,nama_seksi')
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($query) use ($filters) {
                $query->where('name', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%");
            }))
            ->when(in_array($filters['role'], ['admin', 'staf'], true), fn ($query) => $query->where('role', $filters['role']))
            ->when($filters['status'] === 'aktif', fn ($query) => $query->where('is_active', true))
            ->when($filters['status'] === 'nonaktif', fn ($query) => $query->where('is_active', false))
            ->when(
                $filters['seksi_id'] !== '' && ctype_digit($filters['seksi_id']),
                fn ($query) => $query->where('seksi_id', (int) $filters['seksi_id']),
            )
            ->when($filters['seksi_id'] === 'none', fn ($query) => $query->whereNull('seksi_id'))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'staf' THEN 2 ELSE 3 END ASC")
            ->oldest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'seksiList' => Seksi::query()->orderByOrganisasi()->get(['id', 'nama_seksi']),
            'filters' => $filters,
            'activeAdminCount' => User::query()
                ->where('role', 'admin')
                ->where('is_active', true)
                ->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['admin', 'staf'])],
            'seksi_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'staf'),
                'nullable',
                'exists:seksi,id',
            ],
            'is_active' => ['boolean'],
        ], [
            'seksi_id.required' => 'Seksi wajib diisi untuk role Staf.',
        ]);

        if (($validated['role'] ?? null) === 'admin' && blank($validated['seksi_id'] ?? null)) {
            $validated['seksi_id'] = null;
        }

        $validated['password'] = Hash::make($validated['password']);
        User::create($validated);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::in(['admin', 'staf'])],
            'seksi_id' => [
                Rule::requiredIf(fn () => $request->input('role') === 'staf'),
                'nullable',
                'exists:seksi,id',
            ],
            'is_active' => ['boolean'],
        ], [
            'seksi_id.required' => 'Seksi wajib diisi untuk role Staf.',
        ]);

        if (($validated['role'] ?? null) === 'admin' && blank($validated['seksi_id'] ?? null)) {
            $validated['seksi_id'] = null;
        }

        $willDeactivate = ($validated['is_active'] ?? true) === false;
        $willDemote = ($validated['role'] ?? '') !== 'admin' && $user->role === 'admin';

        if ($user->is($request->user()) && $willDeactivate) {
            return back()->withErrors(['is_active' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        if (($willDeactivate || $willDemote) && $this->isLastActiveAdmin($user)) {
            return back()->withErrors([
                'user' => 'Tidak dapat menonaktifkan atau menurunkan role satu-satunya admin aktif.',
            ]);
        }

        $wasInactive = ! $user->is_active;
        $willActivate = ($validated['is_active'] ?? false) === true;

        $user->update($validated);

        // Recovery dari lockout brute-force: wajib reset counter saat
        // admin mengaktifkan kembali akun yang sedang nonaktif.
        if ($wasInactive && $willActivate) {
            $user->login_attempts = 0;
            $user->save();

            return redirect()->route('users.index')
                ->with('success', 'Pengguna berhasil diperbarui.')
                ->with('recovery', true)
                ->with('recovery_email', $user->email);
        }

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('users.index')->with('success', "Password {$user->name} berhasil direset.");
    }

    public function activate(User $user): RedirectResponse
    {
        $user->update(['is_active' => true]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil diaktifkan kembali.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak dapat dinonaktifkan.']);
        }

        if ($this->isLastActiveAdmin($user)) {
            return back()->withErrors(['user' => 'Tidak dapat menonaktifkan satu-satunya admin aktif.']);
        }

        $user->update(['is_active' => false]);

        return redirect()->route('users.index')->with('success', 'Pengguna berhasil dinonaktifkan.');
    }

    public function forceDelete(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        }

        $user->forceDelete();

        return redirect()->route('users.index')->with('danger', 'Pengguna berhasil dihapus permanen dari database.');
    }

    public function destroyBulk(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer|exists:users,id',
        ])['ids'];

        $userCount = count($ids);
        $deleted = User::whereIn('id', $ids)->delete();

        session()->flash('activity_log_metadata', [
            'jenis' => 'hapus_pengguna',
            'status' => 'sukses',
            'jumlah_dihapus' => $deleted,
            'ids' => $ids,
        ]);

        return redirect()->route('users.index')
            ->with('danger', "$deleted pengguna berhasil dihapus permanen.");
    }

    protected function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== 'admin' || ! $user->is_active) {
            return false;
        }

        return User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->whereKeyNot($user->id)
            ->doesntExist();
    }
}
