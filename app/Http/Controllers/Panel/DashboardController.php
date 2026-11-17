<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Panelin giriş səhifəsi. Bütün rəqəmlər ReportService-dən gəlir və rola görə filtrlənir:
 * menecer bütün berberxananı, bərbər yalnız özünü görür.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        return view('panel.dashboard', [
            'user' => $request->user(),
            ...$reports->dashboard($request->user()),
        ]);
    }
}
