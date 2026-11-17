<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus as S;
use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use PanelScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
    }

    // ── Siyahı və IDOR ───────────────────────────────────────────────

    public function test_manager_sees_all_barber_sees_only_own(): void
    {
        $this->appointment($this->rauf, '2030-05-07 11:00', customer: Customer::factory()->create(['name' => 'Raufun müştərisi']));
        $this->appointment($this->elvin, '2030-05-07 11:00', customer: Customer::factory()->create(['name' => 'Elvinin müştərisi']));

        $this->actingAs($this->manager)->get(route('panel.appointments.index'))
            ->assertOk()->assertSee('Raufun müştərisi')->assertSee('Elvinin müştərisi');

        $this->actingAs($this->userOf($this->rauf))->get(route('panel.appointments.index'))
            ->assertOk()->assertSee('Raufun müştərisi')->assertDontSee('Elvinin müştərisi');
    }

    public function test_barber_filter_cannot_reveal_other_barbers_data(): void
    {
        $this->appointment($this->elvin, '2030-05-07 11:00', customer: Customer::factory()->create(['name' => 'Elvinin müştərisi']));

        $this->actingAs($this->userOf($this->rauf))
            ->get(route('panel.appointments.index', ['barber_id' => $this->elvin->id]))
            ->assertDontSee('Elvinin müştərisi');
    }

    public function test_barber_cannot_open_another_barbers_appointment(): void
    {
        $elvins = $this->appointment($this->elvin, '2030-05-07 11:00');

        $this->actingAs($this->userOf($this->rauf))->get(route('panel.appointments.show', $elvins))->assertForbidden();
        $this->actingAs($this->userOf($this->rauf))->patch(route('panel.appointments.status', $elvins), ['status' => 'completed'])->assertForbidden();
        $this->actingAs($this->userOf($this->rauf))->post(route('panel.appointments.cancel', $elvins))->assertForbidden();

        $this->assertSame(S::Confirmed, $elvins->fresh()->status);
    }

    public function test_filters_by_status_period_and_phone_search(): void
    {
        $kamal = Customer::factory()->create(['name' => 'Kamal', 'phone' => '+994501234567']);
        $this->appointment($this->rauf, '2030-05-07 11:00', customer: $kamal);
        $this->appointment($this->rauf, '2030-05-08 11:00', S::Cancelled, Customer::factory()->create(['name' => 'Ləğvçi']));

        $this->actingAs($this->manager)->get(route('panel.appointments.index', ['status' => 'cancelled']))
            ->assertSee('Ləğvçi')->assertDontSee('Kamal');

        $this->actingAs($this->manager)->get(route('panel.appointments.index', ['q' => '123 45']))
            ->assertSee('Kamal')->assertDontSee('Ləğvçi');

        $this->actingAs($this->manager)->get(route('panel.appointments.index', ['period' => 'custom', 'from' => '2030-05-08', 'to' => '2030-05-08']))
            ->assertSee('Ləğvçi')->assertDontSee('Kamal');
    }

    // ── Status əməliyyatları ─────────────────────────────────────────

    public function test_barber_confirms_and_completes_own_appointment(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-06 11:00', S::Pending);
        $as = $this->actingAs($this->userOf($this->rauf));

        $as->patch(route('panel.appointments.status', $a), ['status' => 'confirmed'])->assertSessionHas('status');
        $this->assertSame(S::Confirmed, $a->fresh()->status);

        // Vaxt çatmayıb
        $as->patch(route('panel.appointments.status', $a), ['status' => 'completed'])
            ->assertSessionHas('error', 'Rezervasiyanın vaxtı hələ çatmayıb.');

        $this->travelTo($this->baku('2030-05-06 11:35'));
        $as->patch(route('panel.appointments.status', $a), ['status' => 'completed']);
        $this->assertSame(S::Completed, $a->fresh()->status);
    }

    public function test_invalid_status_values_are_rejected(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-07 11:00');

        $this->actingAs($this->manager)->patch(route('panel.appointments.status', $a), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status'); // ləğv ayrıca endpoint-dən, səbəb ilə
        $this->actingAs($this->manager)->patch(route('panel.appointments.status', $a), ['status' => 'deleted'])
            ->assertSessionHasErrors('status');
    }

    public function test_staff_cancels_with_reason(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-06 11:00');

        $this->actingAs($this->userOf($this->rauf))
            ->post(route('panel.appointments.cancel', $a), ['reason' => 'Müştəri zəng etdi'])
            ->assertSessionHas('status');

        $a->refresh();
        $this->assertSame(S::Cancelled, $a->status);
        $this->assertSame('Müştəri zəng etdi', $a->cancellation_reason);
    }

    public function test_manager_sees_history_on_show_page(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-07 11:00');
        $this->actingAs($this->manager)->post(route('panel.appointments.cancel', $a));

        $this->actingAs($this->manager)->get(route('panel.appointments.show', $a))
            ->assertOk()->assertSee('Tarixçə')->assertSee('Ləğv edildi');
    }

    // ── Əl ilə rezervasiya ───────────────────────────────────────────

    private function manual(array $overrides = []): array
    {
        return [
            'barber_id' => $this->rauf->id,
            'service_id' => $this->haircut->id,
            'date' => '2030-05-06',
            'time' => '10:20', // 20 dəq sonra, grid-dən kənar — menecer üçün olar
            'name' => 'Qonaq',
            'phone' => '055 111 22 33',
            ...$overrides,
        ];
    }

    public function test_manager_creates_manual_booking(): void
    {
        $this->actingAs($this->manager)->post(route('panel.appointments.store'), $this->manual())
            ->assertRedirect()
            ->assertSessionHas('status', 'Rezervasiya yaradıldı.');

        $a = Appointment::sole();
        $this->assertSame(AppointmentSource::Manual, $a->source);
        $this->assertSame(S::Confirmed, $a->status);
        $this->assertSame($this->manager->id, $a->created_by);
        $this->assertSame('+994551112233', $a->customer->phone);
    }

    public function test_manual_booking_requires_barbers_own_service(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.appointments.store'), $this->manual(['service_id' => $this->elvinHaircut->id]))
            ->assertSessionHasErrors(['service_id' => 'Bu xidmət seçilmiş bərbərə aid deyil.']);
    }

    public function test_manual_booking_still_prevents_conflicts(): void
    {
        $this->appointment($this->rauf, '2030-05-06 11:00');

        $this->actingAs($this->manager)
            ->post(route('panel.appointments.store'), $this->manual(['time' => '11:10']))
            ->assertSessionHas('error', 'Bu vaxt artıq boş deyil. Zəhmət olmasa başqa saat seçin.');

        $this->assertSame(1, Appointment::count());
    }

    public function test_barber_cannot_create_manual_booking_or_reschedule(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-07 11:00');
        $as = $this->actingAs($this->userOf($this->rauf));

        $as->get(route('panel.appointments.create'))->assertForbidden();
        $as->post(route('panel.appointments.store'), $this->manual())->assertForbidden();
        $as->get(route('panel.appointments.edit', $a))->assertForbidden();
        $as->put(route('panel.appointments.update', $a), ['barber_id' => $this->rauf->id, 'date' => '2030-05-07', 'time' => '16:00'])->assertForbidden();
    }

    // ── Başqa vaxta keçirmə ──────────────────────────────────────────

    public function test_manager_reschedules_to_other_barber(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-07 11:00');

        $this->actingAs($this->manager)->get(route('panel.appointments.edit', $a))->assertOk()->assertSee('Elvin');

        $this->actingAs($this->manager)
            ->put(route('panel.appointments.update', $a), ['barber_id' => $this->elvin->id, 'date' => '2030-05-07', 'time' => '16:00'])
            ->assertRedirect(route('panel.appointments.show', $a));

        $a->refresh();
        $this->assertTrue($a->barber->is($this->elvin));
        $this->assertSame('2030-05-07 12:00:00', $a->starts_at->toDateTimeString());
    }

    public function test_reschedule_into_break_is_rejected(): void
    {
        $a = $this->appointment($this->rauf, '2030-05-07 11:00');

        $this->actingAs($this->manager)
            ->put(route('panel.appointments.update', $a), ['barber_id' => $this->rauf->id, 'date' => '2030-05-07', 'time' => '14:15'])
            ->assertSessionHas('error');

        $this->assertSame('2030-05-07 07:00:00', $a->fresh()->starts_at->toDateTimeString());
    }
}
