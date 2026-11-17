<?php

namespace App\Services\Booking;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rezervasiyanın yaradılması və həyat dövrü. Public sayt da, panel də YALNIZ bu class-dan istifadə edir —
 * double booking qorunması bir yerdə yazılıb və hər yolda işləyir.
 *
 * DOUBLE BOOKING QORUNMASI
 *   DB::transaction {
 *       SELECT … FROM barbers WHERE id = ? FOR UPDATE   ← eyni bərbərə paralel request-lər burada növbəyə düzülür
 *       availability YENİDƏN yoxlanır                   ← növbədən çıxan ikinci request birincinin yazdığını görür
 *       INSERT appointment
 *   }                                                   ← commit: lock açılır
 *
 * VACİB: transaction daxilində lock-dan ƏVVƏL heç bir adi SELECT olmamalıdır. InnoDB (REPEATABLE READ)
 * "snapshot"-u ilk adi oxunuşda götürür; lock-dan əvvəl götürülsə, ikinci request birincinin
 * commit etdiyi rezervasiyanı görməz. Ona görə settings və digər oxunuşlar transaction-dan kənarda,
 * lock isə transaction-ın ilk sorğusudur.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  Barber|null  $barber  null = "fərq etməz", ilk uyğun boş bərbər seçilir
     *
     * @throws BusinessRuleException
     */
    public function book(
        Service $service,
        ?Barber $barber,
        CarbonImmutable $startsAt,
        string $customerName,
        string $customerPhone,
        AppointmentSource $source = AppointmentSource::Online,
        ?User $actor = null,
    ): BookingResult {
        $phone = PhoneNumber::normalize($customerPhone)
            ?? throw new BusinessRuleException('Telefon nömrəsi düzgün deyil. Nümunə: 050 123 45 67');

        if (! $service->is_active && $source === AppointmentSource::Online) {
            throw new BusinessRuleException('Bu xidmət hazırda mövcud deyil.');
        }

        $settings = BusinessSetting::current(); // transaction-dan ƏVVƏL oxunur (yuxarıdakı qeydə bax)
        $startsAt = $startsAt->utc()->startOfMinute();

        if ($barber) {
            // Xidmət bərbərə məxsusdur: başqa bərbərin xidmətini bu bərbərə yazmaq olmaz
            if (! $barber->is_active || ! $this->availability->offers($barber, $service)) {
                throw new BusinessRuleException('Seçilmiş bərbər bu xidməti göstərmir.');
            }
            $candidates = collect([$service->setRelation('barber', $barber)]);
        } else {
            // "Fərq etməz": eyni adlı xidməti göstərən bərbərlər, ən az yüklü əvvəl.
            // Hər namizəd ÖZ xidməti ilə (öz qiyməti və müddəti) — snapshot da ondan götürülür.
            $candidates = $this->availability->groupServices($service, $startsAt);
        }

        if ($candidates->isEmpty()) {
            throw new SlotUnavailableException('Bu xidmət üçün hazırda aktiv bərbər yoxdur.');
        }

        foreach ($candidates as $candidateService) {
            $result = DB::transaction(fn () => $this->attempt(
                $candidateService->barber, $candidateService, $startsAt, trim($customerName), $phone, $source, $actor, $settings,
            ));

            if ($result) {
                return $result;
            }
        }

        throw new SlotUnavailableException;
    }

    /** Bir bərbər üçün cəhd. Vaxt boş deyilsə null (növbəti bərbər yoxlansın). */
    private function attempt(
        Barber $barber,
        Service $service,
        CarbonImmutable $startsAt,
        string $name,
        string $phone,
        AppointmentSource $source,
        ?User $actor,
        BusinessSetting $settings,
    ): ?BookingResult {
        // 1) LOCK — transaction-ın ilk sorğusu
        Barber::query()->whereKey($barber->id)->lockForUpdate()->firstOrFail();

        // 2) Availability lock altında yenidən yoxlanır
        $available = $source === AppointmentSource::Online
            ? $this->availability->isBookableSlot($barber, $service, $startsAt)
            : $this->availability->isFreeForManualBooking($barber, $startsAt, $service->duration_minutes);

        if (! $available) {
            return null;
        }

        $endsAt = $startsAt->addMinutes($service->duration_minutes);

        // 3) Eyni telefon nömrəsi qaydaları
        $customer = Customer::query()->where('phone', $phone)->first();
        if ($customer) {
            $this->guardCustomerLimits($customer, $startsAt, $endsAt, $source, $settings);
        }

        // createOrFirst: unique(phone) ilə yarış vəziyyətində də dublikat müştəri yaranmır
        $customer ??= Customer::createOrFirst(['phone' => $phone], ['name' => $name]);
        if ($customer->name !== $name && $name !== '') {
            $customer->update(['name' => $name]); // müştəri adını yeniləyibsə, ən sonuncu saxlanır
        }

        // 4) Rezervasiya + snapshot + token
        $token = Str::random(48);

        $appointment = Appointment::create([
            'barber_id' => $barber->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'service_name' => $service->name,
            'service_price' => $service->price,
            'service_duration' => $service->duration_minutes,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $source === AppointmentSource::Manual || $settings->auto_confirm
                ? AppointmentStatus::Confirmed
                : AppointmentStatus::Pending,
            'source' => $source,
            'token_hash' => self::hashToken($token),
            'created_by' => $actor?->id,
        ]);

        $this->audit->log('appointment.created', $appointment, new: [
            'barber_id' => $barber->id,
            'service' => $service->name,
            'starts_at' => $startsAt->toDateTimeString(),
            'source' => $source->value,
        ]);

        // PHASE 9: Telegram bildirişi burada, DB::afterCommit ilə göndəriləcək

        return new BookingResult($appointment->setRelation('barber', $barber)->setRelation('customer', $customer), $token);
    }

    private function guardCustomerLimits(Customer $customer, CarbonImmutable $startsAt, CarbonImmutable $endsAt, AppointmentSource $source, BusinessSetting $settings): void
    {
        $active = Appointment::query()->whereBelongsTo($customer)->occupying();

        if ((clone $active)->overlapping($startsAt, $endsAt)->exists()) {
            throw new BusinessRuleException('Bu vaxtda artıq rezervasiyanız var.');
        }

        // Limit yalnız onlayn rezervasiyaya aiddir (spam qoruması); menecer istədiyi qədər yaza bilər
        if ($source === AppointmentSource::Online
            && (clone $active)->where('starts_at', '>', now())->count() >= $settings->max_active_bookings_per_phone) {
            throw new BusinessRuleException(
                "Bu nömrə ilə artıq {$settings->max_active_bookings_per_phone} aktiv rezervasiya var. Yenisi üçün mövcud rezervasiyalardan birini ləğv edin."
            );
        }
    }

    // ── Həyat dövrü ─────────────────────────────────────────────────

    /**
     * @param  User|null  $actor  null = müştəri öz linki ilə ləğv edir (deadline qaydası tətbiq olunur)
     */
    public function cancel(Appointment $appointment, ?User $actor = null, ?string $reason = null): Appointment
    {
        $settings = BusinessSetting::current();

        return DB::transaction(function () use ($appointment, $actor, $reason, $settings) {
            $appointment = Appointment::query()->whereKey($appointment->id)->lockForUpdate()->firstOrFail();

            if (! $appointment->status->canTransitionTo(AppointmentStatus::Cancelled)) {
                throw new BusinessRuleException('Bu rezervasiya artıq '.mb_strtolower($appointment->status->label()).' — ləğv etmək mümkün deyil.');
            }

            if ($actor === null) {
                $deadline = $appointment->starts_at->subMinutes($settings->cancel_deadline_minutes);
                if (now()->greaterThan($deadline)) {
                    $hours = intdiv($settings->cancel_deadline_minutes, 60);
                    throw new BusinessRuleException("Rezervasiyanı başlamağa {$hours} saatdan az qalmış onlayn ləğv etmək olmaz. Zəhmət olmasa bizimlə əlaqə saxlayın.");
                }
            }

            $old = $appointment->status;
            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason ?? ($actor ? null : 'Müştəri ləğv etdi'),
            ]);

            $this->audit->log('appointment.cancelled', $appointment, ['status' => $old->value], [
                'status' => AppointmentStatus::Cancelled->value,
                'by' => $actor ? 'staff' : 'customer',
                'reason' => $appointment->cancellation_reason,
            ]);

            return $appointment;
        });
    }

    /** Təsdiqlə / tamamlandı / gəlmədi. Ləğv üçün cancel() istifadə olunur. */
    public function changeStatus(Appointment $appointment, AppointmentStatus $to, User $actor): Appointment
    {
        if ($to === AppointmentStatus::Cancelled) {
            return $this->cancel($appointment, $actor);
        }

        if (! $appointment->status->canTransitionTo($to)) {
            throw new BusinessRuleException("«{$appointment->status->label()}» statusundan «{$to->label()}» statusuna keçmək olmaz.");
        }

        if (in_array($to, [AppointmentStatus::Completed, AppointmentStatus::NoShow], true) && $appointment->starts_at->isFuture()) {
            throw new BusinessRuleException('Rezervasiyanın vaxtı hələ çatmayıb.');
        }

        $old = $appointment->status;
        $appointment->update(['status' => $to]);
        $this->audit->log('appointment.status_changed', $appointment, ['status' => $old->value], ['status' => $to->value]);

        return $appointment;
    }

    /**
     * Başqa vaxta və/və ya başqa bərbərə keçirmə (menecer). Snapshot dəyişmir — xidmət eyni qalır.
     */
    public function reschedule(Appointment $appointment, CarbonImmutable $newStartsAt, ?Barber $newBarber, User $actor): Appointment
    {
        if (! $appointment->status->occupiesSlot()) {
            throw new BusinessRuleException('Yalnız aktiv rezervasiyanın vaxtını dəyişmək olar.');
        }

        $newStartsAt = $newStartsAt->utc()->startOfMinute();
        $targetBarber = $newBarber ?? $appointment->barber;

        if (! $targetBarber->is_active) {
            throw new BusinessRuleException('Seçilmiş bərbər aktiv deyil.');
        }
        // Başqa bərbərə keçirəndə: onun da eyni adlı aktiv xidməti olmalıdır.
        // Qiymət snapshot-dakı kimi qalır — müştəri ilə razılaşdırılan qiymət dəyişmir.
        if ($targetBarber->id !== $appointment->barber_id
            && ! $targetBarber->services()->active()->where('name', $appointment->service_name)->exists()) {
            throw new BusinessRuleException('Seçilmiş bərbər bu xidməti göstərmir.');
        }

        BusinessSetting::current(); // transaction-dan əvvəl

        return DB::transaction(function () use ($appointment, $newStartsAt, $targetBarber, $actor) {
            Barber::query()->whereKey($targetBarber->id)->lockForUpdate()->firstOrFail();

            // Rezervasiyanın özü öz köhnə vaxtına mane olmasın — ignore edilir
            if (! $this->availability->isFreeForManualBooking($targetBarber, $newStartsAt, $appointment->service_duration, $appointment->id)) {
                throw new SlotUnavailableException;
            }

            $old = ['barber_id' => $appointment->barber_id, 'starts_at' => $appointment->starts_at->toDateTimeString()];

            $appointment->update([
                'barber_id' => $targetBarber->id,
                'starts_at' => $newStartsAt,
                'ends_at' => $newStartsAt->addMinutes($appointment->service_duration),
            ]);

            $this->audit->log('appointment.rescheduled', $appointment, $old, [
                'barber_id' => $targetBarber->id,
                'starts_at' => $newStartsAt->toDateTimeString(),
            ]);

            return $appointment->setRelation('barber', $targetBarber);
        });
    }

    public function findByToken(string $token): ?Appointment
    {
        if (strlen($token) !== 48) {
            return null;
        }

        return Appointment::query()->where('token_hash', self::hashToken($token))->first();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
