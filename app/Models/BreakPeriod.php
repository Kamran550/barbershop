<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Həftəlik təkrarlanan fasilə. Class adı "Break" ola bilməz — PHP-də reserved sözdür. */
class BreakPeriod extends Model
{
    protected $table = 'breaks';

    protected $fillable = ['barber_id', 'weekday', 'start_time', 'end_time', 'label'];

    protected function casts(): array
    {
        return ['weekday' => 'integer'];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }
}
