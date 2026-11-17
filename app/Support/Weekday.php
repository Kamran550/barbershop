<?php

namespace App\Support;

/** ISO-8601 həftə günləri: 1 = Bazar ertəsi … 7 = Bazar. */
class Weekday
{
    public const LABELS = [
        1 => 'Bazar ertəsi',
        2 => 'Çərşənbə axşamı',
        3 => 'Çərşənbə',
        4 => 'Cümə axşamı',
        5 => 'Cümə',
        6 => 'Şənbə',
        7 => 'Bazar',
    ];

    public const SHORT = [1 => 'B.e.', 2 => 'Ç.a.', 3 => 'Ç.', 4 => 'C.a.', 5 => 'C.', 6 => 'Ş.', 7 => 'B.'];

    public static function label(int $weekday): string
    {
        return self::LABELS[$weekday];
    }
}
