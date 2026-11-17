<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // Login olmayan istifadəçi paneldə → login səhifəsinə; login olan login səhifəsində → panelə
        $middleware->redirectGuestsTo(fn () => route('panel.login'));
        $middleware->redirectUsersTo(fn () => route('panel.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Biznes qaydası pozuntusu → əvvəlki səhifəyə qayıt, daxil edilən datanı saxla, mesajı göstər
        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->with('error', $e->getMessage());
        });
    })->create();
