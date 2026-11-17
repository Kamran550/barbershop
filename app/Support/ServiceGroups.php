<?php

namespace App\Support;

use App\Models\Service;
use Illuminate\Support\Collection;

/**
 * Müxtəlif bərbərlərin eyni adlı xidmətlərini bir qrupda birləşdirir — public qiymət siyahısı
 * və "Fərq etməz" seçimi üçün:
 *
 *   Raufun "Saç kəsimi" 20 ₼ + Elvinin "Saç kəsimi" 15 ₼  →  "Saç kəsimi · 15–20 ₼"
 */
class ServiceGroups
{
    /**
     * @param  Collection<int, Service>  $services  bookable xidmətlər (barber eager-load olunmuş)
     * @return Collection<int, array{key: string, name: string, description: ?string, price: string, duration: string, minPrice: float, serviceIds: list<int>, barberIds: list<int>, representativeId: int}>
     */
    public static function from(Collection $services): Collection
    {
        return $services
            ->groupBy(fn (Service $s) => $s->groupKey())
            ->map(function (Collection $group, string $key) {
                $prices = $group->map(fn (Service $s) => (float) $s->price);
                $durations = $group->pluck('duration_minutes');

                return [
                    'key' => $key,
                    'name' => $group->first()->name,
                    'description' => $group->pluck('description')->filter()->first(),
                    'price' => self::range($prices->min(), $prices->max(), fn ($v) => Money::format($v)),
                    'duration' => self::range($durations->min(), $durations->max(), fn ($v) => $v).' dəq',
                    'minPrice' => $prices->min(),
                    'serviceIds' => $group->pluck('id')->values()->all(),
                    'barberIds' => $group->pluck('barber_id')->unique()->values()->all(),
                    // Slots və POST üçün qrupun "nümayəndəsi": server onun adına görə qrupu yenidən tapır
                    'representativeId' => $group->first()->id,
                ];
            })
            ->sortBy([['minPrice', 'asc'], ['name', 'asc']])
            ->values();
    }

    /** 15, 20 → "15–20 ₼";  15, 15 → "15 ₼" */
    private static function range(float|int $min, float|int $max, callable $format): string
    {
        if ($min == $max) {
            return $format($min);
        }

        // "15 ₼–20 ₼" əvəzinə "15–20 ₼"
        return str_replace(' ₼', '', (string) $format($min)).'–'.$format($max);
    }
}
