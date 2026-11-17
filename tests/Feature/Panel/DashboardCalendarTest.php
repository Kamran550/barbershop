<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus as S;
use App\Models\BlockedPeriod;
use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCalendarTest extends TestCase
{
    use PanelScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function test_manager_dashboard_numbers(): void
    {
        $this->appointment($this->rauf, '2030-05-06 12:00');                         // bu gün, aktiv
        $this->appointment($this->elvin, '2030-05-06 15:00', S::Pending);            // bu gün, gözləyir
        $this->appointment($this->rauf, '2030-05-02 12:00', S::Completed, price: 20); // ay, gəlir
        $this->appointment($this->elvin, '2030-05-03 12:00', S::Completed, price: 15);
        $this->appointment($this->rauf, '2030-05-04 12:00', S::Cancelled);
        $this->appointment($this->rauf, '2030-04-30 12:00', S::Completed, price: 99); // keçən ay — sayılmır

        $this->actingAs($this->manager)->get(route('panel.dashboard'))
            ->assertOk()
            ->assertViewHas('todayCount', 2)
            ->assertViewHas('upcomingCount', 2)
            ->assertViewHas('monthCompleted', 2)
            ->assertViewHas('monthCancelled', 1)
            ->assertViewHas('monthRevenue', 35.0)
            ->assertViewHas('customerCount', 6)
            ->assertViewHas('pending', fn ($p) => $p->count() === 1)
            ->assertViewHas('barberStats', fn ($s) => (float) $s->firstWhere('name', 'Rauf')->revenue === 20.0)
            ->assertSee('Bərbərlər');
    }

    public function test_barber_dashboard_shows_only_own_numbers(): void
    {
        $this->appointment($this->rauf, '2030-05-02 12:00', S::Completed, price: 20);
        $this->appointment($this->elvin, '2030-05-03 12:00', S::Completed, price: 15);

        $this->actingAs($this->userOf($this->rauf))->get(route('panel.dashboard'))
            ->assertViewHas('monthRevenue', 20.0)
            ->assertViewHas('customerCount', 1)
            ->assertViewHas('barberStats', fn ($s) => $s->isEmpty());
    }

    // ── Calendar ─────────────────────────────────────────────────────

    public function test_day_view_manager_sees_all_columns(): void
    {
        $this->appointment($this->rauf, '2030-05-07 11:00', customer: Customer::factory()->create(['name' => 'Kamal']));
        $this->appointment($this->elvin, '2030-05-07 12:00', customer: Customer::factory()->create(['name' => 'Murad']));
        $this->appointment($this->rauf, '2030-05-07 16:00', S::Cancelled, Customer::factory()->create(['name' => 'Ləğvçi']));

        $this->actingAs($this->manager)->get(route('panel.calendar', ['view' => 'day', 'date' => self::TUESDAY]))
            ->assertOk()
            ->assertSee('Rauf')->assertSee('Elvin')
            ->assertSee('Kamal')->assertSee('Murad')
            ->assertDontSee('Ləğvçi') // ləğv edilmiş vaxtı tutmur — göstərilmir
            ->assertSee('Fasilə')
            ->assertViewHas('hours', [10, 20]);
    }

    public function test_barber_calendar_contains_only_own_column(): void
    {
        $this->appointment($this->elvin, '2030-05-07 12:00', customer: Customer::factory()->create(['name' => 'Murad']));

        $this->actingAs($this->userOf($this->rauf))
            ->get(route('panel.calendar', ['view' => 'day', 'date' => self::TUESDAY, 'barber_id' => $this->elvin->id]))
            ->assertOk()
            ->assertDontSee('Murad')
            ->assertViewHas('columns', fn ($c) => $c->count() === 1 && $c->first()['key'] === $this->rauf->id);
    }

    public function test_week_view_shows_seven_days_and_blocks(): void
    {
        BlockedPeriod::create(['barber_id' => $this->rauf->id, 'starts_at' => $this->baku('2030-05-08 16:00')->utc(), 'ends_at' => $this->baku('2030-05-08 17:00')->utc(), 'reason' => 'Həkim']);
        $this->appointment($this->rauf, '2030-05-09 11:00', customer: Customer::factory()->create(['name' => 'Cümə axşamı müştərisi']));

        $this->actingAs($this->manager)->get(route('panel.calendar', ['view' => 'week', 'date' => self::TUESDAY, 'barber_id' => $this->rauf->id]))
            ->assertOk()
            ->assertViewHas('columns', fn ($c) => $c->count() === 7 && $c->first()['key'] === '2030-05-06')
            ->assertSee('Həkim')
            ->assertSee('Cümə axşamı müştərisi');
    }

    public function test_month_view_counts_and_revenue(): void
    {
        $this->appointment($this->rauf, '2030-05-02 12:00', S::Completed, price: 20);
        $this->appointment($this->elvin, '2030-05-02 15:00', S::Completed, price: 15);
        $this->appointment($this->elvin, '2030-05-02 17:00', S::Cancelled);

        $this->actingAs($this->manager)->get(route('panel.calendar', ['view' => 'month', 'date' => self::TUESDAY]))
            ->assertOk()
            ->assertViewHas('monthDays', function ($days) {
                $day = $days->first(fn ($d) => $d['date']->toDateString() === '2030-05-02');

                return $day['count'] === 2 && $day['revenue'] === 35.0 && $days->count() % 7 === 0;
            });
    }

    public function test_invalid_calendar_params_are_rejected(): void
    {
        $this->actingAs($this->manager)->get(route('panel.calendar', ['view' => 'year']))->assertSessionHasErrors('view');
    }
}
