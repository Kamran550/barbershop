<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * role middleware-i test route-ları üzərində yoxlanır
 * (real menecer səhifələri PHASE 5-də yaranacaq və onların öz testləri olacaq).
 */
class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'active', 'role:manager'])->get('/_test/manager-only', fn () => 'ok');
        Route::middleware(['web', 'auth', 'active', 'role:manager,barber'])->get('/_test/staff', fn () => 'ok');
    }

    public function test_manager_can_access_manager_route(): void
    {
        $this->actingAs(User::factory()->manager()->create())->get('/_test/manager-only')->assertOk();
    }

    public function test_barber_gets_403_on_manager_route(): void
    {
        $this->actingAs(User::factory()->barber()->create())->get('/_test/manager-only')->assertForbidden();
    }

    public function test_multiple_roles_are_supported(): void
    {
        $this->actingAs(User::factory()->barber()->create())->get('/_test/staff')->assertOk();
    }

    public function test_guest_is_redirected_not_forbidden(): void
    {
        $this->get('/_test/manager-only')->assertRedirect(route('panel.login'));
    }
}
