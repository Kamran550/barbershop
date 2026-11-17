<?php

namespace Tests\Feature\Auth;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manager / Barber authorization modeli:
 *  - Policy-lər tək obyekti qoruyur (IDOR: başqasının ID-sini URL-ə yazmaq işə yaramır)
 *  - visibleTo() scope-ları siyahıları filtrləyir
 */
class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $raufUser;

    private Barber $rauf;

    private Barber $elvin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->rauf = Barber::factory()->withUser()->create(['name' => 'Rauf']);
        $this->elvin = Barber::factory()->withUser()->create(['name' => 'Elvin']);
        $this->raufUser = $this->rauf->user;
    }

    // ── Rezervasiyalar ────────────────────────────────────────────────

    public function test_barber_sees_only_own_appointments_in_lists(): void
    {
        $mine = Appointment::factory()->for($this->rauf)->count(2)->create();
        Appointment::factory()->for($this->elvin)->count(3)->create();

        $visible = Appointment::query()->visibleTo($this->raufUser)->pluck('id');

        $this->assertEqualsCanonicalizing($mine->pluck('id')->all(), $visible->all());
        $this->assertSame(5, Appointment::query()->visibleTo($this->manager)->count());
    }

    public function test_barber_cannot_view_or_change_another_barbers_appointment(): void
    {
        $elvins = Appointment::factory()->for($this->elvin)->create();
        $raufs = Appointment::factory()->for($this->rauf)->create();

        $this->assertTrue($this->raufUser->can('view', $raufs));
        $this->assertTrue($this->raufUser->can('updateStatus', $raufs));

        $this->assertFalse($this->raufUser->can('view', $elvins), 'IDOR: başqa bərbərin rezervasiyası');
        $this->assertFalse($this->raufUser->can('updateStatus', $elvins));
    }

    public function test_only_manager_can_create_manual_booking_and_reschedule(): void
    {
        $appointment = Appointment::factory()->for($this->rauf)->create();

        $this->assertTrue($this->manager->can('create', Appointment::class));
        $this->assertTrue($this->manager->can('reschedule', $appointment));

        $this->assertFalse($this->raufUser->can('create', Appointment::class));
        $this->assertFalse($this->raufUser->can('reschedule', $appointment), 'Öz rezervasiyasını belə keçirə bilməz');
    }

    public function test_nobody_can_delete_appointments(): void
    {
        $appointment = Appointment::factory()->for($this->rauf)->create();

        $this->assertFalse($this->manager->can('delete', $appointment));
        $this->assertFalse($this->raufUser->can('delete', $appointment));
    }

    public function test_barber_user_without_profile_sees_nothing(): void
    {
        $orphan = User::factory()->barber()->create();
        $appointment = Appointment::factory()->for($this->rauf)->create();

        $this->assertNull($orphan->barberId());
        $this->assertSame(0, Appointment::query()->visibleTo($orphan)->count());
        $this->assertSame(0, Customer::query()->visibleTo($orphan)->count());
        $this->assertFalse($orphan->can('view', $appointment));
    }

    // ── Müştərilər ───────────────────────────────────────────────────

    public function test_barber_sees_only_customers_who_booked_with_them(): void
    {
        $shared = Customer::factory()->create();
        $raufOnly = Customer::factory()->create();
        $elvinOnly = Customer::factory()->create();

        Appointment::factory()->for($this->rauf)->for($shared)->create();
        Appointment::factory()->for($this->elvin)->for($shared)->create();
        Appointment::factory()->for($this->rauf)->for($raufOnly)->create();
        Appointment::factory()->for($this->elvin)->for($elvinOnly)->create();

        $visible = Customer::query()->visibleTo($this->raufUser)->pluck('id');

        $this->assertEqualsCanonicalizing([$shared->id, $raufOnly->id], $visible->all());
        $this->assertTrue($this->raufUser->can('view', $raufOnly));
        $this->assertFalse($this->raufUser->can('view', $elvinOnly));
        $this->assertSame(3, Customer::query()->visibleTo($this->manager)->count());
    }

    public function test_only_manager_can_edit_customer_details(): void
    {
        $customer = Customer::factory()->create();
        Appointment::factory()->for($this->rauf)->for($customer)->create();

        $this->assertTrue($this->manager->can('update', $customer));
        $this->assertFalse($this->raufUser->can('update', $customer));
    }

    // ── Private qeydlər ──────────────────────────────────────────────

    public function test_private_notes_are_visible_only_to_author_and_manager(): void
    {
        $customer = Customer::factory()->create();
        Appointment::factory()->for($this->rauf)->for($customer)->create();
        Appointment::factory()->for($this->elvin)->for($customer)->create();
        $elvinUser = $this->elvin->user;

        $raufsNote = CustomerNote::create(['customer_id' => $customer->id, 'user_id' => $this->raufUser->id, 'body' => 'Qısa kəsim sevir']);
        $elvinsNote = CustomerNote::create(['customer_id' => $customer->id, 'user_id' => $elvinUser->id, 'body' => 'Allergiya var']);

        // Elvin eyni müştəriyə xidmət etsə belə, Raufun qeydini görmür
        $this->assertFalse($elvinUser->can('view', $raufsNote));
        $this->assertFalse($elvinUser->can('delete', $raufsNote));
        $this->assertTrue($elvinUser->can('update', $elvinsNote));

        $this->assertSame([$raufsNote->id], $customer->notes()->visibleTo($this->raufUser)->pluck('id')->all());
        $this->assertSame(2, $customer->notes()->visibleTo($this->manager)->count());
        $this->assertTrue($this->manager->can('delete', $raufsNote));
    }

    public function test_barber_can_add_note_only_for_own_customer(): void
    {
        $own = Customer::factory()->create();
        $foreign = Customer::factory()->create();
        Appointment::factory()->for($this->rauf)->for($own)->create();
        Appointment::factory()->for($this->elvin)->for($foreign)->create();

        $this->assertTrue($this->raufUser->can('create', [CustomerNote::class, $own]));
        $this->assertFalse($this->raufUser->can('create', [CustomerNote::class, $foreign]));
    }

    // ── Bərbərlər və qrafik ──────────────────────────────────────────

    public function test_barber_manages_own_schedule_and_profile_but_not_team_settings(): void
    {
        // Özünü idarə edir
        $this->assertTrue($this->raufUser->can('view', $this->rauf));
        $this->assertTrue($this->raufUser->can('manageSchedule', $this->rauf));
        $this->assertTrue($this->raufUser->can('updateProfile', $this->rauf));

        // Menecerin işi: aktivlik, login hesabı, silmək, komanda siyahısı
        $this->assertFalse($this->raufUser->can('update', $this->rauf));
        $this->assertFalse($this->raufUser->can('delete', $this->rauf));
        $this->assertFalse($this->raufUser->can('viewAny', Barber::class));

        // Başqa bərbərə toxuna bilməz
        $this->assertFalse($this->raufUser->can('view', $this->elvin));
        $this->assertFalse($this->raufUser->can('manageSchedule', $this->elvin));
        $this->assertFalse($this->raufUser->can('updateProfile', $this->elvin));
    }

    public function test_service_ownership_policy(): void
    {
        $raufs = \App\Models\Service::factory()->for($this->rauf)->create();
        $elvins = \App\Models\Service::factory()->for($this->elvin)->create();

        $this->assertTrue($this->raufUser->can('update', $raufs));
        $this->assertTrue($this->raufUser->can('delete', $raufs));
        $this->assertFalse($this->raufUser->can('update', $elvins));
        $this->assertFalse($this->raufUser->can('delete', $elvins));

        $this->assertTrue($this->manager->can('update', $elvins));
        $this->assertSame([$raufs->id], \App\Models\Service::query()->visibleTo($this->raufUser)->pluck('id')->all());
    }

    public function test_manager_manages_all_barbers(): void
    {
        $this->assertTrue($this->manager->can('viewAny', Barber::class));
        $this->assertTrue($this->manager->can('create', Barber::class));
        $this->assertTrue($this->manager->can('manageSchedule', $this->elvin));
    }

    // ── Dashboard scope nümayişi ─────────────────────────────────────

    public function test_dashboard_counts_only_visible_appointments(): void
    {
        // Vaxtı dondururuq: 08:00 UTC = 12:00 Bakı. Test gecə yarısı işləsə belə nəticə dəyişmir.
        $this->travelTo(CarbonImmutable::parse('2030-05-10 08:00:00', 'UTC'));
        $noon = CarbonImmutable::parse('2030-05-10 10:00:00', 'UTC');

        Appointment::factory()->for($this->rauf)->at($noon)->create();
        Appointment::factory()->for($this->elvin)->at($noon)->create();
        Appointment::factory()->for($this->elvin)->at($noon->addDay())->create(); // sabah — sayılmır

        $this->actingAs($this->manager)->get(route('panel.dashboard'))->assertViewHas('todayCount', 2);
        $this->actingAs($this->raufUser)->get(route('panel.dashboard'))->assertViewHas('todayCount', 1);
    }
}
