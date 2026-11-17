<?php

namespace App\Services\Booking;

use App\Models\Appointment;

/**
 * Rezervasiya nəticəsi. $token yalnız BU anda məlumdur (DB-də yalnız hash-i var) —
 * müştəriyə link kimi verilməlidir: /rezervasiya/{token}
 */
final class BookingResult
{
    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $token,
    ) {}
}
