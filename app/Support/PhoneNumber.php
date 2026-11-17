<?php

namespace App\Support;

/**
 * Azərbaycan telefon nömrələrinin normalizasiyası.
 * Müştəri eyni nömrəni müxtəlif cür yaza bilər — DB-də həmişə bir format saxlanır (E.164):
 *   "050 123 45 67", "+994 50 123-45-67", "501234567"  →  "+994501234567"
 * Bu sayədə customers.phone unique index-i həqiqətən işləyir.
 */
class PhoneNumber
{
    /** Mobil operator kodları + Bakı şəhər (12). */
    private const PREFIXES = ['10', '12', '50', '51', '55', '60', '70', '77', '99'];

    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        $national = match (true) {
            strlen($digits) === 12 && str_starts_with($digits, '994') => substr($digits, 3),
            strlen($digits) === 10 && str_starts_with($digits, '0') => substr($digits, 1),
            strlen($digits) === 9 => $digits,
            default => null,
        };

        if ($national === null || ! in_array(substr($national, 0, 2), self::PREFIXES, true)) {
            return null;
        }

        return '+994'.$national;
    }

    /**
     * Axtarış üçün rəqəm fraqmenti: "050 123" → "50123" (DB-də "+994501234567" kimi saxlanır,
     * yerli formatdakı baştakı 0 orada yoxdur). Fraqment çox qısadırsa null.
     */
    public static function searchFragment(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input);
        $digits = str_starts_with($digits, '0') ? substr($digits, 1) : $digits;

        return strlen($digits) >= 3 ? $digits : null;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** "+994501234567" → "+994 50 123 45 67" */
    public static function format(string $e164): string
    {
        $n = substr($e164, 4);

        return sprintf('+994 %s %s %s %s', substr($n, 0, 2), substr($n, 2, 3), substr($n, 5, 2), substr($n, 7, 2));
    }

    /** Public səhifələr üçün: "+994 50 *** ** 67" */
    public static function mask(string $e164): string
    {
        $n = substr($e164, 4);

        return sprintf('+994 %s *** ** %s', substr($n, 0, 2), substr($n, 7, 2));
    }
}
