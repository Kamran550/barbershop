<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;

class CustomerNotePolicy
{
    /**
     * Qeyd yazmaq üçün müştərini görmək icazəsi lazımdır.
     * İstifadə: Gate::authorize('create', [CustomerNote::class, $customer])
     */
    public function create(User $user, Customer $customer): bool
    {
        return $user->can('view', $customer);
    }

    public function view(User $user, CustomerNote $note): bool
    {
        return $this->managesOrAuthored($user, $note);
    }

    public function update(User $user, CustomerNote $note): bool
    {
        return $this->managesOrAuthored($user, $note);
    }

    public function delete(User $user, CustomerNote $note): bool
    {
        return $this->managesOrAuthored($user, $note);
    }

    private function managesOrAuthored(User $user, CustomerNote $note): bool
    {
        return $user->isManager() || $note->user_id === $user->id;
    }
}
