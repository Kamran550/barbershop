<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Login zamanı deaktiv işçi onsuz da daxil ola bilmir. Bu middleware isə
 * session AÇIQ olarkən deaktiv edilən işçini növbəti request-də sistemdən çıxarır.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('panel.login')->withErrors(['email' => __('auth.inactive')]);
        }

        return $next($request);
    }
}
