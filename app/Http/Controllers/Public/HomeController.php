<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Service;
use App\Support\ServiceGroups;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $settings = BusinessSetting::current();
        $now = CarbonImmutable::now($settings->timezone);

        return view('public.home', [
            'settings' => $settings,
            // Qiymət siyahısı: eyni adlı xidmətlər bir sətirdə, qiymət aralığı ilə
            'serviceGroups' => ServiceGroups::from(Service::query()->bookable()->ordered()->with('barber:id')->get()),
            'barbers' => Barber::query()->active()->ordered()
                ->with(['services' => fn ($q) => $q->active()->ordered()])
                ->get(),
            'todayHours' => $settings->opening_hours[$now->isoWeekday()] ?? null,
        ]);
    }
}
