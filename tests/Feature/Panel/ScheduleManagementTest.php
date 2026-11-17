<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\BusinessSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessSettingSeeder::class); // timezone: Asia/Baku (UTC+4)
        $this->travelTo(CarbonImmutable::parse('2030-05-06 06:00:00', 'UTC')); // B.e., Bakı 10:00

        $this->manager = User::factory()->manager()->create();
        $this->barber = Barber::factory()->withUser()->create();
    }

    /** 7 günlük forma: B.e.–Cümə 10–19, nahar 13–14; Şənbə 11–16; Bazar istirahət. */
    private function week(array $overrides = []): array
    {
        $days = [];
        foreach (range(1, 7) as $wd) {
            $days[$wd] = match (true) {
                $wd <= 5 => ['enabled' => '1', 'start' => '10:00', 'end' => '19:00', 'breaks' => [['start' => '13:00', 'end' => '14:00']]],
                $wd === 6 => ['enabled' => '1', 'start' => '11:00', 'end' => '16:00'],
                default => ['enabled' => '0'],
            };
        }

        // Günün sahələri dayaz (shallow) əvəz olunur ki, 'breaks' => [] həqiqətən fasilələri silsin
        foreach ($overrides as $wd => $override) {
            $days[$wd] = array_replace($days[$wd], $override);
        }

        return ['days' => $days];
    }

    // ── Həftəlik qrafik ──────────────────────────────────────────────

    public function test_manager_saves_weekly_schedule_with_breaks(): void
    {
        $this->actingAs($this->manager)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week())
            ->assertRedirect(route('panel.barbers.schedule', $this->barber))
            ->assertSessionHasNoErrors();

        $this->assertSame([1, 2, 3, 4, 5, 6], $this->barber->workingHours()->pluck('weekday')->all());
        $this->assertSame(5, $this->barber->breaks()->count());
        $this->assertDatabaseHas('working_hours', ['barber_id' => $this->barber->id, 'weekday' => 6, 'start_time' => '11:00:00', 'end_time' => '16:00:00']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.updated']);
    }

    public function test_saving_replaces_previous_schedule(): void
    {
        $this->actingAs($this->manager)->put(route('panel.barbers.schedule.update', $this->barber), $this->week());
        $this->actingAs($this->manager)->put(route('panel.barbers.schedule.update', $this->barber), $this->week([
            1 => ['enabled' => '0'], 2 => ['breaks' => []],
        ]));

        $this->assertFalse($this->barber->workingHours()->where('weekday', 1)->exists());
        $this->assertSame(0, $this->barber->breaks()->where('weekday', 2)->count());
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week([3 => ['start' => '18:00', 'end' => '09:00']]))
            ->assertSessionHasErrors('days.3.end');

        $this->assertSame(0, $this->barber->workingHours()->count(), 'Xəta olduqda heç nə yazılmır');
    }

    public function test_break_outside_working_hours_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week([1 => ['breaks' => [['start' => '09:00', 'end' => '10:30']]]]))
            ->assertSessionHasErrors('days.1.breaks');
    }

    public function test_overlapping_breaks_are_rejected(): void
    {
        $this->actingAs($this->manager)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week([2 => ['breaks' => [
                ['start' => '13:00', 'end' => '14:00'],
                ['start' => '13:30', 'end' => '14:30'],
            ]]]))
            ->assertSessionHasErrors('days.2.breaks');
    }

    public function test_invalid_time_format_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week([4 => ['start' => '25:99']]))
            ->assertSessionHasErrors('days.4.start');
    }

    public function test_barber_manages_own_schedule_breaks_and_exceptions(): void
    {
        $user = $this->barber->user;

        $this->actingAs($user)
            ->put(route('panel.barbers.schedule.update', $this->barber), $this->week())
            ->assertRedirect(route('panel.barbers.schedule', $this->barber))
            ->assertSessionHasNoErrors();
        $this->assertSame(6, $this->barber->workingHours()->count());
        $this->assertSame(5, $this->barber->breaks()->count());

        $this->actingAs($user)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-09', 'is_day_off' => '1', 'reason' => 'Həkim'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('availability_exceptions', ['barber_id' => $this->barber->id, 'reason' => 'Həkim']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'schedule.updated', 'user_id' => $user->id]);
    }

    public function test_barber_cannot_touch_another_barbers_schedule(): void
    {
        $other = Barber::factory()->withUser()->create();
        $foreignException = $other->availabilityExceptions()->create(['date' => '2030-05-10', 'is_day_off' => true]);

        $as = $this->actingAs($this->barber->user);
        $as->get(route('panel.barbers.schedule', $other))->assertForbidden();
        $as->put(route('panel.barbers.schedule.update', $other), $this->week())->assertForbidden();
        $as->post(route('panel.barbers.exceptions.store', $other), ['date' => '2030-05-09', 'is_day_off' => '1'])->assertForbidden();
        $as->delete(route('panel.barbers.exceptions.destroy', [$other, $foreignException]))->assertForbidden();

        $this->assertSame(0, $other->workingHours()->count());
        $this->assertModelExists($foreignException);
    }

    public function test_schedule_page_renders(): void
    {
        $this->actingAs($this->manager)->put(route('panel.barbers.schedule.update', $this->barber), $this->week());

        $this->actingAs($this->manager)->get(route('panel.barbers.schedule', $this->barber))
            ->assertOk()
            ->assertSee('Həftəlik qrafik')
            ->assertSee('Tarix istisnaları')
            // Günlər Alpine üçün JSON kimi ötürülür — DB-dəki qrafik formaya düşür
            ->assertViewHas('days', fn (array $days) => $days[0]['enabled'] && $days[0]['start'] === '10:00' && ! $days[6]['enabled']);
    }

    // ── Tarix istisnaları ────────────────────────────────────────────

    public function test_day_off_exception_is_saved_and_reports_conflicts(): void
    {
        // 2030-05-08 Bakı 12:00 = 08:00 UTC
        Appointment::factory()->for($this->barber)->at(CarbonImmutable::parse('2030-05-08 08:00:00', 'UTC'))->create();

        $this->actingAs($this->manager)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-08', 'is_day_off' => '1', 'reason' => 'Məzuniyyət'])
            ->assertSessionHas('status')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('availability_exceptions', ['barber_id' => $this->barber->id, 'is_day_off' => true]);
    }

    public function test_custom_hours_exception_replaces_existing_one_for_same_date(): void
    {
        $this->actingAs($this->manager)->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-09', 'is_day_off' => '1']);
        $this->actingAs($this->manager)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-09', 'is_day_off' => '0', 'start' => '12:00', 'end' => '16:00'])
            ->assertSessionMissing('warning');

        $exception = AvailabilityException::sole();
        $this->assertFalse($exception->is_day_off);
        $this->assertSame('12:00:00', $exception->fresh()->start_time);
    }

    public function test_custom_hours_conflict_counts_only_appointments_outside_new_hours(): void
    {
        $day = '2030-05-09';
        // Bakı 11:00 (yeni saatlardan kənar) və 13:00 (daxil)
        Appointment::factory()->for($this->barber)->at(CarbonImmutable::parse("$day 07:00:00", 'UTC'))->create();
        Appointment::factory()->for($this->barber)->at(CarbonImmutable::parse("$day 09:00:00", 'UTC'))->create();
        // Ləğv edilmiş — sayılmır
        Appointment::factory()->for($this->barber)->at(CarbonImmutable::parse("$day 06:00:00", 'UTC'))->status(AppointmentStatus::Cancelled)->create();

        $this->actingAs($this->manager)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => $day, 'is_day_off' => '0', 'start' => '12:00', 'end' => '18:00'])
            ->assertSessionHas('warning', fn ($msg) => str_contains($msg, '1 aktiv rezervasiya'));
    }

    public function test_exception_in_the_past_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-05', 'is_day_off' => '1'])
            ->assertSessionHasErrors(['date' => 'Keçmiş tarix üçün istisna əlavə etmək olmaz.']);
    }

    public function test_custom_hours_require_valid_range(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.barbers.exceptions.store', $this->barber), ['date' => '2030-05-09', 'is_day_off' => '0', 'start' => '16:00', 'end' => '12:00'])
            ->assertSessionHasErrors('end');
    }

    public function test_exception_of_another_barber_cannot_be_deleted_via_url(): void
    {
        $other = Barber::factory()->create();
        $foreign = $other->availabilityExceptions()->create(['date' => '2030-05-10', 'is_day_off' => true]);

        $this->actingAs($this->manager)
            ->delete(route('panel.barbers.exceptions.destroy', [$this->barber, $foreign]))
            ->assertNotFound();

        $this->assertModelExists($foreign);
    }

    // ── Bloklanmış vaxtlar ───────────────────────────────────────────

    public function test_block_is_stored_in_utc_from_local_input(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.blocked-periods.store'), [
                'barber_id' => $this->barber->id,
                'starts_at' => '2030-05-07T15:00',
                'ends_at' => '2030-05-07T16:30',
                'reason' => 'Həkim',
            ])
            ->assertRedirect(route('panel.blocked-periods.index'))
            ->assertSessionHasNoErrors();

        $block = BlockedPeriod::sole();
        $this->assertSame('2030-05-07 11:00:00', $block->starts_at->toDateTimeString()); // 15:00 Bakı = 11:00 UTC
        $this->assertSame('2030-05-07 12:30:00', $block->ends_at->toDateTimeString());
        $this->assertSame($this->manager->id, $block->created_by);
    }

    public function test_shop_wide_block_warns_about_all_overlapping_appointments(): void
    {
        $other = Barber::factory()->create();
        $at = CarbonImmutable::parse('2030-05-07 08:00:00', 'UTC'); // Bakı 12:00
        Appointment::factory()->for($this->barber)->at($at)->create();
        Appointment::factory()->for($other)->at($at)->create();

        $this->actingAs($this->manager)
            ->post(route('panel.blocked-periods.store'), ['barber_id' => '', 'starts_at' => '2030-05-07T11:00', 'ends_at' => '2030-05-07T14:00'])
            ->assertSessionHas('warning', fn ($msg) => str_contains($msg, '2 aktiv rezervasiya'));

        $this->assertNull(BlockedPeriod::sole()->barber_id);
    }

    public function test_block_validation(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.blocked-periods.store'), ['starts_at' => '2030-05-07T16:00', 'ends_at' => '2030-05-07T15:00'])
            ->assertSessionHasErrors('ends_at');

        $this->actingAs($this->manager)
            ->post(route('panel.blocked-periods.store'), ['starts_at' => '2030-05-01T10:00', 'ends_at' => '2030-05-01T12:00'])
            ->assertSessionHasErrors(['ends_at' => 'Tamamilə keçmişdə qalan vaxtı bloklamaq olmaz.']);

        $this->assertDatabaseCount('blocked_periods', 0);
    }

    public function test_blocks_page_lists_upcoming_blocks_in_local_time(): void
    {
        BlockedPeriod::create(['barber_id' => null, 'starts_at' => '2030-05-07 11:00:00', 'ends_at' => '2030-05-07 12:00:00', 'reason' => 'Təmir']);

        $this->actingAs($this->manager)->get(route('panel.blocked-periods.index'))
            ->assertOk()
            ->assertSee('Bütün berberxana')
            ->assertSee('07.05.2030 15:00');
    }

    // ── Bərbərin öz qrafiki ──────────────────────────────────────────

    public function test_my_schedule_shortcut_opens_own_editable_schedule(): void
    {
        $this->actingAs($this->barber->user)->get(route('panel.my-schedule'))
            ->assertRedirect(route('panel.barbers.schedule', $this->barber));

        $this->actingAs($this->barber->user)->get(route('panel.barbers.schedule', $this->barber))
            ->assertOk()
            ->assertSee('Qrafikim')
            ->assertSee('Yadda saxla');
    }

    public function test_manager_has_no_my_schedule_page(): void
    {
        $this->actingAs($this->manager)->get(route('panel.my-schedule'))->assertForbidden();
    }

    public function test_barber_menu_shows_self_service_items_but_not_team_management(): void
    {
        $this->actingAs($this->barber->user)->get(route('panel.dashboard'))
            ->assertSee('Xidmətlərim')
            ->assertSee('Qrafikim')
            ->assertSee('Bloklanmış vaxtlar')
            ->assertSee('Profilim')
            ->assertDontSee(route('panel.barbers.index'));
    }

    // ── Bərbərin öz blokları ─────────────────────────────────────────

    public function test_barber_blocks_own_time_only(): void
    {
        $other = Barber::factory()->create();

        // barber_id göndərsə belə — blok həmişə özünə yazılır
        $this->actingAs($this->barber->user)
            ->post(route('panel.blocked-periods.store'), [
                'barber_id' => $other->id,
                'starts_at' => '2030-05-07T15:00',
                'ends_at' => '2030-05-07T16:00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->barber->id, BlockedPeriod::sole()->barber_id);
    }

    public function test_barber_cannot_block_whole_shop_or_delete_others_blocks(): void
    {
        $shopWide = BlockedPeriod::create(['barber_id' => null, 'starts_at' => '2030-05-07 11:00:00', 'ends_at' => '2030-05-07 12:00:00']);
        $foreign = BlockedPeriod::create(['barber_id' => Barber::factory()->create()->id, 'starts_at' => '2030-05-07 11:00:00', 'ends_at' => '2030-05-07 12:00:00']);

        $as = $this->actingAs($this->barber->user);
        $as->delete(route('panel.blocked-periods.destroy', $shopWide))->assertForbidden();
        $as->delete(route('panel.blocked-periods.destroy', $foreign))->assertForbidden();

        $this->assertModelExists($shopWide);
        $this->assertModelExists($foreign);
    }

    public function test_barber_sees_own_and_shop_wide_blocks_but_not_others(): void
    {
        $other = Barber::factory()->create(['name' => 'Başqa Usta']);
        BlockedPeriod::create(['barber_id' => $this->barber->id, 'starts_at' => '2030-05-07 11:00:00', 'ends_at' => '2030-05-07 12:00:00', 'reason' => 'Mənim blokum']);
        BlockedPeriod::create(['barber_id' => null, 'starts_at' => '2030-05-08 11:00:00', 'ends_at' => '2030-05-08 12:00:00', 'reason' => 'Təmir']);
        BlockedPeriod::create(['barber_id' => $other->id, 'starts_at' => '2030-05-09 11:00:00', 'ends_at' => '2030-05-09 12:00:00', 'reason' => 'Gizli səbəb']);

        $this->actingAs($this->barber->user)->get(route('panel.blocked-periods.index'))
            ->assertOk()
            ->assertSee('Mənim blokum')
            ->assertSee('Təmir')
            ->assertDontSee('Gizli səbəb')
            ->assertDontSee('Kim üçün'); // bərbər seçimi yoxdur
    }
}
