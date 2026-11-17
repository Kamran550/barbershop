<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Services\ReportService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gəlir hesabatları — yalnız menecer (route səviyyəsində role:manager). */
class ReportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): View
    {
        $tz = BusinessSetting::timezone();
        $today = CarbonImmutable::now($tz);

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'barber_id' => ['nullable', 'integer'],
        ]);

        $from = CarbonImmutable::parse($data['from'] ?? $today->startOfMonth()->toDateString(), $tz);
        $to = CarbonImmutable::parse($data['to'] ?? $today->toDateString(), $tz);

        // Hazır dövrlər
        $presets = [
            'Bu ay' => [$today->startOfMonth(), $today],
            'Keçən ay' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'Son 7 gün' => [$today->subDays(6), $today],
            'Bu il' => [$today->startOfYear(), $today],
        ];

        return view('panel.reports.index', [
            'report' => $reports->revenueReport($from, $to, isset($data['barber_id']) ? (int) $data['barber_id'] : null),
            'from' => $from,
            'to' => $to,
            'barberId' => $data['barber_id'] ?? null,
            'barbers' => Barber::query()->ordered()->get(['id', 'name']),
            'presets' => $presets,
        ]);
    }
}
