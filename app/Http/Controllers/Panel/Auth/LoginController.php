<?php

namespace App\Http\Controllers\Panel\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Nazik controller: validation və authentication LoginRequest-dədir,
 * burada yalnız HTTP axını (view, redirect, session) idarə olunur.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('panel.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Session fixation hücumuna qarşı: login-dən sonra yeni session ID
        $request->session()->regenerate();

        return redirect()->intended(route('panel.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken(); // köhnə CSRF token yararsız olur

        return redirect()->route('panel.login');
    }
}
