<?php

namespace App\Support;

class Money
{
    /** "15.00" → "15 ₼", "12.50" → "12.50 ₼" */
    public static function format(string|int|float|null $amount): string
    {
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

        return number_format($value, $decimals, '.', ' ').' ₼';
    }
}
