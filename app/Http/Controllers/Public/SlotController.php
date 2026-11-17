<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Service;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Layihədəki yeganə JSON endpoint: wizard tarix seçəndə boş saatları gətirir.
 * Ayrıca API (api.php, Sanctum) lazım deyil — eyni domen, eyni session.
 *
 * GET /rezervasiya/saatlar?service=1&barber=2|any&date=2030-05-07
 *   → {"date": "2030-05-07", "slots": ["10:00", "10:15", …]}
 */
class SlotController extends Controller
{
    public function __invoke(Request $request, AvailabilityService $availability): JsonResponse
    {
        $data = $request->validate([
            'service' => ['required', 'integer'],
            'barber' => ['required', 'string', 'max:20'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $service = Service::query()->bookable()->findOrFail($data['service']);

        $slots = $data['barber'] === 'any'
            ? $availability->slotsForAnyBarber($service, $data['date'])
            : $availability->slots(Barber::query()->active()->findOrFail((int) $data['barber']), $service, $data['date']);

        $tz = BusinessSetting::timezone();

        return response()
            ->json([
                'date' => $data['date'],
                'slots' => array_map(fn (CarbonImmutable $s) => $s->setTimezone($tz)->format('H:i'), $slots),
            ])
            ->header('Cache-Control', 'no-store'); // boş saatlar hər an dəyişə bilər
    }
}
