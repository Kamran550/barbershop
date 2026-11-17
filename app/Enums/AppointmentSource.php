<?php

namespace App\Enums;

enum AppointmentSource: string
{
    case Online = 'online';   // müştəri saytdan
    case Manual = 'manual';   // işçi paneldən

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Onlayn',
            self::Manual => 'Əl ilə',
        };
    }
}
