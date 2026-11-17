<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Models\BusinessSetting;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\TimeRange;
use App\Services\ReportService;
use App\Support\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Calendar (server-rendered, kitabxanasız):
 *  - day:   sütunlar = bərbərlər (menecer hamısını, bərbər özünü görür)
 *  - week:  bir bərbərin 7 günü
 *  - month: günlər üzrə say və gəlir
 */
class CalendarController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly ReportService $reports,
    ) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $tz = BusinessSetting::timezone();

        $data = $request->validate([
            'view' => ['nullable', Rule::in(['day', 'week', 'month'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'barber_id' => ['nullable', 'integer'],
        ]);

        $view = $data['view'] ?? 'day';
        $date = isset($data['date']) ? CarbonImmutable::parse($data['date'], $tz) : CarbonImmutable::now($tz)->startOfDay();
        $barbers = $this->visibleBarbers($user);
        abort_if($barbers->isEmpty(), 403, 'Hesabınız bərbər profilinə bağlanmayıb.');

        // Menecer bir bərbəri seçə bilər; bərbər həmişə yalnız özünü görür
        $selected = $barbers->firstWhere('id', (int) ($data['barber_id'] ?? 0));

        $payload = match ($view) {
            'day' => $this->day($date, $selected ? collect([$selected]) : $barbers, $tz),
            'week' => $this->week($date, $selected ?? $barbers->first(), $tz),
            'month' => $this->month($date, $user, $selected),
        };

        return view('panel.calendar.index', [
            'view' => $view,
            'date' => $date,
            'barbers' => $barbers,
            'selected' => $selected,
            'isManager' => $user->isManager(),
            'tz' => $tz,
            'nav' => $this->navigation($view, $date),
            ...$payload,
        ]);
    }

    /** @return Collection<int, Barber> */
    private function visibleBarbers(User $user): Collection
    {
        return $user->isManager()
            ? Barber::query()->active()->ordered()->get(['id', 'name'])
            : Barber::query()->whereKey($user->barberId())->get(['id', 'name']);
    }

    private function day(CarbonImmutable $date, Collection $barbers, string $tz): array
    {
        [$from, $to] = $this->reports->dayRange($date);
        $appointments = $this->appointments($barbers->pluck('id'), $from, $to)->groupBy('barber_id');
        $blocks = $this->blocks($barbers->pluck('id'), $from, $to);

        $columns = $barbers->map(fn (Barber $b) => [
            'key' => $b->id,
            'title' => $b->name,
            'date' => $date,
            'window' => $this->availability->workingWindow($b, $date, $tz),
            'breaks' => $this->breaks($b, $date, $tz),
            'blocks' => $blocks->filter(fn ($bl) => $bl->barber_id === null || $bl->barber_id === $b->id)->values(),
            'appointments' => $appointments->get($b->id, collect()),
            'newLink' => ['barber_id' => $b->id, 'date' => $date->toDateString()],
        ]);

        return ['columns' => $columns, 'hours' => $this->hourRange($columns, $tz)];
    }

    private function week(CarbonImmutable $date, Barber $barber, string $tz): array
    {
        $start = $date->startOfWeek(CarbonImmutable::MONDAY);
        [$from] = $this->reports->dayRange($start);
        [, $to] = $this->reports->dayRange($start->addDays(6));

        $appointments = $this->appointments(collect([$barber->id]), $from, $to)
            ->groupBy(fn ($a) => $a->starts_at->setTimezone($tz)->toDateString());
        $blocks = $this->blocks(collect([$barber->id]), $from, $to);

        $columns = collect(range(0, 6))->map(function (int $i) use ($start, $barber, $tz, $appointments, $blocks) {
            $day = $start->addDays($i);
            [$dFrom, $dTo] = $this->reports->dayRange($day);

            return [
                'key' => $day->toDateString(),
                'title' => Weekday::SHORT[$day->isoWeekday()].' '.$day->locale('az')->translatedFormat('j M'),
                'date' => $day,
                'window' => $this->availability->workingWindow($barber, $day, $tz),
                'breaks' => $this->breaks($barber, $day, $tz),
                'blocks' => $blocks->filter(fn ($bl) => $bl->starts_at < $dTo && $bl->ends_at > $dFrom)->values(),
                'appointments' => $appointments->get($day->toDateString(), collect()),
                'newLink' => ['barber_id' => $barber->id, 'date' => $day->toDateString()],
            ];
        });

        return ['columns' => $columns, 'hours' => $this->hourRange($columns, $tz), 'weekBarber' => $barber];
    }

    private function month(CarbonImmutable $date, User $user, ?Barber $barber): array
    {
        $first = $date->startOfMonth();
        $gridStart = $first->startOfWeek(CarbonImmutable::MONDAY);
        $gridEnd = $first->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        [$from] = $this->reports->dayRange($gridStart);
        [, $to] = $this->reports->dayRange($gridEnd);
        $tz = $date->getTimezone();

        $rows = Appointment::query()
            ->visibleTo($user)
            ->when($barber, fn ($q) => $q->where('barber_id', $barber->id))
            ->startingWithin([$from, $to])
            ->where('status', '!=', AppointmentStatus::Cancelled)
            ->get(['starts_at', 'status', 'service_price'])
            ->groupBy(fn ($a) => $a->starts_at->setTimezone($tz)->toDateString());

        $days = collect();
        for ($d = $gridStart; $d <= $gridEnd; $d = $d->addDay()) {
            $items = $rows->get($d->toDateString(), collect());
            $days->push([
                'date' => $d,
                'inMonth' => $d->month === $first->month,
                'count' => $items->count(),
                'revenue' => (float) $items->where('status', AppointmentStatus::Completed)->sum('service_price'),
            ]);
        }

        return ['monthDays' => $days];
    }

    // ── Köməkçilər ──────────────────────────────────────────────────

    private function appointments(Collection $barberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Appointment::query()
            ->with('customer:id,name')
            ->whereIn('barber_id', $barberIds)
            ->where('status', '!=', AppointmentStatus::Cancelled) // ləğv edilmiş vaxtı tutmur — calendar-da göstərilmir
            ->overlapping($from, $to)
            ->orderBy('starts_at')
            ->get(['id', 'barber_id', 'customer_id', 'service_name', 'starts_at', 'ends_at', 'status']);
    }

    private function blocks(Collection $barberIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return BlockedPeriod::query()
            ->where(fn ($q) => $q->whereIn('barber_id', $barberIds)->orWhereNull('barber_id'))
            ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
            ->get(['barber_id', 'starts_at', 'ends_at', 'reason']);
    }

    /** @return list<TimeRange> */
    private function breaks(Barber $barber, CarbonImmutable $day, string $tz): array
    {
        return $barber->breaks()->where('weekday', $day->isoWeekday())->get(['start_time', 'end_time'])
            ->map(fn ($b) => new TimeRange(
                CarbonImmutable::parse($day->toDateString().' '.$b->start_time, $tz),
                CarbonImmutable::parse($day->toDateString().' '.$b->end_time, $tz),
            ))->all();
    }

    /** Göstəriləcək saat aralığı: iş saatları + kənarda qalan rezervasiyalar; default 09–21. */
    private function hourRange(Collection $columns, string $tz): array
    {
        $min = 9;
        $max = 21;
        $hasData = false;

        foreach ($columns as $c) {
            $ranges = collect($c['window'] ? [$c['window']] : [])
                ->concat($c['appointments']->map(fn ($a) => new TimeRange($a->starts_at, $a->ends_at)));

            foreach ($ranges as $r) {
                $start = $r->start->setTimezone($tz);
                $end = $r->end->setTimezone($tz);
                if (! $start->isSameDay($c['date'])) {
                    continue;
                }
                $s = (int) $start->format('G');
                $e = (int) ceil(($end->format('G') * 60 + (int) $end->format('i')) / 60);
                $min = $hasData ? min($min, $s) : $s;
                $max = $hasData ? max($max, $e) : $e;
                $hasData = true;
            }
        }

        return [max(0, $min), min(24, max($max, $min + 1))];
    }

    private function navigation(string $view, CarbonImmutable $date): array
    {
        $step = fn (int $dir) => match ($view) {
            'day' => $date->addDays($dir),
            'week' => $date->addWeeks($dir),
            'month' => $date->addMonthsNoOverflow($dir),
        };

        $label = match ($view) {
            'day' => Str::ucfirst($date->locale('az')->translatedFormat('l, j F Y')),
            'week' => $date->startOfWeek(CarbonImmutable::MONDAY)->locale('az')->translatedFormat('j M').' – '.
                      $date->endOfWeek(CarbonImmutable::SUNDAY)->locale('az')->translatedFormat('j M Y'),
            'month' => Str::ucfirst($date->locale('az')->translatedFormat('F Y')),
        };

        return ['prev' => $step(-1)->toDateString(), 'next' => $step(1)->toDateString(), 'label' => $label];
    }
}
