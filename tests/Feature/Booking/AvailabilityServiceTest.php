<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\BlockedPeriod;
use App\Models\Service;
use App\Services\Booking\AvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use BookingScenario;
    use RefreshDatabase;

    private AvailabilityService $availability;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScenario();
        $this->availability = app(AvailabilityService::class);
    }

    private function slots(string $date = self::TUESDAY, ?Service $service = null, $barber = null): array
    {
        $barber ??= $this->rauf;

        return $this->times($this->availability->slots($barber, $service ?? $this->haircutOf($barber), $date));
    }

    public function test_full_working_day_respects_hours_break_and_duration(): void
    {
        $slots = $this->slots();

        $this->assertSame('10:00', $slots[0]);
        $this->assertContains('13:30', $slots);            // 13:30–14:00 fasiləyə qədər tam sığır
        $this->assertNotContains('13:45', $slots);         // 13:45–14:15 fasiləyə düşür
        $this->assertNotContains('14:30', $slots);         // fasilə
        $this->assertContains('15:00', $slots);
        $this->assertSame('19:30', end($slots));          // 19:30–20:00 — son slot
        $this->assertCount(15 + 19, $slots);
    }

    public function test_slots_are_returned_in_utc(): void
    {
        $first = $this->availability->slots($this->rauf, $this->haircut, self::TUESDAY)[0];

        $this->assertSame('2030-05-07 06:00:00', $first->toDateTimeString()); // 10:00 Bakı
        $this->assertSame('UTC', $first->getTimezone()->getName());
    }

    public function test_today_respects_min_notice(): void
    {
        // İndi 10:00, min notice 60 dəq → ilk slot 11:00
        $this->assertSame('11:00', $this->slots('2030-05-06')[0]);
    }

    public function test_past_date_and_beyond_horizon_return_nothing(): void
    {
        $this->assertSame([], $this->slots('2030-05-05'));     // dünən
        $this->assertSame([], $this->slots('2030-06-06'));     // 31 gün sonra
        $this->assertNotEmpty($this->slots('2030-06-05'));     // 30 gün sonra (Çərşənbə)
    }

    public function test_invalid_date_returns_nothing(): void
    {
        $this->assertSame([], $this->slots('2030-02-30'));
        $this->assertSame([], $this->slots('sabah'));
    }

    public function test_day_without_working_hours_returns_nothing(): void
    {
        $this->assertSame([], $this->slots('2030-05-12')); // Bazar
    }

    public function test_day_off_exception_overrides_weekly_schedule(): void
    {
        $this->rauf->availabilityExceptions()->create(['date' => self::TUESDAY, 'is_day_off' => true]);

        $this->assertSame([], $this->slots());
        $this->assertNotEmpty($this->slots(barber: $this->elvin), 'Digər bərbər təsirlənmir');
    }

    public function test_custom_hours_exception(): void
    {
        $this->rauf->availabilityExceptions()->create(['date' => self::TUESDAY, 'is_day_off' => false, 'start_time' => '12:00', 'end_time' => '16:00']);

        $slots = $this->slots();
        $this->assertSame('12:00', $slots[0]);
        $this->assertSame('15:30', end($slots));
        $this->assertNotContains('14:00', $slots); // fasilə yenə də tətbiq olunur
    }

    public function test_custom_hours_on_normal_day_off(): void
    {
        $this->rauf->availabilityExceptions()->create(['date' => '2030-05-12', 'is_day_off' => false, 'start_time' => '11:00', 'end_time' => '13:00']);

        $this->assertSame(['11:00', '11:15', '11:30', '11:45', '12:00', '12:15', '12:30'], $this->slots('2030-05-12'));
    }

    public function test_barber_block_removes_slots(): void
    {
        BlockedPeriod::create([
            'barber_id' => $this->rauf->id,
            'starts_at' => $this->baku('2030-05-07 11:00')->utc(),
            'ends_at' => $this->baku('2030-05-07 12:00')->utc(),
        ]);

        $slots = $this->slots();
        $this->assertContains('10:30', $slots);    // 10:30–11:00 bitişikdir
        $this->assertNotContains('10:45', $slots);
        $this->assertNotContains('11:30', $slots);
        $this->assertContains('12:00', $slots);
        $this->assertContains('11:00', $this->slots(barber: $this->elvin));
    }

    public function test_shop_wide_block_affects_every_barber(): void
    {
        BlockedPeriod::create([
            'barber_id' => null,
            'starts_at' => $this->baku('2030-05-07 10:00')->utc(),
            'ends_at' => $this->baku('2030-05-07 13:00')->utc(),
        ]);

        $this->assertSame('13:00', $this->slots()[0]);
        $this->assertSame('13:00', $this->slots(barber: $this->elvin)[0]);
    }

    public function test_existing_appointment_blocks_its_time_plus_buffer(): void
    {
        // 11:00–11:30 + buffer 5 → [10:55, 11:35) tutulub
        Appointment::factory()->for($this->rauf)->at($this->baku('2030-05-07 11:00')->utc(), 30)->create();

        $slots = $this->slots();
        $this->assertContains('10:15', $slots);      // 10:15–10:45, 10 dəq boşluq
        $this->assertNotContains('10:30', $slots);   // 10:30–11:00 — buffer-ə düşür
        $this->assertNotContains('11:00', $slots);
        $this->assertNotContains('11:30', $slots);   // 11:35-dən əvvəl
        $this->assertContains('11:45', $slots);
    }

    public function test_cancelled_and_completed_appointments_do_not_block(): void
    {
        Appointment::factory()->for($this->rauf)->at($this->baku('2030-05-07 11:00')->utc())->status(AppointmentStatus::Cancelled)->create();

        $this->assertContains('11:00', $this->slots());
    }

    public function test_pending_appointment_blocks_slot(): void
    {
        Appointment::factory()->for($this->rauf)->at($this->baku('2030-05-07 11:00')->utc())->status(AppointmentStatus::Pending)->create();

        $this->assertNotContains('11:00', $this->slots());
    }

    public function test_other_barbers_appointments_do_not_affect(): void
    {
        Appointment::factory()->for($this->elvin)->at($this->baku('2030-05-07 11:00')->utc())->create();

        $this->assertContains('11:00', $this->slots());
    }

    public function test_long_service_must_fit_entirely(): void
    {
        $color = Service::factory()->for($this->rauf)->create(['duration_minutes' => 90]);

        $slots = $this->slots(service: $color);
        $this->assertContains('12:30', $slots);       // 12:30–14:00
        $this->assertNotContains('12:45', $slots);    // fasiləyə düşür
        $this->assertSame('18:30', end($slots));      // 18:30–20:00
    }

    public function test_service_not_offered_or_inactive_returns_nothing(): void
    {
        // Elvinin xidməti Rauf üçün sorğulanır — xidmət bərbərə məxsusdur
        $this->assertSame([], $this->slots(service: $this->elvinHaircut, barber: $this->rauf));

        $this->haircut->update(['is_active' => false]);
        $this->assertSame([], $this->slots());

        $this->haircut->update(['is_active' => true]);
        $this->rauf->update(['is_active' => false]);
        $this->assertSame([], $this->slots());
    }

    public function test_any_barber_union(): void
    {
        $at = $this->baku('2030-05-07 10:00')->utc();
        Appointment::factory()->for($this->rauf)->at($at)->create();

        $any = $this->times($this->availability->slotsForAnyBarber($this->haircut, self::TUESDAY));
        $this->assertContains('10:00', $any, 'Elvin boşdur');
        $this->assertSame(count(array_unique($any)), count($any), 'Dublikat yoxdur');

        Appointment::factory()->for($this->elvin)->at($at)->create();
        $any = $this->times($this->availability->slotsForAnyBarber($this->haircut, self::TUESDAY));
        $this->assertNotContains('10:00', $any, 'Hər ikisi məşğuldur');
    }

    public function test_any_barber_groups_by_service_name_only(): void
    {
        // Elvinin "Saqqal" xidməti "Saç kəsimi" qrupuna düşməməlidir
        Service::factory()->for($this->elvin)->create(['name' => 'Saqqal', 'duration_minutes' => 20]);

        $group = $this->availability->groupServices($this->haircut);

        $this->assertEqualsCanonicalizing([$this->haircut->id, $this->elvinHaircut->id], $group->pluck('id')->all());
    }

    public function test_any_barber_skips_inactive_services_and_barbers(): void
    {
        $this->elvinHaircut->update(['is_active' => false]);
        $this->assertSame([$this->haircut->id], $this->availability->groupServices($this->haircut)->pluck('id')->all());

        $this->elvinHaircut->update(['is_active' => true]);
        $this->elvin->update(['is_active' => false]);
        $this->assertSame([$this->haircut->id], $this->availability->groupServices($this->haircut)->pluck('id')->all());
    }
}
