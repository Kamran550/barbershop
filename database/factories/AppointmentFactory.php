<?php

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Barber;
use App\Models\Customer;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Testlər üçün. Real rezervasiyalar BookingService ilə yaradılır (availability yoxlaması ilə);
 * factory heç bir qayda yoxlamır — test vəziyyətini sürətlə qurmaq üçündür.
 *
 * @extends Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);

        return [
            'barber_id' => Barber::factory(),
            'customer_id' => Customer::factory(),
            'service_id' => null,
            'service_name' => 'Saç kəsimi',
            'service_price' => '15.00',
            'service_duration' => 30,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'source' => AppointmentSource::Online,
            'token_hash' => hash('sha256', Str::random(64)),
        ];
    }

    /** Xidmətdən snapshot götürür — real rezervasiya kimi. */
    public function forService(Service $service): static
    {
        return $this->state(fn (array $attributes) => [
            'service_id' => $service->id,
            'service_name' => $service->name,
            'service_price' => $service->price,
            'service_duration' => $service->duration_minutes,
            'ends_at' => CarbonImmutable::parse($attributes['starts_at'])->addMinutes($service->duration_minutes),
        ]);
    }

    public function at(CarbonImmutable $startsAt, int $durationMinutes = 30): static
    {
        return $this->state([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes($durationMinutes),
            'service_duration' => $durationMinutes,
        ]);
    }

    public function status(AppointmentStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'cancelled_at' => $status === AppointmentStatus::Cancelled ? now() : null,
        ]);
    }
}
