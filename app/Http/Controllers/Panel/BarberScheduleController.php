<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\AvailabilityExceptionRequest;
use App\Http\Requests\Panel\WeeklyScheduleRequest;
use App\Models\AvailabilityException;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Services\ScheduleService;
use App\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Menecer: bərbərin həftəlik qrafiki, fasilələri və tarix istisnaları. */
class BarberScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedule) {}

    public function edit(Barber $barber): View
    {
        Gate::authorize('manageSchedule', $barber);

        $barber->load(['workingHours', 'breaks']);
        $today = CarbonImmutable::now(BusinessSetting::timezone())->toDateString();

        return view('panel.barbers.schedule', [
            'barber' => $barber,
            'days' => $this->formState($barber),
            'exceptions' => $barber->availabilityExceptions()->where('date', '>=', $today)->orderBy('date')->get(),
            'today' => $today,
        ]);
    }

    public function update(WeeklyScheduleRequest $request, Barber $barber): RedirectResponse
    {
        $this->schedule->saveWeekly($barber, $request->validated('days'));

        return redirect()->route('panel.barbers.schedule', $barber)->with('status', 'Həftəlik qrafik yadda saxlanıldı.');
    }

    public function storeException(AvailabilityExceptionRequest $request, Barber $barber): RedirectResponse
    {
        [, $conflicts] = $this->schedule->saveException($barber, $request->validated());

        $redirect = redirect()->route('panel.barbers.schedule', $barber)->with('status', 'Tarix istisnası yadda saxlanıldı.');

        return $conflicts > 0
            ? $redirect->with('warning', "Diqqət: həmin gün {$conflicts} aktiv rezervasiya yeni qrafikdən kənarda qalır. Müştərilərlə əlaqə saxlayıb vaxtı dəyişin və ya ləğv edin.")
            : $redirect;
    }

    /**
     * scopeBindings: {availabilityException} $barber->availabilityExceptions() daxilində axtarılır —
     * başqa bərbərin istisnasının ID-sini yazmaq 404 verir (IDOR qoruması).
     */
    public function destroyException(Barber $barber, AvailabilityException $availabilityException): RedirectResponse
    {
        Gate::authorize('manageSchedule', $barber);
        $availabilityException->setRelation('barber', $barber);
        $this->schedule->deleteException($availabilityException);

        return redirect()->route('panel.barbers.schedule', $barber)->with('status', 'İstisna silindi.');
    }

    /**
     * Alpine formu üçün 7 günlük vəziyyət. Validation xətası olubsa, istifadəçinin daxil etdiyi
     * (old) dəyərlər göstərilir, yoxsa DB-dəki qrafik.
     */
    private function formState(Barber $barber): array
    {
        $old = old('days');
        $hours = $barber->workingHours->keyBy('weekday');
        $breaks = $barber->breaks->groupBy('weekday');
        $errors = session('errors')?->getBag('default');

        return collect(Weekday::LABELS)->map(function (string $label, int $weekday) use ($old, $hours, $breaks, $errors) {
            if (is_array($old)) {
                $day = $old[$weekday] ?? [];
                $state = [
                    'enabled' => filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'start' => $day['start'] ?? '10:00',
                    'end' => $day['end'] ?? '20:00',
                    'breaks' => array_values($day['breaks'] ?? []),
                ];
            } else {
                $h = $hours->get($weekday);
                $state = [
                    'enabled' => $h !== null,
                    'start' => $h ? substr($h->start_time, 0, 5) : '10:00',
                    'end' => $h ? substr($h->end_time, 0, 5) : '20:00',
                    'breaks' => ($breaks->get($weekday) ?? collect())
                        ->map(fn ($b) => ['start' => substr($b->start_time, 0, 5), 'end' => substr($b->end_time, 0, 5)])
                        ->values()->all(),
                ];
            }

            return [
                'weekday' => $weekday,
                'label' => $label,
                ...$state,
                'errors' => $errors ? array_values(array_unique(array_merge(...array_values($errors->get("days.$weekday.*") ?: [[]])))) : [],
            ];
        })->values()->all();
    }
}
