<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreBookingRequest;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Service;
use App\Services\Booking\BookingService;
use App\Services\ImageStorage;
use App\Support\Money;
use App\Support\ServiceGroups;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * Rezervasiya wizard-ı: Usta → Xidmət → Vaxt → Məlumat.
 * Xidmətlər bərbərə məxsusdur; "Fərq etməz" seçəndə eyni adlı xidmətlər qrup kimi göstərilir.
 */
class BookingController extends Controller
{
    public function create(Request $request): View
    {
        $settings = BusinessSetting::current();
        $today = CarbonImmutable::now($settings->timezone);

        $services = Service::query()->bookable()->ordered()->with('barber:id')->get();
        $barbers = Barber::query()->active()->ordered()
            ->whereIn('id', $services->pluck('barber_id')) // xidməti olmayan bərbər seçilə bilməz
            ->with(['workingHours:id,barber_id,weekday', 'availabilityExceptions' => fn ($q) => $q
                ->where('date', '>=', $today->toDateString())
                ->select('id', 'barber_id', 'date', 'is_day_off')])
            ->get();

        // Wizard-ın bütün statik datası bir JSON obyektində — Alpine onu x-data ilə alır.
        // Burada yalnız public məlumat var: bərbərin telefonu, Telegram ID-si və s. düşmür.
        $config = [
            'today' => $today->toDateString(),
            'horizonDays' => $settings->booking_horizon_days,
            'slotsUrl' => route('booking.slots'),
            'services' => $services->map(fn (Service $s) => [
                'id' => $s->id,
                'slug' => $s->slug,
                'barberId' => $s->barber_id,
                'groupKey' => $s->groupKey(),
                'name' => $s->name,
                'description' => $s->description,
                'duration' => $s->duration_minutes,
                'price' => Money::format($s->price),
            ])->values(),
            'groups' => ServiceGroups::from($services),
            'barbers' => $barbers->map(fn (Barber $b) => [
                'id' => $b->id,
                'slug' => $b->slug,
                'name' => $b->name,
                'title' => $b->title,
                'photo' => ImageStorage::url($b->photo_path),
                'initial' => mb_strtoupper(mb_substr($b->name, 0, 1)),
                // Tarix zolağında işləmədiyi günləri sönük göstərmək üçün (dəqiq yoxlama — slots endpoint-də)
                'weekdays' => $b->workingHours->pluck('weekday')->all(),
                'offDates' => $b->availabilityExceptions->where('is_day_off', true)->map(fn ($e) => $e->date->toDateString())->values(),
                'extraDates' => $b->availabilityExceptions->where('is_day_off', false)->map(fn ($e) => $e->date->toDateString())->values(),
            ])->values(),
            'preselect' => [
                'barber' => $request->query('barber'),   // ?barber=rauf
                'service' => $request->query('service'), // ?service=sac-kesimi-rauf (konkret xidmət)
                'group' => $request->query('xidmet'),    // ?xidmet=sac-kesimi (Fərq etməz + qrup)
            ],
            'old' => [
                'service_id' => old('service_id'),
                'barber_id' => old('barber_id'),
                'date' => old('date'),
                'time' => old('time'),
                'name' => old('name'),
                'phone' => old('phone'),
            ],
            // Server xətasından sonra hansı addıma qayıdılsın
            'errorStep' => match (true) {
                Session::has('error') => 3, // vaxt tutulub → saat seçiminə
                Session::has('errors') => 4,
                default => null,
            },
        ];

        return view('public.booking', ['settings' => $settings, 'config' => $config]);
    }

    public function store(StoreBookingRequest $request, BookingService $booking): RedirectResponse
    {
        $service = Service::query()->bookable()->findOrFail($request->validated('service_id'));
        $barberId = $request->validated('barber_id');
        $barber = $barberId === 'any' ? null : Barber::query()->active()->findOrFail($barberId);

        // Konkret bərbər seçilibsə, xidmət onun olmalıdır — yoxlama BookingService-dədir
        $result = $booking->book(
            $service,
            $barber,
            $request->startsAt(),
            $request->validated('name'),
            $request->validated('phone'),
        );

        return redirect()->route('booking.show', $result->token)->with('booked', true);
    }
}
