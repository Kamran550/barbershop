<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Birdəfəlik bloklanmış interval (UTC). barber_id = null → bütün berberxana. */
class BlockedPeriod extends Model
{
    protected $fillable = ['barber_id', 'starts_at', 'ends_at', 'reason', 'created_by'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Menecer hamısını; bərbər öz bloklarını + bütün berberxana üzrə blokları görür. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $barberId = $user->barberId();
        $barberId === null
            ? $query->whereRaw('1 = 0')
            : $query->where(fn (Builder $q) => $q->where('barber_id', $barberId)->orWhereNull('barber_id'));
    }
}
