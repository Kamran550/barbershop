<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PHASE 3: database strukturunun və data integrity qaydalarının testləri.
 * Bu testlər DB-nin özünün (FK, unique, CHECK) səhv datanı rədd etdiyini yoxlayır.
 */
class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_settings_staff_services_and_schedules(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame('Asia/Baku', BusinessSetting::current()->timezone);
        $this->assertSame(1, User::where('role', UserRole::Manager)->count());
        $this->assertSame(3, Barber::count());
        $this->assertSame(4 + 5 + 5, Service::count()); // hər bərbərin öz xidmətləri

        $rauf = Barber::where('slug', 'rauf')->firstOrFail();
        $this->assertTrue($rauf->user->isBarber());
        $this->assertCount(4, $rauf->services);
        $this->assertCount(6, $rauf->workingHours);
        $this->assertCount(6, $rauf->breaks);

        // Eyni xidmət, fərqli qiymət
        $elvin = Barber::where('slug', 'elvin')->firstOrFail();
        $this->assertSame('20.00', $rauf->services->firstWhere('name', 'Saç kəsimi')->price);
        $this->assertSame('15.00', $elvin->services->firstWhere('name', 'Saç kəsimi')->price);

        $tural = Barber::where('slug', 'tural')->firstOrFail();
        $this->assertNull($tural->user_id, 'Tural login hesabı olmayan bərbərdir');
        $this->assertFalse($tural->services->contains('name', 'Saç boyama'));
    }

    public function test_service_belongs_to_barber_and_is_deleted_with_barber(): void
    {
        $barber = Barber::factory()->create();
        $service = Service::factory()->for($barber)->create();

        $this->assertTrue($service->barber->is($barber));

        $barber->delete();
        $this->assertModelMissing($service);
    }

    public function test_barber_id_is_not_mass_assignable_on_service(): void
    {
        $service = Service::factory()->create();
        $originalOwner = $service->barber_id;

        // Strict mode (production xaric): icazəsiz sahə səssizcə atılmır, xəta verir
        try {
            $service->update(['barber_id' => Barber::factory()->create()->id, 'name' => 'Yeni ad']);
            $this->fail('barber_id mass assignment ilə dəyişməməli idi');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame($originalOwner, $service->fresh()->barber_id);
    }

    public function test_barber_cannot_have_two_schedules_for_same_weekday(): void
    {
        $barber = Barber::factory()->create();
        WorkingHour::create(['barber_id' => $barber->id, 'weekday' => 1, 'start_time' => '10:00', 'end_time' => '18:00']);

        $this->expectException(QueryException::class);
        WorkingHour::create(['barber_id' => $barber->id, 'weekday' => 1, 'start_time' => '12:00', 'end_time' => '20:00']);
    }

    public function test_working_hours_end_must_be_after_start(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);
        WorkingHour::create(['barber_id' => $barber->id, 'weekday' => 2, 'start_time' => '18:00', 'end_time' => '10:00']);
    }

    public function test_weekday_must_be_between_1_and_7(): void
    {
        $barber = Barber::factory()->create();

        $this->expectException(QueryException::class);
        WorkingHour::create(['barber_id' => $barber->id, 'weekday' => 8, 'start_time' => '10:00', 'end_time' => '18:00']);
    }

    public function test_custom_hours_exception_requires_valid_times(): void
    {
        $barber = Barber::factory()->create();

        // İzin günü — saat lazım deyil
        AvailabilityException::create(['barber_id' => $barber->id, 'date' => '2030-01-01', 'is_day_off' => true]);

        // Xüsusi saat — amma saat yoxdur
        $this->expectException(QueryException::class);
        AvailabilityException::create(['barber_id' => $barber->id, 'date' => '2030-01-02', 'is_day_off' => false]);
    }

    public function test_appointment_end_must_be_after_start(): void
    {
        $this->expectException(QueryException::class);

        $appointment = Appointment::factory()->create();
        DB::table('appointments')->where('id', $appointment->id)->update(['ends_at' => $appointment->starts_at]);
    }

    public function test_invalid_status_is_rejected_by_database(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('appointments')->where('id', $appointment->id)->update(['status' => 'deleted']);
    }

    public function test_customer_phone_is_unique(): void
    {
        Customer::factory()->create(['phone' => '+994501234567']);

        $this->expectException(QueryException::class);
        Customer::factory()->create(['phone' => '+994501234567']);
    }

    public function test_service_snapshot_survives_price_change_and_deletion(): void
    {
        $service = Service::factory()->create(['name' => 'Saç kəsimi', 'price' => 15, 'duration_minutes' => 30]);
        $appointment = Appointment::factory()->forService($service)->create();

        $service->update(['price' => 25]);
        $this->assertSame('15.00', $appointment->fresh()->service_price);

        $service->delete();
        $appointment->refresh();
        $this->assertNull($appointment->service_id);
        $this->assertSame('Saç kəsimi', $appointment->service_name);
        $this->assertSame('15.00', $appointment->service_price);
        $this->assertSame(30, $appointment->service_duration);
    }

    public function test_barber_with_appointments_cannot_be_deleted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);
        $appointment->barber->delete();
    }

    public function test_customer_with_appointments_cannot_be_deleted(): void
    {
        $appointment = Appointment::factory()->create();

        $this->expectException(QueryException::class);
        $appointment->customer->delete();
    }

    public function test_deleting_barber_without_appointments_cascades_schedule(): void
    {
        $barber = Barber::factory()->create();
        $barber->workingHours()->create(['weekday' => 1, 'start_time' => '10:00', 'end_time' => '18:00']);
        $barber->breaks()->create(['weekday' => 1, 'start_time' => '13:00', 'end_time' => '14:00']);

        $barber->delete();

        $this->assertDatabaseCount('working_hours', 0);
        $this->assertDatabaseCount('breaks', 0);
    }

    public function test_deleting_user_keeps_barber_profile(): void
    {
        $barber = Barber::factory()->withUser()->create();

        $barber->user->delete();

        $this->assertNull($barber->fresh()->user_id);
    }

    public function test_token_hash_is_never_serialized(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertArrayNotHasKey('token_hash', $appointment->toArray());
    }

    public function test_telegram_token_is_encrypted_at_rest(): void
    {
        $this->seed(DatabaseSeeder::class);
        BusinessSetting::current()->update(['telegram_bot_token' => '123456:SECRET']);

        $raw = DB::table('business_settings')->value('telegram_bot_token');

        $this->assertNotSame('123456:SECRET', $raw);
        $this->assertStringNotContainsString('SECRET', $raw);
        $this->assertSame('123456:SECRET', BusinessSetting::query()->first()->telegram_bot_token);
    }

    public function test_occupying_and_overlapping_scopes(): void
    {
        $barber = Barber::factory()->create();
        $start = now('UTC')->addDay()->setTime(10, 0)->toImmutable();

        Appointment::factory()->for($barber)->at($start, 30)->create();                                          // 10:00–10:30
        Appointment::factory()->for($barber)->at($start->addHour(), 30)->status(AppointmentStatus::Cancelled)->create(); // 11:00–11:30 ləğv

        $hits = fn ($from, $to) => Appointment::query()->whereBelongsTo($barber)->occupying()->overlapping($from, $to)->count();

        $this->assertSame(1, $hits($start->addMinutes(15), $start->addMinutes(45)));  // kəsişir
        $this->assertSame(0, $hits($start->addMinutes(30), $start->addMinutes(60)));  // bitişik — kəsişmir
        $this->assertSame(0, $hits($start->addHour(), $start->addMinutes(90)));       // ləğv edilmiş vaxtı tutmur
    }
}
