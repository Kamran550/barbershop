<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Models\BusinessSetting;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Bu bərbər, bu xidmət, bu tarix üçün hansı başlanğıc saatları boşdur?"
 *
 * Alqoritm (interval çıxma):
 *   1. Tarix keçmişdədirsə və ya üfüqdən (booking_horizon_days) uzaqdırsa → boş
 *   2. İş pəncərəsi: tarix istisnası varsa o, yoxsa həftəlik qrafik
 *   3. − fasilələr
 *   4. − bloklanmış vaxtlar (bərbərin + bütün berberxananın)
 *   5. − aktiv rezervasiyalar (hər iki tərəfdən buffer ilə genişləndirilmiş)
 *   6. Qalan boş intervallarda slot_interval addımı ilə namizədlər: [t, t+müddət) tam sığırmı?
 *   7. now + min_notice-dən tez olan namizədlər atılır
 *
 * Bütün müqayisələr mütləq zaman anları ilə aparılır (Carbon timezone-dan asılı olmadan müqayisə edir);
 * iş saatları berberxananın timezone-unda qurulur, DB-dən gələn UTC vaxtlar ilə düzgün müqayisə olunur.
 */
class AvailabilityService
{
    /**
     * Onlayn rezervasiya üçün boş başlanğıc saatları.
     *
     * @return list<CarbonImmutable> UTC
     */
    public function slots(Barber $barber, Service $service, string $date, ?int $ignoreAppointmentId = null): array
    {
        if (! $barber->is_active || ! $service->is_active || ! $this->offers($barber, $service)) {
            return [];
        }

        $settings = BusinessSetting::current();
        $day = $this->parseDay($date, $settings->timezone);

        if ($day === null || ! $this->withinBookingWindow($day, $settings)) {
            return [];
        }

        $window = $this->workingWindow($barber, $day, $settings->timezone);
        if ($window === null) {
            return [];
        }

        $free = $this->freeRanges($barber, $window, $day, $settings, $ignoreAppointmentId);
        $earliest = CarbonImmutable::now()->addMinutes($settings->min_notice_minutes);
        $duration = $service->duration_minutes;

        $slots = [];
        for ($t = $window->start; $t->addMinutes($duration) <= $window->end; $t = $t->addMinutes($settings->slot_interval_minutes)) {
            if ($t < $earliest) {
                continue;
            }

            $candidate = new TimeRange($t, $t->addMinutes($duration));
            if ($this->fits($candidate, $free)) {
                $slots[] = $t->utc();
            }
        }

        return $slots;
    }

    /**
     * "Fərq etməz" seçimi: eyni adlı xidməti göstərən bərbərlərdən ən azı birinin boş olduğu saatlar.
     * Hər bərbər öz xidmətinin müddəti ilə yoxlanır (Raufun saç kəsimi 30, Elvininki 40 dəq ola bilər).
     *
     * @return list<CarbonImmutable> UTC, sıralanmış
     */
    public function slotsForAnyBarber(Service $service, string $date): array
    {
        return $this->groupServices($service)
            ->flatMap(fn (Service $s) => $this->slots($s->barber, $s, $date))
            ->unique(fn (CarbonImmutable $t) => $t->getTimestamp())
            ->sortBy(fn (CarbonImmutable $t) => $t->getTimestamp())
            ->values()
            ->all();
    }

    /** Onlayn rezervasiya: vaxt slots() siyahısında olmalıdır (grid, notice, üfüq daxil). */
    public function isBookableSlot(Barber $barber, Service $service, CarbonImmutable $startsAt, ?int $ignoreAppointmentId = null): bool
    {
        $date = $startsAt->setTimezone(BusinessSetting::current()->timezone)->toDateString();

        return collect($this->slots($barber, $service, $date, $ignoreAppointmentId))
            ->contains(fn (CarbonImmutable $slot) => $slot->equalTo($startsAt));
    }

    /**
     * Menecerin əl ilə rezervasiyası / vaxt dəyişikliyi üçün daha yumşaq yoxlama:
     * grid, min_notice və üfüq tələb olunmur (məs. müştəri qapıdan gəlib), amma
     * keçmiş vaxt, iş saatı, fasilə, blok və digər rezervasiyalar YENƏ DƏ qadağandır.
     */
    public function isFreeForManualBooking(Barber $barber, CarbonImmutable $startsAt, int $durationMinutes, ?int $ignoreAppointmentId = null): bool
    {
        if ($startsAt->isPast()) {
            return false;
        }

        $settings = BusinessSetting::current();
        $local = $startsAt->setTimezone($settings->timezone);
        $day = $local->startOfDay();

        $window = $this->workingWindow($barber, $day, $settings->timezone);
        if ($window === null) {
            return false;
        }

        $free = $this->freeRanges($barber, $window, $day, $settings, $ignoreAppointmentId);

        return $this->fits(new TimeRange($local, $local->addMinutes($durationMinutes)), $free);
    }

    /**
     * "Fərq etməz" üçün namizədlər: $service ilə EYNİ ADLI, rezervasiya edilə bilən (aktiv xidmət + aktiv bərbər)
     * bütün xidmətlər, bərbəri ilə birlikdə. $day verilibsə, həmin gün ən az yüklü bərbərin xidməti əvvəl gəlir.
     *
     * Ad müqayisəsi MySQL-in utf8mb4_unicode_ci collation-u ilə aparılır: böyük/kiçik hərf fərqi nəzərə alınmır.
     *
     * @return Collection<int, Service>
     */
    public function groupServices(Service $service, ?CarbonImmutable $day = null): Collection
    {
        $services = Service::query()
            ->bookable()
            ->where('name', $service->name)
            ->with('barber')
            ->get();

        if (! $day) {
            return $services->sortBy(fn (Service $s) => [$s->barber->sort_order, $s->barber->name])->values();
        }

        $start = $day->setTimezone(BusinessSetting::current()->timezone)->startOfDay();
        $load = Appointment::query()
            ->occupying()
            ->overlapping($start->utc(), $start->addDay()->utc())
            ->whereIn('barber_id', $services->pluck('barber_id'))
            ->selectRaw('barber_id, COUNT(*) as aggregate')
            ->groupBy('barber_id')
            ->pluck('aggregate', 'barber_id');

        return $services
            ->sortBy(fn (Service $s) => [(int) ($load[$s->barber_id] ?? 0), $s->barber->sort_order, $s->barber->name])
            ->values();
    }

    /** Xidmət bərbərə məxsusdur: bərbər yalnız öz xidmətini göstərir. */
    public function offers(Barber $barber, Service $service): bool
    {
        return $service->barber_id === $barber->id;
    }

    // ── Daxili addımlar ─────────────────────────────────────────────

    /** Tarix URL-dən gəlir — səhv format exception yox, sadəcə "slot yoxdur" deməkdir. */
    private function parseDay(string $date, string $tz): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, $tz);
        } catch (\Throwable) {
            return null;
        }

        // 2030-02-30 → Carbon onu 2030-03-02-yə "düzəldir"; belə tarixləri rədd edirik
        return $day && $day->toDateString() === $date ? $day : null;
    }

    private function withinBookingWindow(CarbonImmutable $day, BusinessSetting $settings): bool
    {
        $today = CarbonImmutable::now($settings->timezone)->startOfDay();

        return $day >= $today && $day <= $today->addDays($settings->booking_horizon_days);
    }

    /**
     * Tarix istisnası həftəlik qrafikdən üstündür. null → həmin gün işləmir.
     * Public: calendar da eyni məntiqlə iş saatlarını göstərir (məntiq təkrarlanmır).
     */
    public function workingWindow(Barber $barber, CarbonImmutable $day, string $tz): ?TimeRange
    {
        $exception = $barber->availabilityExceptions()->where('date', $day->toDateString())->first();

        if ($exception) {
            return $exception->is_day_off ? null : $this->rangeOnDay($day, $exception->start_time, $exception->end_time, $tz);
        }

        $hours = $barber->workingHours()->where('weekday', $day->isoWeekday())->first();

        return $hours ? $this->rangeOnDay($day, $hours->start_time, $hours->end_time, $tz) : null;
    }

    /** @return list<TimeRange> iş pəncərəsinin boş hissələri */
    private function freeRanges(Barber $barber, TimeRange $window, CarbonImmutable $day, BusinessSetting $settings, ?int $ignoreAppointmentId): array
    {
        $tz = $settings->timezone;
        $buffer = $settings->buffer_minutes;

        $breaks = $barber->breaks()
            ->where('weekday', $day->isoWeekday())
            ->get(['start_time', 'end_time'])
            ->map(fn ($b) => $this->rangeOnDay($day, $b->start_time, $b->end_time, $tz));

        $blocks = BlockedPeriod::query()
            ->where(fn ($q) => $q->where('barber_id', $barber->id)->orWhereNull('barber_id'))
            ->where('starts_at', '<', $window->end->utc())
            ->where('ends_at', '>', $window->start->utc())
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($b) => new TimeRange($b->starts_at, $b->ends_at));

        // Rezervasiya hər iki tərəfdən buffer qədər genişlənir: yeni müştəri nə ondan dərhal əvvəl,
        // nə də dərhal sonra başlaya bilər. İş gününün sonunda isə buffer tələb olunmur.
        $appointments = Appointment::query()
            ->whereBelongsTo($barber)
            ->occupying()
            ->when($ignoreAppointmentId, fn ($q) => $q->whereKeyNot($ignoreAppointmentId))
            ->overlapping($window->start->subMinutes($buffer)->utc(), $window->end->addMinutes($buffer)->utc())
            ->get(['starts_at', 'ends_at'])
            ->map(fn ($a) => new TimeRange($a->starts_at->subMinutes($buffer), $a->ends_at->addMinutes($buffer)));

        return TimeRange::subtractAll([$window], $breaks->concat($blocks)->concat($appointments));
    }

    /** @param list<TimeRange> $free */
    private function fits(TimeRange $candidate, array $free): bool
    {
        foreach ($free as $range) {
            if ($range->contains($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function rangeOnDay(CarbonImmutable $day, string $start, string $end, string $tz): TimeRange
    {
        $date = $day->toDateString();

        return new TimeRange(
            CarbonImmutable::parse("$date $start", $tz),
            CarbonImmutable::parse("$date $end", $tz),
        );
    }
}
