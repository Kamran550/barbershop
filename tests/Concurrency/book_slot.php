<?php

/**
 * ConcurrentBookingTest tərəfindən AYRI PHP prosesi kimi işə salınır.
 * Hər proses Laravel-i boot edir, "start" anına qədər gözləyir (hamı eyni anda başlasın deyə)
 * və eyni slotu tutmağa çalışır. Nəticəni stdout-a yazır: OK | TAKEN | ERROR <mesaj>
 *
 * İstifadə: php book_slot.php <serviceId> <barberId> <startsAtUtc> <phone> <goAtMicrotime>
 */

use App\Exceptions\SlotUnavailableException;
use App\Models\Barber;
use App\Models\Service;
use App\Services\Booking\BookingService;
use Carbon\CarbonImmutable;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $serviceId, $barberId, $startsAt, $phone, $goAt] = $argv;

$service = Service::findOrFail($serviceId);
$barber = Barber::findOrFail($barberId);
$booking = app(BookingService::class);
App\Models\BusinessSetting::current(); // parametrləri əvvəlcədən oxu ki, start anında yalnız booking qalsın

while (microtime(true) < (float) $goAt) {
    usleep(500);
}

try {
    $booking->book($service, $barber, CarbonImmutable::parse($startsAt, 'UTC'), 'Paralel müştəri', $phone);
    echo 'OK';
} catch (SlotUnavailableException) {
    echo 'TAKEN';
} catch (Throwable $e) {
    echo 'ERROR '.$e->getMessage();
}
