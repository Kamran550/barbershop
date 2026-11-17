<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    /** Siyahı Customer::visibleTo() ilə filtrlənir. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Bərbər yalnız ona ən azı bir dəfə yazılmış müştərinin kartını və tarixçəsini görür. */
    public function view(User $user, Customer $customer): bool
    {
        if ($user->isManager()) {
            return true;
        }

        $barberId = $user->barberId();

        return $barberId !== null
            && $customer->appointments()->where('barber_id', $barberId)->exists();
    }

    /** Ad / telefon redaktəsi — yalnız menecer. */
    public function update(User $user, Customer $customer): bool
    {
        return $user->isManager();
    }
}
