<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private qeyd. Görə bilənlər: menecer (hamısını) və qeydin müəllifi.
 * Başqa bərbər eyni müştəriyə xidmət etsə belə, həmkarının qeydini görmür.
 */
class CustomerNote extends Model
{
    protected $fillable = ['customer_id', 'user_id', 'body'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isManager()) {
            $query->where('user_id', $user->id);
        }
    }
}
