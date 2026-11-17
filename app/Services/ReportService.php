<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Dashboard və hesabatların bütün rəqəmləri. Controller-də sorğu yazılmır.
 *
 * Qaydalar:
 *  - GƏLİR = yalnız "tamamlanıb" statuslu rezervasiyaların snapshot qiyməti (service_price).
 *    Təsdiqlənmiş, amma hələ baş tutmamış rezervasiya gəlir deyil — "gözlənilən gəlir" ayrıca göstərilir.
 *  - Dövrlər berberxananın timezone-unda hesablanır (Bakı günü), sorğu UTC ilə aparılır.
 *  - Bərbər yalnız öz rəqəmlərini görür (visibleTo).
 */
class ReportService
{
    /** Bakı tarixi → [UTC başlanğıc, UTC son) */
    public function dayRange(CarbonImmutable $localDay): array
    {
        $start = $localDay->startOfDay();

        return [$start->utc(), $start->addDay()->utc()];
    }

    /** Lokal tarixlər [from, to] (hər ikisi daxil) → UTC interval */
    public function periodRange(CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [$from->startOfDay()->utc(), $to->startOfDay()->addDay()->utc()];
    }

    public function dashboard(User $user): array
    {
        $tz = BusinessSetting::timezone();
        $now = CarbonImmutable::now($tz);
        [$todayStart, $todayEnd] = $this->dayRange($now);
        [$monthStart, $monthEnd] = $this->periodRange($now->startOfMonth(), $now->endOfMonth());

        $visible = fn (): Builder => Appointment::query()->visibleTo($user);
        $inMonth = fn (Builder $q) => $q->startingWithin([$monthStart, $monthEnd]);

        return [
            'now' => $now,
            'todayCount' => $visible()->occupying()->overlapping($todayStart, $todayEnd)->count(),
            'upcomingCount' => $visible()->occupying()->where('starts_at', '>=', now())->count(),
            'monthCompleted' => $inMonth($visible())->where('status', AppointmentStatus::Completed)->count(),
            'monthCancelled' => $inMonth($visible())->where('status', AppointmentStatus::Cancelled)->count(),
            'monthRevenue' => (float) $inMonth($visible())->where('status', AppointmentStatus::Completed)->sum('service_price'),
            'customerCount' => Customer::query()->visibleTo($user)->count(),

            // Bugünkü gündəlik: ləğv edilmişlər xaric hamısı, vaxta görə
            'agenda' => $visible()
                ->with(['customer:id,name,phone', 'barber:id,name'])
                ->where('status', '!=', AppointmentStatus::Cancelled)
                ->startingWithin([$todayStart, $todayEnd])
                ->orderBy('starts_at')
                ->get(),

            // Təsdiq gözləyənlər (auto_confirm söndürüləndə)
            'pending' => $visible()
                ->with(['customer:id,name', 'barber:id,name'])
                ->where('status', AppointmentStatus::Pending)
                ->where('starts_at', '>=', now())
                ->orderBy('starts_at')
                ->limit(10)
                ->get(),

            'barberStats' => $user->isManager() ? $this->barberStats($todayStart, $todayEnd, $monthStart, $monthEnd) : collect(),
        ];
    }

    /** Bərbərlər üzrə: bu gün, bu ay tamamlanmış, gəlir, gəlməyənlər. */
    public function barberStats(CarbonImmutable $todayStart, CarbonImmutable $todayEnd, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $period = fn ($q) => $q->where('starts_at', '>=', $from)->where('starts_at', '<', $to);

        return Barber::query()
            ->ordered()
            ->withCount([
                'appointments as today_count' => fn ($q) => $q->occupying()->overlapping($todayStart, $todayEnd),
                'appointments as completed_count' => fn ($q) => $period($q)->where('status', AppointmentStatus::Completed),
                'appointments as no_show_count' => fn ($q) => $period($q)->where('status', AppointmentStatus::NoShow),
            ])
            ->withSum(['appointments as revenue' => fn ($q) => $period($q)->where('status', AppointmentStatus::Completed)], 'service_price')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * Hesabat: tarix intervalı üzrə gəlir — ümumi, bərbər üzrə, xidmət üzrə + status sayları.
     */
    public function revenueReport(CarbonImmutable $from, CarbonImmutable $to, ?int $barberId = null): array
    {
        [$start, $end] = $this->periodRange($from, $to);

        $base = fn (): Builder => Appointment::query()
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<', $end)
            ->when($barberId, fn ($q) => $q->where('barber_id', $barberId));

        $completed = fn (): Builder => $base()->where('status', AppointmentStatus::Completed);

        $statusCounts = $base()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $total = (float) $completed()->sum('service_price');
        $completedCount = (int) ($statusCounts[AppointmentStatus::Completed->value] ?? 0);

        return [
            'total' => $total,
            'completedCount' => $completedCount,
            'averageTicket' => $completedCount ? $total / $completedCount : 0.0,
            // Gözlənilən: intervalda hələ baş tutmamış aktiv rezervasiyalar
            'expected' => (float) $base()->occupying()->sum('service_price'),
            'statusCounts' => collect(AppointmentStatus::cases())
                ->mapWithKeys(fn (AppointmentStatus $s) => [$s->value => (int) ($statusCounts[$s->value] ?? 0)]),

            'byBarber' => $completed()
                ->join('barbers', 'barbers.id', '=', 'appointments.barber_id')
                ->selectRaw('barbers.id, barbers.name, COUNT(*) as visits, SUM(appointments.service_price) as revenue')
                ->groupBy('barbers.id', 'barbers.name')
                ->orderByDesc('revenue')
                ->get()
                ->toBase(),

            // Snapshot adına görə qruplanır: xidmət sonradan silinsə və ya adı dəyişsə belə tarixçə düzgün qalır
            'byService' => $completed()
                ->selectRaw('service_name, COUNT(*) as visits, SUM(service_price) as revenue')
                ->groupBy('service_name')
                ->orderByDesc('revenue')
                ->get()
                ->toBase(),
        ];
    }
}
