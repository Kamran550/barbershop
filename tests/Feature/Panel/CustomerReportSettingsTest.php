<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus as S;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\CustomerNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Once;
use Tests\TestCase;

class CustomerReportSettingsTest extends TestCase
{
    use PanelScenario;
    use RefreshDatabase;

    private Customer $kamal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPanel();
        $this->kamal = Customer::factory()->create(['name' => 'Kamal', 'phone' => '+994501234567']);
    }

    // ── Müştərilər (CRM) ─────────────────────────────────────────────

    public function test_customer_stats_count_only_completed_visits(): void
    {
        $this->appointment($this->rauf, '2030-05-01 12:00', S::Completed, $this->kamal, 20);
        $this->appointment($this->elvin, '2030-05-03 12:00', S::Completed, $this->kamal, 15);
        $this->appointment($this->rauf, '2030-05-04 12:00', S::NoShow, $this->kamal, 20);
        $this->appointment($this->rauf, '2030-05-07 12:00', S::Confirmed, $this->kamal, 20);

        $this->actingAs($this->manager)->get(route('panel.customers.show', $this->kamal))
            ->assertOk()
            ->assertViewHas('customer', fn ($c) => $c->visits_count === 2 && (float) $c->total_spent === 35.0)
            ->assertSee('03.05.2030'); // son ziyarət
    }

    public function test_barber_sees_only_own_visits_and_customers(): void
    {
        $other = Customer::factory()->create(['name' => 'Yalnız Elvinin']);
        $this->appointment($this->rauf, '2030-05-01 12:00', S::Completed, $this->kamal, 20);
        $this->appointment($this->elvin, '2030-05-03 12:00', S::Completed, $this->kamal, 15);
        $this->appointment($this->elvin, '2030-05-03 15:00', S::Completed, $other, 15);

        $rauf = $this->userOf($this->rauf);

        $this->actingAs($rauf)->get(route('panel.customers.index'))
            ->assertSee('Kamal')->assertDontSee('Yalnız Elvinin');

        $this->actingAs($rauf)->get(route('panel.customers.show', $this->kamal))
            ->assertViewHas('customer', fn ($c) => $c->visits_count === 1 && (float) $c->total_spent === 20.0)
            ->assertViewHas('appointments', fn ($a) => $a->count() === 1);

        $this->actingAs($rauf)->get(route('panel.customers.show', $other))->assertForbidden();
    }

    public function test_customer_search_by_phone_fragment(): void
    {
        $this->appointment($this->rauf, '2030-05-07 12:00', customer: $this->kamal);
        $this->appointment($this->rauf, '2030-05-08 12:00', customer: Customer::factory()->create(['name' => 'Murad', 'phone' => '+994559998877']));

        $this->actingAs($this->manager)->get(route('panel.customers.index', ['q' => '050 123']))
            ->assertSee('Kamal')->assertDontSee('Murad');
    }

    public function test_private_notes_author_and_manager_only(): void
    {
        $this->appointment($this->rauf, '2030-05-07 12:00', customer: $this->kamal);
        $this->appointment($this->elvin, '2030-05-08 12:00', customer: $this->kamal);

        $this->actingAs($this->userOf($this->rauf))
            ->post(route('panel.customers.notes.store', $this->kamal), ['body' => 'Qısa fade sevir'])
            ->assertSessionHas('status');

        $this->actingAs($this->userOf($this->elvin))->get(route('panel.customers.show', $this->kamal))->assertDontSee('Qısa fade sevir');
        $this->actingAs($this->manager)->get(route('panel.customers.show', $this->kamal))->assertSee('Qısa fade sevir');

        $note = CustomerNote::sole();
        $this->actingAs($this->userOf($this->elvin))->delete(route('panel.customers.notes.destroy', [$this->kamal, $note]))->assertForbidden();
        $this->assertModelExists($note);
    }

    public function test_note_url_must_match_customer(): void
    {
        $other = Customer::factory()->create();
        $note = CustomerNote::create(['customer_id' => $this->kamal->id, 'user_id' => $this->manager->id, 'body' => 'x']);

        $this->actingAs($this->manager)->delete(route('panel.customers.notes.destroy', [$other, $note]))->assertNotFound();
    }

    public function test_manager_updates_customer_phone_with_normalization_and_uniqueness(): void
    {
        Customer::factory()->create(['phone' => '+994559998877']);

        $this->actingAs($this->manager)->put(route('panel.customers.update', $this->kamal), ['name' => 'Kamal Ə.', 'phone' => '055 999 88 77'])
            ->assertSessionHasErrors(['phone' => 'Bu nömrə ilə başqa müştəri artıq var.']);

        $this->actingAs($this->manager)->put(route('panel.customers.update', $this->kamal), ['name' => 'Kamal Ə.', 'phone' => '070 111 22 33'])
            ->assertSessionHasNoErrors();
        $this->assertSame('+994701112233', $this->kamal->fresh()->phone);
    }

    public function test_barber_cannot_edit_customer_details(): void
    {
        $this->appointment($this->rauf, '2030-05-07 12:00', customer: $this->kamal);

        $this->actingAs($this->userOf($this->rauf))
            ->put(route('panel.customers.update', $this->kamal), ['name' => 'X', 'phone' => '0701112233'])
            ->assertForbidden();
    }

    // ── Hesabatlar ───────────────────────────────────────────────────

    public function test_revenue_report_by_barber_and_service(): void
    {
        $this->appointment($this->rauf, '2030-05-01 12:00', S::Completed, price: 20);
        $this->appointment($this->rauf, '2030-05-02 12:00', S::Completed, price: 20);
        $this->appointment($this->elvin, '2030-05-02 12:00', S::Completed, price: 15)->update(['service_name' => 'Saqqal']);
        $this->appointment($this->elvin, '2030-05-03 12:00', S::NoShow, price: 15);
        $this->appointment($this->rauf, '2030-04-20 12:00', S::Completed, price: 99); // dövrdən kənar

        $this->actingAs($this->manager)->get(route('panel.reports', ['from' => '2030-05-01', 'to' => '2030-05-06']))
            ->assertOk()
            ->assertViewHas('report', function (array $r) {
                return $r['total'] === 55.0
                    && $r['completedCount'] === 3
                    && $r['statusCounts']['no_show'] === 1
                    && (float) $r['byBarber']->firstWhere('name', 'Rauf')->revenue === 40.0
                    && (float) $r['byService']->firstWhere('service_name', 'Saqqal')->revenue === 15.0;
            });
    }

    public function test_report_filtered_by_barber(): void
    {
        $this->appointment($this->rauf, '2030-05-01 12:00', S::Completed, price: 20);
        $this->appointment($this->elvin, '2030-05-02 12:00', S::Completed, price: 15);

        $this->actingAs($this->manager)->get(route('panel.reports', ['from' => '2030-05-01', 'to' => '2030-05-06', 'barber_id' => $this->elvin->id]))
            ->assertViewHas('report', fn ($r) => $r['total'] === 15.0);
    }

    public function test_barber_cannot_see_reports_or_settings(): void
    {
        $as = $this->actingAs($this->userOf($this->rauf));
        $as->get(route('panel.reports'))->assertForbidden();
        $as->get(route('panel.settings.edit'))->assertForbidden();
        $as->put(route('panel.settings.update'), [])->assertForbidden();
    }

    // ── Parametrlər ──────────────────────────────────────────────────

    private function settingsForm(array $overrides = []): array
    {
        $hours = [];
        foreach (range(1, 7) as $wd) {
            $hours[$wd] = $wd === 7 ? ['open_day' => '0'] : ['open_day' => '1', 'open' => '10:00', 'close' => '20:00'];
        }

        return [
            'name' => 'Klassik Bərbər',
            'tagline' => 'Yeni şüar',
            'timezone' => 'Asia/Baku',
            'opening_hours' => $hours,
            'slot_interval_minutes' => 15,
            'buffer_minutes' => 10,
            'booking_horizon_days' => 14,
            'min_notice_minutes' => 30,
            'cancel_deadline_minutes' => 60,
            'max_active_bookings_per_phone' => 2,
            'auto_confirm' => '1',
            ...$overrides,
        ];
    }

    public function test_manager_updates_settings_and_site_uses_them(): void
    {
        $this->actingAs($this->manager)->put(route('panel.settings.update'), $this->settingsForm())
            ->assertRedirect(route('panel.settings.edit'))
            ->assertSessionHasNoErrors();

        Once::flush();
        $s = BusinessSetting::current();
        $this->assertSame('Klassik Bərbər', $s->name);
        $this->assertSame(10, $s->buffer_minutes);
        $this->assertNull($s->opening_hours[7]);
        $this->assertSame(['open' => '10:00', 'close' => '20:00'], $s->opening_hours[1]);

        $this->get(route('home'))->assertSee('Klassik Bərbər')->assertSee('Yeni şüar');
    }

    public function test_opening_hours_close_must_be_after_open(): void
    {
        $form = $this->settingsForm();
        $form['opening_hours'][2] = ['open_day' => '1', 'open' => '20:00', 'close' => '10:00'];

        $this->actingAs($this->manager)->put(route('panel.settings.update'), $form)->assertSessionHasErrors('opening_hours.2.close');
    }

    public function test_telegram_token_is_encrypted_kept_when_blank_and_never_rendered(): void
    {
        $token = '123456789:AAHdqTcvCH1vGWJxfSeofSAs0K5PALDsaw';

        $this->actingAs($this->manager)->put(route('panel.settings.update'), $this->settingsForm([
            'telegram_enabled' => '1', 'telegram_bot_token' => $token, 'telegram_manager_chat_id' => '123456789',
        ]))->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('AAHdq', DB::table('business_settings')->value('telegram_bot_token'));

        // Boş göndərilir → token dəyişmir
        $this->actingAs($this->manager)->put(route('panel.settings.update'), $this->settingsForm([
            'telegram_enabled' => '1', 'telegram_bot_token' => '', 'telegram_manager_chat_id' => '123456789',
        ]))->assertSessionHasNoErrors();
        Once::flush();
        $this->assertSame($token, BusinessSetting::current()->telegram_bot_token);

        // Forma tokeni geri göstərmir, audit jurnalına da düşmür
        $this->actingAs($this->manager)->get(route('panel.settings.edit'))->assertDontSee('AAHdq')->assertSee('təyin edilib');
        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['telegram_bot_token' => $token])]);
        $this->assertFalse(DB::table('audit_logs')->where('new_values', 'like', '%AAHdq%')->exists());
    }

    public function test_enabling_telegram_requires_token_and_chat_id(): void
    {
        $this->actingAs($this->manager)->put(route('panel.settings.update'), $this->settingsForm(['telegram_enabled' => '1']))
            ->assertSessionHasErrors(['telegram_bot_token', 'telegram_manager_chat_id']);

        $this->actingAs($this->manager)->put(route('panel.settings.update'), $this->settingsForm(['telegram_bot_token' => 'yanlış-token']))
            ->assertSessionHasErrors('telegram_bot_token');
    }
}
