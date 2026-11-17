<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Qrafikim" menyusunun qısa yolu: bərbəri öz qrafik səhifəsinə aparır.
 * Qrafik səhifəsi menecerlə ortaqdır; icazəni BarberPolicy::manageSchedule yoxlayır.
 */
class MyScheduleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $barber = $request->user()->barber;
        abort_if($barber === null, 403, 'Hesabınız bərbər profilinə bağlanmayıb.');

        return redirect()->route('panel.barbers.schedule', $barber);
    }
}
