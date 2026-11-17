<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Login olmayan müştəri, normalizə olunmuş telefonla unikal.
 * Statistika (ziyarət sayı, xərc, son ziyarət) appointments-dən hesablanır — PHASE 8 (CRM).
 */
class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory;

    protected $fillable = ['name', 'phone'];

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** Private qeydlər. Kimin hansını görməsi CustomerNote::visibleTo() və CustomerNotePolicy ilə. */
    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    /** Menecer bütün müştəriləri, bərbər yalnız ona ən azı bir dəfə yazılmış müştəriləri görür. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $barberId = $user->barberId();
        $barberId === null
            ? $query->whereRaw('1 = 0')
            : $query->whereHas('appointments', fn (Builder $q) => $q->where('barber_id', $barberId));
    }
}
