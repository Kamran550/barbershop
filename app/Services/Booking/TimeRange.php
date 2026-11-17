<?php

namespace App\Services\Booking;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Yarımaçıq zaman intervalı [start, end): start daxildir, end daxil deyil.
 * Ona görə 10:00–10:30 və 10:30–11:00 kəsişmir (bitişikdir).
 *
 * Availability engine-in bütün riyaziyyatı bu kiçik class-dadır: DB-dən asılı deyil,
 * unit testlə tam yoxlanır.
 */
final class TimeRange
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
    ) {
        if ($end <= $start) {
            throw new InvalidArgumentException('TimeRange: end start-dan sonra olmalıdır.');
        }
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $this->end > $other->start;
    }

    public function contains(self $other): bool
    {
        return $other->start >= $this->start && $other->end <= $this->end;
    }

    /**
     * Bu intervaldan $cut-ı çıxır: 0, 1 və ya 2 hissə qalır.
     *
     *   [10:00 ─────────────── 20:00]  minus  [14:00 ── 15:00]
     *   = [10:00 ── 14:00] + [15:00 ── 20:00]
     *
     * @return list<self>
     */
    public function subtract(self $cut): array
    {
        if (! $this->overlaps($cut)) {
            return [$this];
        }

        $parts = [];
        if ($cut->start > $this->start) {
            $parts[] = new self($this->start, $cut->start);
        }
        if ($cut->end < $this->end) {
            $parts[] = new self($cut->end, $this->end);
        }

        return $parts;
    }

    /**
     * @param  list<self>  $ranges
     * @param  iterable<self>  $cuts
     * @return list<self>
     */
    public static function subtractAll(array $ranges, iterable $cuts): array
    {
        foreach ($cuts as $cut) {
            $next = [];
            foreach ($ranges as $range) {
                array_push($next, ...$range->subtract($cut));
            }
            $ranges = $next;
        }

        return $ranges;
    }
}
