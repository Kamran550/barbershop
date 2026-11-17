<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Barber = 'barber';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Menecer',
            self::Barber => 'Bərbər',
        };
    }
}
