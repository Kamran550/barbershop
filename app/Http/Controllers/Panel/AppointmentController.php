<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\ManualBookingRequest;
use App\Http\Requests\Panel\RescheduleRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Service;
use App\Services\Booking\BookingService;
use App\Services\ReportService;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Rezervasiyaların idarəsi. Yazma əməliyyatlarının hamısı BookingService-dən keçir
 * (lock, availability yoxlaması, status maşını, audit) — controller yalnız HTTP-ni idarə edir.
 */
class AppointmentController extends Controller
{
    public function __construct(private readonly BookingService $booking) {}

    public function index(Request $request, ReportService $reports): View
    {
        Gate::authorize('viewAny', Appointment::class);
        $user = $request->user();
        $tz = BusinessSetting::timezone();
        $today = CarbonImmutable::now($tz);

        $filters = $request->validate([
            'period' => ['nullable', Rule::in(['today', 'upcoming', 'past', 'month', 'custom'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            'barber_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $period = $filters['period'] ?? (isset($filters['from']) ? 'custom' : 'upcoming');

        $query = Appointment::query()
            ->visibleTo($user) // bərbər yalnız özününkünü — filter nə olursa olsun
            ->with(['customer:id,name,phone', 'barber:id,name']);

        match ($period) {
            'today' => $query->startingWithin($reports->dayRange($today)),
            'upcoming' => $query->where('starts_at', '>=', now()),
            'past' => $query->where('starts_at', '<', now()),
            'month' => $query->startingWithin($reports->periodRange($today->startOfMonth(), $today->endOfMonth())),
            'custom' => $query->startingWithin($reports->periodRange(
                CarbonImmutable::parse($filters['from'] ?? $today->toDateString(), $tz),
                CarbonImmutable::parse($filters['to'] ?? $filters['from'] ?? $today->toDateString(), $tz),
            )),
        };

        $query
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($user->isManager() && ($filters['barber_id'] ?? null), fn ($q) => $q->where('barber_id', $filters['barber_id']))
            ->when($filters['q'] ?? null, function ($q, string $term) {
                $digits = PhoneNumber::searchFragment($term);
                $q->whereHas('customer', fn ($c) => $c->where(fn ($w) => $w
                    ->where('name', 'like', '%'.addcslashes($term, '%_').'%')
                    ->when($digits, fn ($w) => $w->orWhere('phone', 'like', '%'.$digits.'%'))));
            })
            ->orderBy('starts_at', in_array($period, ['upcoming', 'today'], true) ? 'asc' : 'desc');

        return view('panel.appointments.index', [
            'appointments' => $query->paginate(25)->withQueryString(),
            'filters' => [...$filters, 'period' => $period],
            'barbers' => $user->isManager() ? Barber::query()->ordered()->get(['id', 'name']) : collect(),
            'tz' => $tz,
        ]);
    }

    public function show(Request $request, Appointment $appointment): View
    {
        Gate::authorize('view', $appointment);
        $appointment->load(['customer', 'barber:id,name', 'creator:id,name']);

        return view('panel.appointments.show', [
            'appointment' => $appointment,
            'tz' => BusinessSetting::timezone(),
            // Tarixçə (kim nə vaxt nəyi dəyişdi) — yalnız menecer
            'history' => $request->user()->isManager()
                ? AuditLog::query()->with('user:id,name')->whereMorphedTo('auditable', $appointment)->latest('id')->get()
                : collect(),
        ]);
    }

    /** Təsdiqlə / tamamlandı / gəlmədi. */
    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('updateStatus', $appointment);

        $data = $request->validate([
            'status' => ['required', Rule::in([
                AppointmentStatus::Confirmed->value,
                AppointmentStatus::Completed->value,
                AppointmentStatus::NoShow->value,
            ])],
        ]);

        $status = AppointmentStatus::from($data['status']);
        $this->booking->changeStatus($appointment, $status, $request->user());

        return back()->with('status', "Status dəyişdi: {$status->label()}.");
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        Gate::authorize('updateStatus', $appointment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $this->booking->cancel($appointment, $request->user(), $data['reason'] ?? null);

        return back()->with('status', 'Rezervasiya ləğv edildi.');
    }

    // ── Əl ilə rezervasiya (menecer) ────────────────────────────────

    public function create(Request $request): View
    {
        Gate::authorize('create', Appointment::class);

        return view('panel.appointments.create', [
            'barbers' => Barber::query()->active()->ordered()
                ->with(['services' => fn ($q) => $q->active()->ordered()->select('id', 'barber_id', 'name', 'price', 'duration_minutes')])
                ->get(['id', 'name']),
            'today' => CarbonImmutable::now(BusinessSetting::timezone())->toDateString(),
            'preset' => $request->only(['barber_id', 'date', 'time']),
        ]);
    }

    public function store(ManualBookingRequest $request): RedirectResponse
    {
        $barber = Barber::query()->active()->findOrFail($request->validated('barber_id'));
        $service = Service::query()->findOrFail($request->validated('service_id'));

        $result = $this->booking->book(
            $service,
            $barber,
            $request->startsAt(),
            $request->validated('name'),
            $request->validated('phone'),
            AppointmentSource::Manual,
            $request->user(),
        );

        return redirect()->route('panel.appointments.show', $result->appointment)->with('status', 'Rezervasiya yaradıldı.');
    }

    // ── Başqa vaxta / bərbərə keçirmə (menecer) ─────────────────────

    public function edit(Appointment $appointment): View
    {
        Gate::authorize('reschedule', $appointment);
        $appointment->load(['customer:id,name', 'barber:id,name']);
        $tz = BusinessSetting::timezone();

        return view('panel.appointments.edit', [
            'appointment' => $appointment,
            // Yalnız eyni adlı xidməti göstərən aktiv bərbərlər
            'barbers' => Barber::query()->active()->ordered()
                ->where(fn ($q) => $q
                    ->whereHas('services', fn ($s) => $s->active()->where('name', $appointment->service_name))
                    ->orWhere('id', $appointment->barber_id))
                ->get(['id', 'name']),
            'local' => $appointment->starts_at->setTimezone($tz),
        ]);
    }

    public function update(RescheduleRequest $request, Appointment $appointment): RedirectResponse
    {
        $barber = Barber::query()->findOrFail($request->validated('barber_id'));
        $this->booking->reschedule($appointment->load('barber'), $request->startsAt(), $barber, $request->user());

        return redirect()->route('panel.appointments.show', $appointment)->with('status', 'Rezervasiyanın vaxtı dəyişdirildi.');
    }
}
