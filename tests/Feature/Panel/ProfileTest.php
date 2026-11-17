<?php

namespace Tests\Feature\Panel;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Bərbərin öz profili: şəxsi sahələr və şifrə — aktivlik / email / sıra isə menecerdə qalır. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->barber = Barber::factory()->withUser()->create(['name' => 'Rauf', 'sort_order' => 5]);
    }

    public function test_barber_updates_own_profile_and_photo(): void
    {
        $this->actingAs($this->barber->user)
            ->put(route('panel.profile.update'), [
                'name' => 'Rauf Məmmədov',
                'title' => 'Baş bərbər',
                'bio' => 'Klassik kəsimlər',
                'photo' => UploadedFile::fake()->image('me.jpg', 500, 500),
            ])
            ->assertRedirect(route('panel.profile.edit'))
            ->assertSessionHas('status');

        $barber = $this->barber->fresh();
        $this->assertSame('Baş bərbər', $barber->title);
        $this->assertSame('rauf-memmedov', $barber->slug);
        Storage::disk('public')->assertExists($barber->photo_path);
        $this->assertSame('Rauf Məmmədov', $barber->user->name, 'Login hesabının adı da sinxron olur');
        $this->assertDatabaseHas('audit_logs', ['action' => 'barber.profile_updated']);
    }

    public function test_barber_cannot_escalate_via_profile_form(): void
    {
        $this->actingAs($this->barber->user)->put(route('panel.profile.update'), [
            'name' => 'Rauf',
            'is_active' => '0',
            'sort_order' => '0',
            'user_id' => 999,
            'email' => 'hacker@example.com',
        ])->assertSessionHasNoErrors();

        $barber = $this->barber->fresh();
        $this->assertTrue($barber->is_active);
        $this->assertSame(5, $barber->sort_order);
        $this->assertNotSame('hacker@example.com', $barber->user->email);
    }

    public function test_manager_has_no_barber_profile_page(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get(route('panel.profile.edit'))->assertForbidden();
    }

    public function test_barber_changes_password_with_current_password(): void
    {
        $user = $this->barber->user;

        $this->actingAs($user)
            ->put(route('panel.profile.password'), ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrorsIn('password', ['current_password' => 'Şifrə yanlışdır.']);

        $this->actingAs($user)
            ->put(route('panel.profile.password'), ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Şifrəniz dəyişdirildi.');

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_changed']);
    }

    public function test_new_password_must_be_strong_and_confirmed(): void
    {
        $this->actingAs($this->barber->user)
            ->put(route('panel.profile.password'), ['current_password' => 'password', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrorsIn('password', 'password');
    }
}
