<?php

namespace Tests\Feature\Panel;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Xidmətlər bərbərə məxsusdur:
 *  - bərbər öz xidmətlərini yaradır / redaktə edir / silir, başqasınınkına toxuna bilmir
 *  - menecer hamısını idarə edir və yeni xidməti istənilən bərbərin adına yaradır
 */
class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Barber $rauf;

    private Barber $elvin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->manager = User::factory()->manager()->create();
        $this->rauf = Barber::factory()->withUser()->create(['name' => 'Rauf']);
        $this->elvin = Barber::factory()->withUser()->create(['name' => 'Elvin']);
    }

    private function validData(array $overrides = []): array
    {
        return [
            'name' => 'Saç kəsimi',
            'description' => 'Klassik kəsim',
            'price' => '15.50',
            'duration_minutes' => 30,
            'is_active' => '1',
            ...$overrides,
        ];
    }

    // ── Bərbər öz xidmətləri ─────────────────────────────────────────

    public function test_barber_creates_own_service(): void
    {
        $this->actingAs($this->rauf->user)
            ->post(route('panel.services.store'), $this->validData())
            ->assertRedirect(route('panel.services.index'));

        $service = Service::sole();
        $this->assertTrue($service->barber->is($this->rauf));
        $this->assertSame('sac-kesimi-rauf', $service->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'service.created', 'user_id' => $this->rauf->user_id]);
    }

    public function test_barber_cannot_create_service_for_another_barber(): void
    {
        // barber_id göndərilsə belə nəzərə alınmır — sahib həmişə özüdür
        $this->actingAs($this->rauf->user)
            ->post(route('panel.services.store'), $this->validData(['barber_id' => $this->elvin->id]));

        $this->assertTrue(Service::sole()->barber->is($this->rauf));
    }

    public function test_barber_sees_only_own_services(): void
    {
        Service::factory()->for($this->rauf)->create(['name' => 'Raufun kəsimi']);
        Service::factory()->for($this->elvin)->create(['name' => 'Elvinin kəsimi']);

        $this->actingAs($this->rauf->user)->get(route('panel.services.index'))
            ->assertOk()
            ->assertSee('Xidmətlərim')
            ->assertSee('Raufun kəsimi')
            ->assertDontSee('Elvinin kəsimi');
    }

    public function test_barber_cannot_edit_or_delete_another_barbers_service(): void
    {
        $elvins = Service::factory()->for($this->elvin)->create(['price' => 20]);

        $this->actingAs($this->rauf->user)->get(route('panel.services.edit', $elvins))->assertForbidden();
        $this->actingAs($this->rauf->user)->put(route('panel.services.update', $elvins), $this->validData(['price' => '1']))->assertForbidden();
        $this->actingAs($this->rauf->user)->delete(route('panel.services.destroy', $elvins))->assertForbidden();

        $this->assertSame('20.00', $elvins->fresh()->price);
    }

    public function test_barber_updates_own_price(): void
    {
        $own = Service::factory()->for($this->rauf)->create(['price' => 15]);

        $this->actingAs($this->rauf->user)
            ->put(route('panel.services.update', $own), $this->validData(['name' => $own->name, 'price' => '18']))
            ->assertRedirect(route('panel.services.index'));

        $this->assertSame('18.00', $own->fresh()->price);
    }

    public function test_barber_without_profile_cannot_manage_services(): void
    {
        $orphan = User::factory()->barber()->create();

        $this->actingAs($orphan)->get(route('panel.services.index'))->assertForbidden();
        $this->actingAs($orphan)->post(route('panel.services.store'), $this->validData())->assertForbidden();
    }

    // ── Menecer ──────────────────────────────────────────────────────

    public function test_manager_sees_all_services_with_owner(): void
    {
        Service::factory()->for($this->rauf)->create(['name' => 'Raufun kəsimi']);
        Service::factory()->for($this->elvin)->create(['name' => 'Elvinin kəsimi']);

        $this->actingAs($this->manager)->get(route('panel.services.index'))
            ->assertOk()
            ->assertSee('Raufun kəsimi')
            ->assertSee('Elvinin kəsimi');
    }

    public function test_manager_creates_service_for_chosen_barber_with_image(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.services.store'), $this->validData([
                'barber_id' => $this->elvin->id,
                'image' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            ]))
            ->assertRedirect(route('panel.services.index'))
            ->assertSessionHas('status');

        $service = Service::sole();
        $this->assertTrue($service->barber->is($this->elvin));
        $this->assertSame('15.50', $service->price);
        $this->assertNotSame('photo.jpg', basename($service->image_path), 'Fayl adı təsadüfi olmalıdır');
        Storage::disk('public')->assertExists($service->image_path);
    }

    public function test_manager_must_choose_barber(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.services.store'), $this->validData())
            ->assertSessionHasErrors('barber_id');
    }

    public function test_same_name_for_different_barbers_gets_unique_slugs(): void
    {
        $this->actingAs($this->rauf->user)->post(route('panel.services.store'), $this->validData());
        $this->actingAs($this->elvin->user)->post(route('panel.services.store'), $this->validData());
        $this->actingAs($this->rauf->user)->post(route('panel.services.store'), $this->validData());

        $this->assertSame(['sac-kesimi-rauf', 'sac-kesimi-elvin', 'sac-kesimi-rauf-2'], Service::orderBy('id')->pluck('slug')->all());
    }

    // ── Validation, şəkil, audit ─────────────────────────────────────

    public function test_unchecked_checkbox_means_inactive(): void
    {
        $data = $this->validData();
        unset($data['is_active']);

        $this->actingAs($this->rauf->user)->post(route('panel.services.store'), $data);

        $this->assertFalse(Service::sole()->is_active);
    }

    public function test_validation_rejects_bad_price_duration_and_files(): void
    {
        $as = $this->actingAs($this->rauf->user);

        $as->post(route('panel.services.store'), $this->validData([
            'price' => '-5',
            'duration_minutes' => 7,
            'image' => UploadedFile::fake()->create('evil.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors(['price', 'duration_minutes', 'image']);

        $as->post(route('panel.services.store'), $this->validData([
            'image' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ]))->assertSessionHasErrors('image');

        $as->post(route('panel.services.store'), $this->validData([
            'image' => UploadedFile::fake()->image('huge.jpg')->size(3000), // 3 MB
        ]))->assertSessionHasErrors('image');

        $this->assertDatabaseCount('services', 0);
    }

    public function test_price_change_is_audited_and_does_not_touch_past_appointments(): void
    {
        $service = Service::factory()->for($this->rauf)->create(['price' => 15, 'duration_minutes' => 30]);
        $appointment = Appointment::factory()->for($this->rauf)->forService($service)->create();

        $this->actingAs($this->rauf->user)
            ->put(route('panel.services.update', $service), $this->validData(['name' => $service->name, 'price' => '20', 'duration_minutes' => 30]))
            ->assertRedirect(route('panel.services.index'));

        $this->assertSame('20.00', $service->fresh()->price);
        $this->assertSame('15.00', $appointment->fresh()->service_price);

        $log = AuditLog::where('action', 'service.updated')->sole();
        $this->assertSame('15.00', $log->old_values['price']);
        $this->assertSame('20.00', $log->new_values['price']);
        $this->assertSame($this->rauf->user_id, $log->user_id);
    }

    public function test_replacing_and_removing_image_deletes_old_file(): void
    {
        $as = $this->actingAs($this->rauf->user);
        $as->post(route('panel.services.store'), $this->validData(['image' => UploadedFile::fake()->image('a.jpg')]));
        $service = Service::sole();
        $first = $service->image_path;

        $as->put(route('panel.services.update', $service), $this->validData(['image' => UploadedFile::fake()->image('b.png')]));
        $second = $service->fresh()->image_path;

        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $as->put(route('panel.services.update', $service), $this->validData(['remove_image' => '1']));

        Storage::disk('public')->assertMissing($second);
        $this->assertNull($service->fresh()->image_path);
    }

    public function test_service_without_appointments_can_be_deleted(): void
    {
        $service = Service::factory()->for($this->rauf)->create();

        $this->actingAs($this->rauf->user)->delete(route('panel.services.destroy', $service))
            ->assertRedirect(route('panel.services.index'));

        $this->assertModelMissing($service);
    }

    public function test_service_with_appointments_cannot_be_deleted(): void
    {
        $service = Service::factory()->for($this->rauf)->create();
        Appointment::factory()->for($this->rauf)->forService($service)->create();

        $this->actingAs($this->manager)
            ->from(route('panel.services.edit', $service))
            ->delete(route('panel.services.destroy', $service))
            ->assertRedirect(route('panel.services.edit', $service))
            ->assertSessionHas('error', 'Bu xidmət üzrə rezervasiyalar var. Silmək əvəzinə deaktiv edin.');

        $this->assertModelExists($service);
    }
}
