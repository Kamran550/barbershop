<?php

namespace Tests\Feature\Panel;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BarberManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->manager = User::factory()->manager()->create();
    }

    public function test_barber_user_cannot_manage_barbers(): void
    {
        $barber = Barber::factory()->withUser()->create();

        $this->actingAs($barber->user)->get(route('panel.barbers.index'))->assertForbidden();
        $this->actingAs($barber->user)->get(route('panel.barbers.edit', $barber))->assertForbidden();
        $this->actingAs($barber->user)->put(route('panel.barbers.update', $barber), ['name' => 'Hack'])->assertForbidden();

        $this->assertNotSame('Hack', $barber->fresh()->name);
    }

    public function test_manager_creates_barber_with_account_and_photo(): void
    {
        $this->actingAs($this->manager)->post(route('panel.barbers.store'), [
            'name' => 'Rauf',
            'title' => 'Baş bərbər',
            'is_active' => '1',
            'email' => 'RAUF@Example.com',
            'password' => 'secret123',
            'photo' => UploadedFile::fake()->image('rauf.jpg', 600, 600),
        ])->assertRedirect();

        $barber = Barber::with('user')->sole();
        $this->assertSame('rauf', $barber->slug);
        Storage::disk('public')->assertExists($barber->photo_path);

        $this->assertSame('rauf@example.com', $barber->user->email);
        $this->assertSame(UserRole::Barber, $barber->user->role);
        $this->assertTrue(Hash::check('secret123', $barber->user->password));

        // Yeni hesab dərhal işləyir
        $this->post(route('panel.logout'));
        $this->post(route('panel.login.store'), ['email' => 'rauf@example.com', 'password' => 'secret123']);
        $this->assertAuthenticatedAs($barber->user);
    }

    public function test_barber_can_be_created_without_account(): void
    {
        $this->actingAs($this->manager)->post(route('panel.barbers.store'), ['name' => 'Tural', 'is_active' => '1'])->assertRedirect();

        $this->assertNull(Barber::sole()->user_id);
        $this->assertSame(1, User::count()); // yalnız menecer
    }

    public function test_new_account_requires_strong_password_and_unique_email(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.barbers.store'), ['name' => 'Elvin', 'email' => 'elvin@example.com'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->manager)
            ->post(route('panel.barbers.store'), ['name' => 'Elvin', 'email' => 'elvin@example.com', 'password' => 'short'])
            ->assertSessionHasErrors('password');

        $this->actingAs($this->manager)
            ->post(route('panel.barbers.store'), ['name' => 'Elvin', 'email' => $this->manager->email, 'password' => 'secret123'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('barbers', 0);
    }

    public function test_failed_validation_leaves_no_orphan_records(): void
    {
        $this->actingAs($this->manager)
            ->post(route('panel.barbers.store'), ['name' => 'Elvin', 'email' => 'not-an-email', 'photo' => UploadedFile::fake()->image('a.jpg')])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('barbers', 0);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Yetim şəkil qalmamalıdır');
    }

    public function test_deactivating_barber_deactivates_account_and_kicks_them_out(): void
    {
        $barber = Barber::factory()->withUser()->create();
        $barberUser = $barber->user;

        $this->actingAs($this->manager)->put(route('panel.barbers.update', $barber), [
            'name' => $barber->name,
            'email' => $barberUser->email,
            // is_active göndərilmir = checkbox seçilməyib
        ])->assertRedirect(route('panel.barbers.index'));

        $this->assertFalse($barber->fresh()->is_active);
        $this->assertFalse($barberUser->fresh()->is_active);

        $this->actingAs($barberUser->fresh())->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    }

    public function test_password_is_kept_when_left_blank_and_changed_when_given(): void
    {
        $barber = Barber::factory()->withUser()->create();
        $original = $barber->user->password;

        $payload = ['name' => $barber->name, 'email' => $barber->user->email, 'is_active' => '1'];

        $this->actingAs($this->manager)->put(route('panel.barbers.update', $barber), $payload);
        $this->assertSame($original, $barber->user->fresh()->password);

        $this->actingAs($this->manager)->put(route('panel.barbers.update', $barber), [...$payload, 'password' => 'newpass123']);
        $this->assertTrue(Hash::check('newpass123', $barber->user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', ['new_values' => json_encode(['password' => 'newpass123'])]);
    }

    public function test_manager_edit_page_lists_barbers_own_services(): void
    {
        $barber = Barber::factory()->create();
        Service::factory()->for($barber)->create(['name' => 'Ülgüclə qırxım', 'price' => 12]);

        $this->actingAs($this->manager)->get(route('panel.barbers.edit', $barber))
            ->assertOk()
            ->assertSee('Ülgüclə qırxım')
            ->assertSee('12 ₼');
    }

    public function test_deleting_barber_without_appointments_deletes_their_services(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->for($barber)->create();

        $this->actingAs($this->manager)->delete(route('panel.barbers.destroy', $barber));

        $this->assertModelMissing($service);
    }

    public function test_barber_without_appointments_is_deleted_with_account(): void
    {
        $barber = Barber::factory()->withUser()->create();
        $userId = $barber->user_id;

        $this->actingAs($this->manager)->delete(route('panel.barbers.destroy', $barber))->assertRedirect(route('panel.barbers.index'));

        $this->assertModelMissing($barber);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }

    public function test_barber_with_appointments_cannot_be_deleted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs($this->manager)
            ->delete(route('panel.barbers.destroy', $appointment->barber))
            ->assertSessionHas('error');

        $this->assertModelExists($appointment->barber);
    }
}
