<?php

namespace Tests\Feature\Panel;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\User;
use Tests\Feature\Booking\BookingScenario;

/**
 * Panel testləri üçün: BookingScenario (Rauf, Elvin, xidmətlər, 2030-05-06 10:00 Bakı) + login hesabları.
 */
trait PanelScenario
{
    use BookingScenario;

    protected User $manager;

    protected function setUpPanel(): void
    {
        $this->setUpScenario();
        $this->manager = User::factory()->manager()->create();

        foreach ([$this->rauf, $this->elvin] as $barber) {
            $barber->update(['user_id' => User::factory()->barber()->create(['name' => $barber->name])->id]);
        }
    }

    protected function userOf(Barber $barber): User
    {
        return $barber->fresh()->user;
    }

    /** Rezervasiya (factory ilə, qaydasız) — Bakı vaxtı ilə. */
    protected function appointment(Barber $barber, string $bakuTime, AppointmentStatus $status = AppointmentStatus::Confirmed, ?Customer $customer = null, float $price = 15): Appointment
    {
        return Appointment::factory()
            ->for($barber)
            ->for($customer ?? Customer::factory()->create())
            ->at($this->baku($bakuTime)->utc())
            ->status($status)
            ->create(['service_price' => $price, 'service_name' => 'Saç kəsimi']);
    }
}
