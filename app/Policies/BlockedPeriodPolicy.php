<?php

namespace App\Policies;

use App\Models\Barber;
use App\Models\BlockedPeriod;
use App\Models\User;

/**
 * Bloklar:
 *  - Bərbər: yalnız ÖZÜ üçün blok yaradır/silir. Bütün berberxana üzrə blokları görür, amma dəyişə bilməz.
 *  - Menecer: hər kəs üçün və bütün berberxana üçün.
 */
class BlockedPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->barberId() !== null;
    }

    /** $target = null → bütün berberxana bağlanır (yalnız menecer). */
    public function create(User $user, ?Barber $target = null): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $target !== null && $user->barberId() === $target->id;
    }

    public function delete(User $user, BlockedPeriod $block): bool
    {
        if ($user->isManager()) {
            return true;
        }

        return $block->barber_id !== null && $block->barber_id === $user->barberId();
    }
}
