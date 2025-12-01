<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): SymfonyResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Get the actual origin from ngrok forwarding headers
        $user = Auth::user();
        
        // Check for ngrok's forwarded host or use config app.url
        $baseUrl = $request->header('X-Forwarded-Host') 
            ? $request->getScheme() . '://' . $request->header('X-Forwarded-Host')
            : config('app.url');
        
        $dashboardPath = match($user->role) {
            'admin' => '/admin/dashboard',
            'teacher' => '/teacher/dashboard',
            'student' => '/student/dashboard',
            default => '/',
        };
        
        // Use Inertia::location for external redirect
        return Inertia::location($baseUrl . $dashboardPath);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
