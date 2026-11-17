<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Sistemə login olan işçi (Manager və ya Barber). Müştərilər burada deyil.
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /** Bərbər rolundakı user-in public profili (manager üçün null). */
    public function barber(): HasOne
    {
        return $this->hasOne(Barber::class);
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::Manager;
    }

    public function isBarber(): bool
    {
        return $this->role === UserRole::Barber;
    }

    /**
     * Bərbər rolundakı user-in barbers.id-si. Menecer və ya profilə bağlanmamış bərbər üçün null.
     * Authorization-un əsası: "bu rezervasiya mənimdir?" = appointment.barber_id === barberId()
     */
    public function barberId(): ?int
    {
        return $this->isBarber() ? $this->barber?->id : null;
    }
}
