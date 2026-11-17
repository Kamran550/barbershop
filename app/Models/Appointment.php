<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rezervasiya. Yaradılması və status dəyişiklikləri YALNIZ BookingService vasitəsilə (PHASE 6),
 * çünki orada lock + availability yoxlaması var. Controller-lər birbaşa create() çağırmamalıdır.
 */
class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'barber_id',
        'customer_id',
        'service_id',
        'service_name',
        'service_price',
        'service_duration',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'token_hash',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    /** Token hash heç vaxt JSON / array çıxışına düşməsin. */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'service_price' => 'decimal:2',
            'service_duration' => 'integer',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Siyahılar üçün IDOR qoruması: menecer hamısını, bərbər yalnız özününkünü görür.
     * Policy tək obyekti yoxlayır; bu scope isə siyahını DB səviyyəsində filtrləyir.
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $barberId = $user->barberId();
        $barberId === null
            ? $query->whereRaw('1 = 0') // profilə bağlanmamış bərbər heç nə görmür
            : $query->where('barber_id', $barberId);
    }

    /** Vaxtı tutan rezervasiyalar (pending + confirmed). */
    #[Scope]
    protected function occupying(Builder $query): void
    {
        $query->whereIn('status', AppointmentStatus::active());
    }

    /**
     * Başlanğıcı [from, to) intervalına düşənlər (hesabat və siyahılar üçün).
     * whereBetween istifadə olunmur: o, sonu da daxil edir və növbəti günün 00:00-ı bu günə düşərdi.
     *
     * @param  array{0: \DateTimeInterface, 1: \DateTimeInterface}  $range
     */
    #[Scope]
    protected function startingWithin(Builder $query, array $range): void
    {
        $query->where('starts_at', '>=', $range[0])->where('starts_at', '<', $range[1]);
    }

    /** [from, to) intervalı ilə kəsişənlər: a.start < to AND a.end > from */
    #[Scope]
    protected function overlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }
}
