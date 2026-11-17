<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

/**
 * Xidmət bərbərə məxsusdur: bərbər yalnız ÖZ xidmətlərini yaradır, redaktə edir, silir.
 * Menecer hamısını idarə edir. Siyahı Service::visibleTo() ilə filtrlənir.
 */
class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager() || $user->barberId() !== null;
    }

    public function create(User $user): bool
    {
        return $user->isManager() || $user->barberId() !== null;
    }

    public function update(User $user, Service $service): bool
    {
        return $this->managesOrOwns($user, $service);
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->managesOrOwns($user, $service);
    }

    private function managesOrOwns(User $user, Service $service): bool
    {
        if ($user->isManager()) {
            return true;
        }

        $barberId = $user->barberId();

        return $barberId !== null && $service->barber_id === $barberId;
    }
}
