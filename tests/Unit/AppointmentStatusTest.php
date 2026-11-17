<?php

namespace Tests\Unit;

use App\Enums\AppointmentStatus as S;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    public function test_allowed_transitions(): void
    {
        $this->assertTrue(S::Pending->canTransitionTo(S::Confirmed));
        $this->assertTrue(S::Pending->canTransitionTo(S::Cancelled));
        $this->assertTrue(S::Confirmed->canTransitionTo(S::Completed));
        $this->assertTrue(S::Confirmed->canTransitionTo(S::NoShow));
        $this->assertTrue(S::Confirmed->canTransitionTo(S::Cancelled));
    }

    public function test_final_states_cannot_change(): void
    {
        foreach ([S::Completed, S::Cancelled, S::NoShow] as $final) {
            foreach (S::cases() as $next) {
                $this->assertFalse($final->canTransitionTo($next), "{$final->value} → {$next->value}");
            }
        }
        $this->assertFalse(S::Pending->canTransitionTo(S::Completed), 'Təsdiqlənməmiş rezervasiya tamamlana bilməz');
    }

    public function test_only_pending_and_confirmed_occupy_slot(): void
    {
        $this->assertTrue(S::Pending->occupiesSlot());
        $this->assertTrue(S::Confirmed->occupiesSlot());
        $this->assertFalse(S::Cancelled->occupiesSlot());
        $this->assertFalse(S::Completed->occupiesSlot());
        $this->assertFalse(S::NoShow->occupiesSlot());
    }
}
