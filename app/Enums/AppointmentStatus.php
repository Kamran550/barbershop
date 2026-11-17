<?php

namespace App\Enums;

/**
 * Rezervasiyanın həyat dövrü:
 *
 *   pending ─► confirmed ─► completed
 *      │           ├──────► no_show
 *      └───────────┴──────► cancelled
 */
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Gözləmədə',
            self::Confirmed => 'Təsdiqlənib',
            self::Completed => 'Tamamlanıb',
            self::Cancelled => 'Ləğv edilib',
            self::NoShow => 'Gəlmədi',
        };
    }

    /** Vaxtı tutan statuslar — availability hesablamasında nəzərə alınır. */
    public function occupiesSlot(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed];
    }

    /** Bu statusdan hansı statuslara keçmək olar. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
            self::Completed, self::Cancelled, self::NoShow => [],
        }, true);
    }
}
