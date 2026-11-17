<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bərbərin vaxt qrafikinin idarəsi:
 *  - həftəlik iş saatları + fasilələr (təkrarlanan)
 *  - konkret tarix istisnaları (izin günü / xüsusi saatlar)
 *  - bloklanmış intervallar (bərbər və ya bütün berberxana)
 *
 * Qrafik dəyişəndə mövcud rezervasiyalar AVTOMATİK ləğv edilmir — menecerə xəbərdarlıq qaytarılır
 * ki, müştərilərlə özü əlaqə saxlasın. Müştərinin rezervasiyasını səssizcə ləğv etmək pis təcrübədir.
 */
class ScheduleService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<int, array{enabled: bool, start?: string, end?: string, breaks?: list<array{start: string, end: string}>}>  $days
     *                                                                                                                              Açar — weekday (1–7). Validation WeeklyScheduleRequest-dədir.
     */
    public function saveWeekly(Barber $barber, array $days): void
    {
        DB::transaction(function () use ($barber, $days) {
            $old = $this->weeklySnapshot($barber);

            // Ən sadə və etibarlı yol: köhnə qrafiki sil, yenisini yaz (bir transaction daxilində)
            $barber->workingHours()->delete();
            $barber->breaks()->delete();

            foreach ($days as $weekday => $day) {
                if (! ($day['enabled'] ?? false)) {
                    continue;
                }

                $barber->workingHours()->create(['weekday' => $weekday, 'start_time' => $day['start'], 'end_time' => $day['end']]);

                foreach ($day['breaks'] ?? [] as $break) {
                    $barber->breaks()->create(['weekday' => $weekday, 'start_time' => $break['start'], 'end_time' => $break['end']]);
                }
            }

            $this->audit->log('schedule.updated', $barber, $old, $this->weeklySnapshot($barber));
        });
    }

    /**
     * Eyni tarix üçün istisna varsa, əvəz olunur (unique barber_id + date).
     *
     * @return array{0: AvailabilityException, 1: int} istisna və təsirlənən aktiv rezervasiyaların sayı
     */
    public function saveException(Barber $barber, array $data): array
    {
        $exception = $barber->availabilityExceptions()->updateOrCreate(
            ['date' => $data['date']],
            [
                'is_day_off' => $data['is_day_off'],
                'start_time' => $data['is_day_off'] ? null : $data['start'],
                'end_time' => $data['is_day_off'] ? null : $data['end'],
                'reason' => $data['reason'] ?? null,
            ],
        );

        $this->audit->log('schedule.exception_saved', $barber, new: $exception->only(['date', 'is_day_off', 'start_time', 'end_time', 'reason']));

        return [$exception, $this->appointmentsOutsideException($barber, $exception)];
    }

    public function deleteException(AvailabilityException $exception): void
    {
        $this->audit->log('schedule.exception_deleted', $exception->barber, old: $exception->only(['date', 'is_day_off', 'start_time', 'end_time']));
        $exception->delete();
    }

    /**
     * Daxil edilən vaxt berberxananın saatı ilə (Asia/Baku) gəlir, DB-yə UTC kimi yazılır.
     *
     * @return array{0: BlockedPeriod, 1: int}
     */
    public function createBlock(?Barber $barber, string $startsAtLocal, string $endsAtLocal, ?string $reason): array
    {
        $tz = BusinessSetting::timezone();

        $block = BlockedPeriod::create([
            'barber_id' => $barber?->id,
            'starts_at' => CarbonImmutable::parse($startsAtLocal, $tz)->utc(),
            'ends_at' => CarbonImmutable::parse($endsAtLocal, $tz)->utc(),
            'reason' => $reason,
            'created_by' => Auth::id(),
        ]);

        $this->audit->log('schedule.block_created', $block, new: [
            'barber_id' => $block->barber_id,
            'starts_at' => $block->starts_at->toDateTimeString(),
            'ends_at' => $block->ends_at->toDateTimeString(),
            'reason' => $reason,
        ]);

        $conflicts = Appointment::query()
            ->occupying()
            ->overlapping($block->starts_at, $block->ends_at)
            ->when($barber, fn ($q) => $q->whereBelongsTo($barber))
            ->count();

        return [$block, $conflicts];
    }

    public function deleteBlock(BlockedPeriod $block): void
    {
        $this->audit->log('schedule.block_deleted', $block, old: $block->only(['barber_id', 'reason']));
        $block->delete();
    }

    /** İstisna günü yeni saatlardan kənarda qalan (və ya izin günü düşən) aktiv rezervasiyalar. */
    private function appointmentsOutsideException(Barber $barber, AvailabilityException $exception): int
    {
        $tz = BusinessSetting::timezone();
        $dayStart = CarbonImmutable::parse($exception->date->toDateString(), $tz);

        $appointments = Appointment::query()
            ->whereBelongsTo($barber)
            ->occupying()
            ->overlapping($dayStart->utc(), $dayStart->addDay()->utc())
            ->get(['id', 'starts_at', 'ends_at']);

        if ($exception->is_day_off) {
            return $appointments->count();
        }

        $open = CarbonImmutable::parse($exception->date->toDateString().' '.$exception->start_time, $tz);
        $close = CarbonImmutable::parse($exception->date->toDateString().' '.$exception->end_time, $tz);

        return $appointments->filter(fn (Appointment $a) => $a->starts_at < $open || $a->ends_at > $close)->count();
    }

    /** Audit üçün qrafikin oxunaqlı görüntüsü: [1 => '10:00-20:00 (14:00-15:00)', …] */
    private function weeklySnapshot(Barber $barber): array
    {
        $breaks = $barber->breaks()->get()->groupBy('weekday');

        return $barber->workingHours()->get()->mapWithKeys(fn ($h) => [
            $h->weekday => substr($h->start_time, 0, 5).'-'.substr($h->end_time, 0, 5)
                .($breaks->has($h->weekday)
                    ? ' ('.$breaks[$h->weekday]->map(fn ($b) => substr($b->start_time, 0, 5).'-'.substr($b->end_time, 0, 5))->implode(', ').')'
                    : ''),
        ])->all();
    }
}
