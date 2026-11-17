<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_in_azerbaijani(): void
    {
        $this->get(route('panel.login'))
            ->assertOk()
            ->assertSee('İdarəetmə panelinə giriş')
            ->assertSee('name="_token"', false); // CSRF token formda var
    }

    public function test_guest_is_redirected_from_panel_to_login(): void
    {
        $this->get(route('panel.dashboard'))->assertRedirect(route('panel.login'));
    }

    public function test_manager_can_log_in_and_see_dashboard(): void
    {
        $user = User::factory()->manager()->create(['email' => 'boss@example.com']);

        $this->post(route('panel.login.store'), ['email' => 'boss@example.com', 'password' => 'password'])
            ->assertRedirect(route('panel.dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->get(route('panel.dashboard'))->assertOk()->assertSee('Xoş gəldiniz')->assertSee('Menecer');
    }

    public function test_email_is_case_insensitive(): void
    {
        User::factory()->create(['email' => 'rauf@example.com']);

        $this->post(route('panel.login.store'), ['email' => 'RAUF@Example.com', 'password' => 'password']);

        $this->assertAuthenticated();
    }

    public function test_wrong_password_is_rejected_with_generic_message(): void
    {
        User::factory()->create(['email' => 'rauf@example.com']);

        $this->from(route('panel.login'))
            ->post(route('panel.login.store'), ['email' => 'rauf@example.com', 'password' => 'wrong'])
            ->assertRedirect(route('panel.login'))
            ->assertSessionHasErrors(['email' => 'E-poçt və ya şifrə yanlışdır.']);

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in_and_gets_same_message(): void
    {
        User::factory()->inactive()->create(['email' => 'old@example.com']);

        $this->post(route('panel.login.store'), ['email' => 'old@example.com', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'E-poçt və ya şifrə yanlışdır.']);

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        User::factory()->create(['email' => 'rauf@example.com']);

        foreach (range(1, 5) as $_) {
            $this->post(route('panel.login.store'), ['email' => 'rauf@example.com', 'password' => 'wrong']);
        }

        // 6-cı cəhd: doğru şifrə ilə belə bloklanır
        $this->post(route('panel.login.store'), ['email' => 'rauf@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString('Çox sayda uğursuz cəhd', session('errors')->first('email'));
    }

    public function test_validation_messages_are_in_azerbaijani(): void
    {
        $this->post(route('panel.login.store'), [])
            ->assertSessionHasErrors(['email' => 'E-poçt mütləqdir.', 'password' => 'Şifrə mütləqdir.']);
    }

    public function test_login_regenerates_session_id(): void
    {
        User::factory()->create(['email' => 'rauf@example.com']);
        $this->get(route('panel.login'));
        $before = session()->getId();

        $this->post(route('panel.login.store'), ['email' => 'rauf@example.com', 'password' => 'password']);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_panel(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('panel.login'))
            ->assertRedirect(route('panel.dashboard'));
    }

    public function test_user_can_log_out(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('panel.logout'))
            ->assertRedirect(route('panel.login'));

        $this->assertGuest();
    }

    public function test_user_deactivated_during_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('panel.dashboard'))->assertOk();

        $user->update(['is_active' => false]);

        $this->get(route('panel.dashboard'))
            ->assertRedirect(route('panel.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
