<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Database\Seeders\BusinessSettingSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * REAL paralel double booking testi.
 *
 * Adi testlər RefreshDatabase ilə transaction daxilində işləyir — o data başqa proseslərə görünmür.
 * Burada isə data həqiqətən commit olunur (DatabaseTruncation), sonra N ayrı PHP prosesi
 * EYNİ ANDA eyni bərbərin eyni slotunu tutmağa çalışır. Lock düzgün işləyirsə:
 * yalnız biri "OK", qalanları "TAKEN" alır və DB-də tam 1 rezervasiya olur.
 *
 * Yoxlanılıb: BookingService-də lockForUpdate() sətri söndürüləndə nəticə ["OK" × 8] olur —
 * yəni test race condition-u həqiqətən tutur.
 */
#[Group('concurrency')]
class ConcurrentBookingTest extends TestCase
{
    use DatabaseTruncation;

    private const PROCESSES = 8;

    protected function tearDown(): void
    {
        // Commit olunmuş datanı təmizlə ki, RefreshDatabase istifadə edən digər testlərə təsir etməsin
        Schema::withoutForeignKeyConstraints(function () {
            foreach (['appointments', 'customers', 'working_hours', 'services', 'barbers', 'business_settings', 'audit_logs'] as $table) {
                DB::table($table)->truncate();
            }
        });

        parent::tearDown();
    }

    public function test_parallel_requests_for_same_slot_create_exactly_one_appointment(): void
    {
        $this->seed(BusinessSettingSeeder::class);

        $barber = Barber::factory()->create();
        $service = Service::factory()->for($barber)->create(['duration_minutes' => 30]);
        foreach (range(1, 7) as $weekday) {
            $barber->workingHours()->create(['weekday' => $weekday, 'start_time' => '00:00', 'end_time' => '23:55']);
        }

        // Real "indi"-yə görə: sabah Bakı vaxtı 12:00 (uşaq proseslər travelTo-nu görmür)
        $slot = CarbonImmutable::now('Asia/Baku')->addDay()->setTime(12, 0)->utc();
        $goAt = microtime(true) + 3.0; // proseslərin boot olmasına vaxt

        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'mysql',
            'DB_DATABASE' => config('database.connections.mysql.database'),
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        $processes = [];
        foreach (range(1, self::PROCESSES) as $i) {
            $phone = '05010000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT); // hər prosesə ayrı müştəri
            $processes[] = proc_open(
                [PHP_BINARY, base_path('tests/Concurrency/book_slot.php'), (string) $service->id, (string) $barber->id, $slot->toDateTimeString(), $phone, (string) $goAt],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes[$i],
                base_path(),
                $env,
            );
        }

        $results = [];
        foreach ($processes as $n => $process) {
            $i = $n + 1;
            $results[] = trim(stream_get_contents($pipes[$i][1])).trim(stream_get_contents($pipes[$i][2]));
            proc_close($process);
        }

        $counts = array_count_values($results);

        $this->assertSame(1, $counts['OK'] ?? 0, 'Nəticələr: '.json_encode($results, JSON_UNESCAPED_UNICODE));
        $this->assertSame(self::PROCESSES - 1, $counts['TAKEN'] ?? 0, 'Nəticələr: '.json_encode($results, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, Appointment::count());
    }
}
