<?php

namespace Tests\Feature\Public;

use App\Models\Barber;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Booking\BookingScenario;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use BookingScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScenario();
    }

    public function test_home_page_groups_services_with_price_range(): void
    {
        Service::factory()->for($this->rauf)->create(['name' => 'Gizli xidmət', 'is_active' => false]);
        $retired = Barber::factory()->create(['name' => 'Köhnə usta', 'is_active' => false]);
        Service::factory()->for($retired)->create(['name' => 'Köhnə ustanın xidməti']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Saç kəsimi')
            ->assertSee('15–20 ₼')           // Rauf 15, Elvin 20 → bir sətir, aralıq
            ->assertSee('Rauf')
            ->assertDontSee('Gizli xidmət')
            ->assertDontSee('Köhnə usta')
            ->assertDontSee('Köhnə ustanın xidməti') // deaktiv bərbərin xidməti də görünmür
            ->assertSee(route('booking.create', ['xidmet' => 'sac-kesimi']), false);
    }

    public function test_booking_page_renders_wizard_config(): void
    {
        $this->get(route('booking.create', ['xidmet' => 'sac-kesimi']))
            ->assertOk()
            ->assertSee('bookingWizard(', false)
            ->assertViewHas('config', function (array $config) {
                $group = $config['groups']->firstWhere('key', 'sac-kesimi');

                return $config['today'] === '2030-05-06'
                    && $config['preselect']['group'] === 'sac-kesimi'
                    && count($config['services']) === 2
                    && $group['price'] === '15–20 ₼'
                    && $group['barberIds'] === [$this->rauf->id, $this->elvin->id]
                    && $config['services']->firstWhere('id', $this->elvinHaircut->id)['barberId'] === $this->elvin->id
                    && $config['barbers']->firstWhere('name', 'Rauf')['weekdays'] === [1, 2, 3, 4, 5, 6];
            });
    }

    public function test_barber_without_services_is_not_offered_in_wizard(): void
    {
        Barber::factory()->create(['name' => 'Yeni usta']);

        $this->get(route('booking.create'))
            ->assertViewHas('config', fn ($c) => $c['barbers']->firstWhere('name', 'Yeni usta') === null);
    }

    public function test_wizard_config_contains_no_private_data(): void
    {
        $this->rauf->update(['phone' => '+994551112233', 'telegram_chat_id' => '123456789']);

        $this->get(route('booking.create'))
            ->assertDontSee('551112233')
            ->assertDontSee('123456789');
    }

    // ── Slots JSON endpoint ──────────────────────────────────────────

    public function test_slots_endpoint_returns_local_times(): void
    {
        $this->getJson(route('booking.slots', ['service' => $this->haircut->id, 'barber' => $this->rauf->id, 'date' => self::TUESDAY]))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('date', self::TUESDAY)
            ->assertJsonPath('slots.0', '10:00')
            ->assertJsonCount(34, 'slots');
    }

    public function test_slots_endpoint_supports_any_barber(): void
    {
        $this->getJson(route('booking.slots', ['service' => $this->haircut->id, 'barber' => 'any', 'date' => self::TUESDAY]))
            ->assertOk()
            ->assertJsonPath('slots.0', '10:00');
    }

    public function test_slots_endpoint_validates_input(): void
    {
        $this->getJson(route('booking.slots', ['service' => $this->haircut->id, 'barber' => 'any', 'date' => 'sabah']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->getJson(route('booking.slots', ['barber' => 'any', 'date' => self::TUESDAY]))->assertUnprocessable();

        $this->haircut->update(['is_active' => false]);
        $this->getJson(route('booking.slots', ['service' => $this->haircut->id, 'barber' => 'any', 'date' => self::TUESDAY]))->assertNotFound();
    }

    public function test_slots_endpoint_is_rate_limited(): void
    {
        $url = route('booking.slots', ['service' => $this->haircut->id, 'barber' => 'any', 'date' => self::TUESDAY]);

        foreach (range(1, 60) as $_) {
            $this->getJson($url)->assertOk();
        }

        $this->getJson($url)->assertTooManyRequests();
    }
}
