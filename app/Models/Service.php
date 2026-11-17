<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Bərbərə məxsus xidmət: hər bərbər öz adı, qiyməti və müddəti ilə.
 *
 * barber_id BİLƏRƏKDƏN fillable-da deyil: bərbər formadan barber_id göndərib xidməti başqasının
 * adına keçirə bilməsin (mass assignment qoruması). Sahib yalnız service qatında açıq təyin olunur.
 */
class Service extends Model
{
    /** @use HasFactory<\Database\Factories\ServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'duration_minutes',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2', // string kimi qaytarılır — float dəqiqlik itkisi olmur
            'duration_minutes' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * "Fərq etməz" seçimi üçün qrup açarı: eyni adlı xidmətlər (Raufun və Elvinin "Saç kəsimi") bir qrupdur.
     * "Saç kəsimi" → "sac-kesimi"
     */
    public function groupKey(): string
    {
        return self::groupKeyFor($this->name);
    }

    public static function groupKeyFor(string $name): string
    {
        return Str::slug($name);
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    /** Aktiv bərbərin aktiv xidmətləri — public saytda göstərilə bilənlər. */
    #[Scope]
    protected function bookable(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('barber', fn (Builder $q) => $q->where('is_active', true));
    }

    /** Siyahılar üçün: menecer hamısını, bərbər yalnız öz xidmətlərini görür. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $barberId = $user->barberId();
        $barberId === null ? $query->whereRaw('1 = 0') : $query->where('barber_id', $barberId);
    }
}
