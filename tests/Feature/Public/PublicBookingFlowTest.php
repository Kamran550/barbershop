<?php

namespace Tests\Feature\Public;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\CustomerNote;
use App\Models\User;
use App\Services\Booking\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Booking\BookingScenario;
use Tests\TestCase;

/** Müştərinin tam yolu: forma → nəticə səhifəsi → təqvim → ləğv. */
class PublicBookingFlowTest extends TestCase
{
    use BookingScenario;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpScenario();
    }

    private function form(array $overrides = []): array
    {
        return [
            'service_id' => $this->haircut->id,
            'barber_id' => $this->rauf->id,
            'date' => self::TUESDAY,
            'time' => '11:00',
            'name' => 'Kamal',
            'phone' => '050 123 45 67',
            ...$overrides,
        ];
    }

    /** POST edib nəticə səhifəsinin tokenini qaytarır. */
    private function bookAndGetToken(array $overrides = []): string
    {
        $response = $this->post(route('booking.store'), $this->form($overrides));
        $response->assertRedirect();

        preg_match('#/rezervasiya/([A-Za-z0-9]{48})$#', $response->headers->get('Location'), $m);
        $this->assertNotEmpty($m, 'Token linkinə yönləndirilməli idi');

        return $m[1];
    }

    public function test_successful_booking_redirects_to_ticket_page(): void
    {
        $token = $this->bookAndGetToken();

        $appointment = Appointment::sole();
        $this->assertSame('2030-05-07 07:00:00', $appointment->starts_at->toDateTimeString());
        $this->assertTrue($appointment->barber->is($this->rauf));

        $this->get(route('booking.show', $token))
            ->assertOk()
            ->assertSee('Rezervasiya təsdiqləndi')
            ->assertSee('11:00')
            ->assertSee('Saç kəsimi')
            ->assertSee('+994 50 *** ** 67')        // telefon maskalanıb
            ->assertDontSee('+994501234567')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_any_barber_option_uses_chosen_barbers_own_price(): void
    {
        // Rauf 11:00-da məşğuldur → "Fərq etməz" Elvini seçməlidir, qiymət Elvinin 20 ₼-u
        $this->bookAndGetToken(['phone' => '0501111111']);

        $token = $this->bookAndGetToken(['barber_id' => 'any', 'phone' => '0502222222']);

        $appointment = Appointment::latest('id')->first();
        $this->assertTrue($appointment->barber->is($this->elvin));
        $this->assertSame('20.00', $appointment->service_price);
        $this->get(route('booking.show', $token))->assertSee('Elvin')->assertSee('20 ₼');
    }

    public function test_service_of_another_barber_cannot_be_booked(): void
    {
        // Elvinin xidməti + Rauf (formanı əllə dəyişdirmək cəhdi)
        $this->from(route('booking.create'))
            ->post(route('booking.store'), $this->form(['service_id' => $this->elvinHaircut->id, 'barber_id' => $this->rauf->id]))
            ->assertRedirect(route('booking.create'))
            ->assertSessionHas('error', 'Seçilmiş bərbər bu xidməti göstərmir.');

        $this->assertSame(0, Appointment::count());
    }

    public function test_taken_slot_returns_to_wizard_with_message_and_input(): void
    {
        $this->bookAndGetToken(['phone' => '0501111111']);

        $this->from(route('booking.create'))
            ->post(route('booking.store'), $this->form(['phone' => '0502222222', 'name' => 'Murad']))
            ->assertRedirect(route('booking.create'))
            ->assertSessionHas('error', 'Bu vaxt artıq boş deyil. Zəhmət olmasa başqa saat seçin.')
            ->assertSessionHasInput('name', 'Murad');

        $this->assertSame(1, Appointment::count());

        // Wizard saat seçimi addımına qayıdır
        $this->get(route('booking.create'))->assertViewHas('config', fn ($c) => $c['errorStep'] === 3 && $c['old']['name'] === 'Murad');
    }

    public function test_validation_errors(): void
    {
        $this->post(route('booking.store'), $this->form(['phone' => '123', 'name' => 'A', 'time' => '25:00']))
            ->assertSessionHasErrors(['phone', 'name', 'time']);

        $this->assertSame(0, Appointment::count());
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post(route('booking.store'), $this->form(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, Appointment::count());
    }

    public function test_inactive_barber_or_service_cannot_be_booked(): void
    {
        $this->rauf->update(['is_active' => false]);

        $this->post(route('booking.store'), $this->form())->assertSessionHasErrors('barber_id');
        $this->assertSame(0, Appointment::count());
    }

    public function test_booking_is_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('booking.store'), $this->form(['time' => '10:00', 'phone' => '050000000'.$i]));
        }

        $this->post(route('booking.store'), $this->form(['time' => '16:00', 'phone' => '0559999999']))->assertTooManyRequests();
    }

    public function test_csrf_is_required(): void
    {
        // Laravel CSRF yoxlamasını env === 'testing' olanda avtomatik keçir — burada müvəqqəti başqa env veririk
        $this->app['env'] = 'local';

        $this->post(route('booking.store'), $this->form())->assertStatus(419); // 419 = CSRF token yoxdur / səhvdir

        $this->assertSame(0, Appointment::count(), 'Token olmadan rezervasiya yaranmamalıdır');
    }

    // ── Bilet səhifəsi ──────────────────────────────────────────────

    public function test_unknown_or_malformed_token_returns_404(): void
    {
        $this->get('/rezervasiya/'.str_repeat('a', 48))->assertNotFound();
        $this->get('/rezervasiya/qisa-token')->assertNotFound();
    }

    public function test_customer_name_is_escaped(): void
    {
        $token = $this->bookAndGetToken(['name' => '<script>alert(1)</script>']);

        $this->get(route('booking.show', $token))
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_private_notes_never_appear_on_public_page(): void
    {
        $token = $this->bookAndGetToken();
        CustomerNote::create([
            'customer_id' => Appointment::sole()->customer_id,
            'user_id' => User::factory()->manager()->create()->id,
            'body' => 'GİZLİ: həmişə gecikir',
        ]);

        $this->get(route('booking.show', $token))->assertDontSee('GİZLİ');
    }

    public function test_calendar_file(): void
    {
        $token = $this->bookAndGetToken();

        $response = $this->get(route('booking.calendar', $token))->assertOk();

        $this->assertStringStartsWith('text/calendar', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('DTSTART:20300507T070000Z', $response->getContent());
        $this->assertStringContainsString('DTEND:20300507T073000Z', $response->getContent());
    }

    // ── Ləğv ─────────────────────────────────────────────────────────

    public function test_customer_cancels_via_link_and_slot_frees_up(): void
    {
        $token = $this->bookAndGetToken();

        $this->get(route('booking.show', $token))->assertSee('Rezervasiyanı ləğv et');

        $this->post(route('booking.cancel', $token))
            ->assertRedirect(route('booking.show', $token))
            ->assertSessionHas('status', 'Rezervasiyanız ləğv edildi.');

        $this->assertSame(AppointmentStatus::Cancelled, Appointment::sole()->status);
        $this->get(route('booking.show', $token))->assertSee('Ləğv edilib')->assertDontSee('Rezervasiyanı ləğv et');

        // Vaxt yenidən boşdur
        $this->getJson(route('booking.slots', ['service' => $this->haircut->id, 'barber' => $this->rauf->id, 'date' => self::TUESDAY]))
            ->assertJsonFragment(['11:00']);
    }

    public function test_cancel_button_hidden_and_blocked_after_deadline(): void
    {
        $token = $this->bookAndGetToken(['date' => '2030-05-06', 'time' => '12:00']); // 2 saat qalıb
        $this->travel(1)->minutes();

        $this->get(route('booking.show', $token))
            ->assertDontSee('Rezervasiyanı ləğv et')
            ->assertSee('2 saatdan az qalıb');

        $this->post(route('booking.cancel', $token))->assertSessionHas('error');
        $this->assertSame(AppointmentStatus::Confirmed, Appointment::sole()->status);
    }

    public function test_token_is_shown_only_once_and_stored_hashed(): void
    {
        $token = $this->bookAndGetToken();

        $this->assertSame(BookingService::hashToken($token), Appointment::sole()->token_hash);
        $this->assertDatabaseMissing('appointments', ['token_hash' => $token]);
    }
}
