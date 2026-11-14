<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        ActivityLog::create([
            'user_id'      => $user->id,
            'action'       => 'auth.login',
            'route_name'   => 'auth.login',
            'method'       => 'POST',
            'description'  => 'Login ke sistem.',
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['path' => 'login', 'status' => 200],
            'old_values'   => null,
            'new_values'   => null,
            'target_type'  => 'user',
            'target_id'    => $user->id,
            'target_label' => $user->name,
        ]);

        $request->session()->flash('welcome', true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        if ($user) {
            ActivityLog::create([
                'user_id'      => $user->id,
                'action'       => 'auth.logout',
                'route_name'   => 'auth.logout',
                'method'       => 'POST',
                'description'  => 'Logout dari sistem.',
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
                'metadata'     => ['path' => 'logout', 'status' => 200],
                'old_values'   => null,
                'new_values'   => null,
                'target_type'  => 'user',
                'target_id'    => $user->id,
                'target_label' => $user->name,
            ]);
        }

        return redirect('/');
    }
}
