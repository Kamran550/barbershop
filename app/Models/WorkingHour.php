<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Həftəlik iş qrafiki. weekday: ISO-8601 (1 = Bazar ertəsi … 7 = Bazar). */
class WorkingHour extends Model
{
    protected $fillable = ['barber_id', 'weekday', 'start_time', 'end_time'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
