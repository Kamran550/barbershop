<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Konkret tarix üçün qrafik dəyişikliyi: izin günü və ya xüsusi iş saatları. */
class AvailabilityException extends Model
{
    protected $fillable = ['barber_id', 'date', 'is_day_off', 'start_time', 'end_time', 'reason'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_day_off' => 'boolean',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
