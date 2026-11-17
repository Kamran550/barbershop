<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\BlockedPeriod;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use BookingScenario;
    use RefreshDatabase;

    private BookingService $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScenario();
        $this->booking = app(BookingService::class);
    }

    private function book(string $bakuTime, $barber = 'rauf', string $phone = '050 123 45 67', string $name = 'Kamal', ?Service $service = null)
    {
        $barber = is_string($barber) ? $this->{$barber} : $barber;
        // Konkret bərbər → onun öz xidməti; "Fərq etməz" (null) → qrupun nümayəndəsi
        $service ??= $barber ? $this->haircutOf($barber) : $this->haircut;

        return $this->booking->book($service, $barber, $this->baku($bakuTime), $name, $phone);
    }

    // ── Uğurlu rezervasiya ───────────────────────────────────────────

    public function test_successful_booking_with_snapshot_customer_and_token(): void
    {
        $result = $this->book('2030-05-07 11:00');
        $a = $result->appointment->fresh();

        $this->assertSame(AppointmentStatus::Confirmed, $a->status);   // auto_confirm = true
        $this->assertSame(AppointmentSource::Online, $a->source);
        $this->assertSame('2030-05-07 07:00:00', $a->starts_at->toDateTimeString()); // UTC
        $this->assertSame('2030-05-07 07:30:00', $a->ends_at->toDateTimeString());
        $this->assertSame(['Saç kəsimi', '15.00', 30], [$a->service_name, $a->service_price, $a->service_duration]);

        $this->assertSame('+994501234567', $a->customer->phone);
        $this->assertSame('Kamal', $a->customer->name);

        $this->assertSame(48, strlen($result->token));
        $this->assertNotSame($result->token, $a->token_hash, 'Token özü DB-də saxlanmır');
        $this->assertTrue($this->booking->findByToken($result->token)->is($a));
        $this->assertNull($this->booking->findByToken(str_repeat('x', 48)));

        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.created', 'auditable_id' => $a->id, 'user_id' => null]);
    }

    public function test_manual_confirmation_mode_creates_pending(): void
    {
        $this->updateSettings(['auto_confirm' => false]);

        $this->assertSame(AppointmentStatus::Pending, $this->book('2030-05-07 11:00')->appointment->status);
    }

    // ── Eyni telefon ─────────────────────────────────────────────────

    public function test_same_phone_reuses_customer_in_any_format(): void
    {
        $this->book('2030-05-07 11:00', phone: '050 123 45 67', name: 'Kamal');
        $this->book('2030-05-08 11:00', phone: '+994501234567', name: 'Kamal Əliyev');

        $this->assertSame(1, Customer::count());
        $this->assertSame('Kamal Əliyev', Customer::sole()->name);
    }

    public function test_same_phone_cannot_book_overlapping_time_even_with_other_barber(): void
    {
        $this->book('2030-05-07 11:00', 'rauf');

        $this->expectExceptionObject(new BusinessRuleException('Bu vaxtda artıq rezervasiyanız var.'));
        $this->book('2030-05-07 11:15', 'elvin');
    }

    public function test_same_phone_active_booking_limit(): void
    {
        $this->book('2030-05-07 11:00');
        $this->book('2030-05-08 11:00');
        $this->book('2030-05-09 11:00');

        try {
            $this->book('2030-05-10 11:00');
            $this->fail('4-cü rezervasiya qəbul edilməməli idi');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('3 aktiv rezervasiya', $e->getMessage());
        }

        // Birini ləğv etdikdən sonra yenidən mümkündür
        Appointment::first()->update(['status' => AppointmentStatus::Cancelled]);
        $this->book('2030-05-10 11:00');
        $this->assertSame(4, Appointment::count());
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->expectException(BusinessRuleException::class);
        $this->book('2030-05-07 11:00', phone: '12345');
    }

    // ── Vaxt qaydaları ───────────────────────────────────────────────

    public function test_past_time_is_rejected(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-06 09:00');
    }

    public function test_time_within_min_notice_is_rejected(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-06 10:30'); // indi 10:00, notice 60 dəq
    }

    public function test_outside_working_hours_is_rejected(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-07 09:00');
    }

    public function test_day_off_is_rejected(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-12 11:00'); // Bazar
    }

    public function test_break_time_is_rejected(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-07 14:00');
    }

    public function test_blocked_time_is_rejected(): void
    {
        BlockedPeriod::create(['barber_id' => null, 'starts_at' => $this->baku('2030-05-07 16:00')->utc(), 'ends_at' => $this->baku('2030-05-07 18:00')->utc()]);

        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-07 16:30');
    }

    public function test_service_must_fit_before_closing_and_before_break(): void
    {
        $color = Service::factory()->for($this->rauf)->create(['duration_minutes' => 60]);

        $this->book('2030-05-07 19:00', service: $color, phone: '0501111111');  // 19:00–20:00 — tam sığır

        foreach (['2030-05-07 13:30', '2030-05-07 19:15'] as $time) {
            try {
                $this->book($time, service: $color, phone: '0502222222');
                $this->fail("$time qəbul edilməməli idi");
            } catch (SlotUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_off_grid_time_is_rejected_online(): void
    {
        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-07 11:07');
    }

    // ── Double booking ───────────────────────────────────────────────

    public function test_same_barber_same_time_cannot_be_double_booked(): void
    {
        $this->book('2030-05-07 11:00', phone: '0501111111');

        try {
            $this->book('2030-05-07 11:00', phone: '0502222222');
            $this->fail('Double booking!');
        } catch (SlotUnavailableException $e) {
            $this->assertSame('Bu vaxt artıq boş deyil. Zəhmət olmasa başqa saat seçin.', $e->getMessage());
        }

        $this->assertSame(1, Appointment::count());
    }

    public function test_partial_overlap_and_buffer_are_rejected(): void
    {
        $this->book('2030-05-07 11:00', phone: '0501111111'); // 11:00–11:30, buffer → 11:35

        foreach (['2030-05-07 10:45', '2030-05-07 11:15', '2030-05-07 11:30'] as $time) {
            try {
                $this->book($time, phone: '0502222222');
                $this->fail("$time qəbul edilməməli idi");
            } catch (SlotUnavailableException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->book('2030-05-07 11:45', phone: '0502222222'); // buffer-dən sonra — olar
        $this->assertSame(2, Appointment::count());
    }

    public function test_different_barbers_can_take_same_time(): void
    {
        $this->book('2030-05-07 11:00', 'rauf', phone: '0501111111');
        $this->book('2030-05-07 11:00', 'elvin', phone: '0502222222');

        $this->assertSame(2, Appointment::count());
    }

    public function test_booking_locks_barber_row(): void
    {
        DB::enableQueryLog();
        $this->book('2030-05-07 11:00');

        $sql = collect(DB::getQueryLog())->pluck('query');
        $lockIndex = $sql->search(fn ($q) => str_contains($q, 'from `barbers`') && str_contains($q, 'for update'));

        $this->assertNotFalse($lockIndex, 'Bərbər sətri FOR UPDATE ilə kilidlənməlidir');
        $insertIndex = $sql->search(fn ($q) => str_starts_with($q, 'insert into `appointments`'));
        $this->assertLessThan($insertIndex, $lockIndex, 'Lock insert-dən əvvəl olmalıdır');
    }

    // ── "Fərq etməz" ─────────────────────────────────────────────────

    public function test_any_barber_picks_free_one(): void
    {
        $this->book('2030-05-07 11:00', 'rauf', phone: '0501111111');

        $result = $this->book('2030-05-07 11:00', null, phone: '0502222222');

        $this->assertTrue($result->appointment->barber->is($this->elvin));
        // Snapshot seçilən ustanın ÖZ xidmətindən götürülür: Elvinin qiyməti 20 ₼
        $this->assertSame($this->elvinHaircut->id, $result->appointment->service_id);
        $this->assertSame('20.00', $result->appointment->fresh()->service_price);
    }

    public function test_any_barber_balances_load(): void
    {
        $this->book('2030-05-07 16:00', 'rauf', phone: '0501111111'); // Raufun həmin gün 1 rezervasiyası var

        $result = $this->book('2030-05-07 11:00', null, phone: '0502222222');

        $this->assertTrue($result->appointment->barber->is($this->elvin), 'Daha az yüklü bərbər seçilir');
    }

    public function test_any_barber_fails_when_everyone_is_busy(): void
    {
        $this->book('2030-05-07 11:00', 'rauf', phone: '0501111111');
        $this->book('2030-05-07 11:00', 'elvin', phone: '0502222222');

        $this->expectException(SlotUnavailableException::class);
        $this->book('2030-05-07 11:00', null, phone: '0503333333');
    }

    public function test_barber_not_offering_service_or_inactive(): void
    {
        try {
            // Elvinin xidməti Raufa yazılmağa çalışılır — xidmət bərbərə məxsusdur
            $this->book('2030-05-07 11:00', 'rauf', service: $this->elvinHaircut);
            $this->fail();
        } catch (BusinessRuleException $e) {
            $this->assertSame('Seçilmiş bərbər bu xidməti göstərmir.', $e->getMessage());
        }

        $this->rauf->update(['is_active' => false]);
        $this->expectException(BusinessRuleException::class);
        $this->book('2030-05-07 11:00');
    }

    // ── Əl ilə rezervasiya (menecer) ─────────────────────────────────

    public function test_manager_manual_booking_skips_notice_and_grid_but_not_conflicts(): void
    {
        $manager = User::factory()->manager()->create();
        $this->updateSettings(['auto_confirm' => false]);

        $manual = fn (string $time, string $phone) => $this->booking->book(
            $this->haircut, $this->rauf, $this->baku($time), 'Qonaq', $phone, AppointmentSource::Manual, $manager,
        );

        $a = $manual('2030-05-06 10:07', '0501111111')->appointment; // 7 dəq sonra, grid-dən kənar — olar
        $this->assertSame(AppointmentStatus::Confirmed, $a->status, 'Əl ilə yaradılan həmişə təsdiqlənib');
        $this->assertSame($manager->id, $a->created_by);

        $this->expectException(SlotUnavailableException::class);
        $manual('2030-05-06 10:20', '0502222222'); // birincinin üstünə düşür
    }

    // ── Ləğv ─────────────────────────────────────────────────────────

    public function test_customer_cancels_before_deadline_and_slot_becomes_free(): void
    {
        $a = $this->book('2030-05-07 11:00', phone: '0501111111')->appointment;

        $this->booking->cancel($a);

        $a->refresh();
        $this->assertSame(AppointmentStatus::Cancelled, $a->status);
        $this->assertNotNull($a->cancelled_at);
        $this->assertSame('Müştəri ləğv etdi', $a->cancellation_reason);

        $this->book('2030-05-07 11:00', phone: '0502222222'); // vaxt yenidən boşdur
        $this->assertSame(2, Appointment::count());
    }

    public function test_customer_cannot_cancel_after_deadline_but_staff_can(): void
    {
        $a = $this->book('2030-05-06 12:00')->appointment; // indi 10:00 → 2 saat qalıb

        $this->travel(5)->minutes();

        try {
            $this->booking->cancel($a);
            $this->fail('Deadline keçib');
        } catch (BusinessRuleException $e) {
            $this->assertStringContainsString('2 saatdan az', $e->getMessage());
        }

        $this->booking->cancel($a, User::factory()->manager()->create(), 'Telefonla ləğv etdi');
        $this->assertSame(AppointmentStatus::Cancelled, $a->fresh()->status);
    }

    public function test_cancelled_appointment_cannot_be_cancelled_again(): void
    {
        $a = $this->book('2030-05-07 11:00')->appointment;
        $this->booking->cancel($a);

        $this->expectException(BusinessRuleException::class);
        $this->booking->cancel($a);
    }

    // ── Status ───────────────────────────────────────────────────────

    public function test_status_transitions(): void
    {
        $manager = User::factory()->manager()->create();
        $this->updateSettings(['auto_confirm' => false]);
        $a = $this->book('2030-05-06 11:00')->appointment;

        $this->booking->changeStatus($a, AppointmentStatus::Confirmed, $manager);

        try {
            $this->booking->changeStatus($a, AppointmentStatus::Completed, $manager);
            $this->fail('Vaxt çatmayıb');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Rezervasiyanın vaxtı hələ çatmayıb.', $e->getMessage());
        }

        $this->travelTo($this->baku('2030-05-06 11:40'));
        $this->booking->changeStatus($a, AppointmentStatus::Completed, $manager);
        $this->assertSame(AppointmentStatus::Completed, $a->fresh()->status);

        $this->expectException(BusinessRuleException::class);
        $this->booking->changeStatus($a, AppointmentStatus::NoShow, $manager);
    }

    // ── Vaxt dəyişikliyi ─────────────────────────────────────────────

    public function test_reschedule_moves_and_frees_old_slot(): void
    {
        $manager = User::factory()->manager()->create();
        $a = $this->book('2030-05-07 11:00', phone: '0501111111')->appointment;

        $this->booking->reschedule($a, $this->baku('2030-05-07 16:00'), $this->elvin, $manager);

        $a->refresh();
        $this->assertTrue($a->barber->is($this->elvin));
        $this->assertSame('2030-05-07 12:00:00', $a->starts_at->toDateTimeString());
        $this->assertSame('2030-05-07 12:30:00', $a->ends_at->toDateTimeString());
        $this->assertDatabaseHas('audit_logs', ['action' => 'appointment.rescheduled']);

        $this->book('2030-05-07 11:00', 'rauf', phone: '0502222222'); // köhnə vaxt boşaldı
    }

    public function test_reschedule_to_barber_without_same_service_is_rejected_and_price_is_kept(): void
    {
        $manager = User::factory()->manager()->create();
        $tural = $this->makeBarber('Tural');
        Service::factory()->for($tural)->create(['name' => 'Saqqal']);
        $a = $this->book('2030-05-07 11:00')->appointment; // Rauf, 15 ₼

        try {
            $this->booking->reschedule($a, $this->baku('2030-05-07 16:00'), $tural, $manager);
            $this->fail('Turalın "Saç kəsimi" xidməti yoxdur');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Seçilmiş bərbər bu xidməti göstərmir.', $e->getMessage());
        }

        // Elvinə keçirəndə qiymət müştəri ilə razılaşdırılan kimi qalır (Elvinin 20 ₼-u deyil)
        $this->booking->reschedule($a, $this->baku('2030-05-07 16:00'), $this->elvin, $manager);
        $this->assertSame('15.00', $a->fresh()->service_price);
    }

    public function test_reschedule_within_own_time_is_allowed(): void
    {
        $manager = User::factory()->manager()->create();
        $a = $this->book('2030-05-07 11:00')->appointment;

        // 15 dəq sonraya: köhnə vaxtı ilə kəsişir, amma öz-özünə mane olmur
        $this->booking->reschedule($a, $this->baku('2030-05-07 11:15'), null, $manager);

        $this->assertSame('2030-05-07 07:15:00', $a->fresh()->starts_at->toDateTimeString());
    }

    public function test_reschedule_into_occupied_slot_is_rejected(): void
    {
        $manager = User::factory()->manager()->create();
        $this->book('2030-05-07 16:00', 'elvin', phone: '0501111111');
        $a = $this->book('2030-05-07 11:00', 'rauf', phone: '0502222222')->appointment;

        $this->expectException(SlotUnavailableException::class);
        $this->booking->reschedule($a, $this->baku('2030-05-07 16:00'), $this->elvin, $manager);
    }

    public function test_cancelled_appointment_cannot_be_rescheduled(): void
    {
        $manager = User::factory()->manager()->create();
        $a = $this->book('2030-05-07 11:00')->appointment;
        $this->booking->cancel($a, $manager);

        $this->expectException(BusinessRuleException::class);
        $this->booking->reschedule($a->fresh(), $this->baku('2030-05-07 12:00'), null, $manager);
    }
}
