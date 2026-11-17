<?php

namespace App\Policies;

use App\Models\Barber;
use App\Models\User;

/**
 * Bölgü:
 *  - Menecer: hər şey (bərbər əlavə/silmək, aktivlik, login hesabı, hamının qrafiki)
 *  - Bərbər: YALNIZ ÖZ profili (şəkil, bio, telefon) və ÖZ qrafiki (iş saatları, fasilələr, istisnalar)
 */
class BarberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManager();
    }

    public function view(User $user, Barber $barber): bool
    {
        return $this->managesOrIs($user, $barber);
    }

    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Tam redaktə: aktivlik, sıra, login hesabı — yalnız menecer. */
    public function update(User $user, Barber $barber): bool
    {
        return $user->isManager();
    }

    /** Silmək DB səviyyəsində də qorunur: rezervasiyası olan bərbər silinmir (restrict FK). */
    public function delete(User $user, Barber $barber): bool
    {
        return $user->isManager();
    }

    /** Öz profili: ad, vəzifə, bio, telefon, şəkil, Telegram — bərbərin özü. */
    public function updateProfile(User $user, Barber $barber): bool
    {
        return $user->barberId() === $barber->id;
    }

    public function viewSchedule(User $user, Barber $barber): bool
    {
        return $this->managesOrIs($user, $barber);
    }

    /** İş saatları, fasilələr, istisnalar — menecer və bərbərin özü. */
    public function manageSchedule(User $user, Barber $barber): bool
    {
        return $this->managesOrIs($user, $barber);
    }

    private function managesOrIs(User $user, Barber $barber): bool
    {
        return $user->isManager() || $user->barberId() === $barber->id;
    }
}
