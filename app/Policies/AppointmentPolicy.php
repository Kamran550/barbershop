<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

/**
 * Konkret rezervasiya üzrə icazələr. Controller-də: $this->authorize('view', $appointment)
 * və ya Gate::authorize(...). Policy-də metod yoxdursa (məs. delete) — icazə yoxdur.
 * Rezervasiyalar heç vaxt silinmir, yalnız ləğv edilir.
 */
class AppointmentPolicy
{
    /** Siyahı səhifəsinə hər işçi girə bilər — nəticə visibleTo() ilə filtrlənir. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->managesOrOwns($user, $appointment);
    }

    /** Əl ilə rezervasiya yaratmaq — yalnız menecer. */
    public function create(User $user): bool
    {
        return $user->isManager();
    }

    /** Təsdiqlə / tamamlandı / gəlmədi / ləğv et — menecer və rezervasiyanın öz bərbəri. */
    public function updateStatus(User $user, Appointment $appointment): bool
    {
        return $this->managesOrOwns($user, $appointment);
    }

    /** Başqa vaxta və ya başqa bərbərə keçirmək — yalnız menecer. */
    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $user->isManager();
    }

    private function managesOrOwns(User $user, Appointment $appointment): bool
    {
        if ($user->isManager()) {
            return true;
        }

        $barberId = $user->barberId();

        return $barberId !== null && $appointment->barber_id === $barberId;
    }
}
