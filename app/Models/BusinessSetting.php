<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tək sətirli parametrlər. Hər yerdə BusinessSetting::current() ilə oxunur.
 */
class BusinessSetting extends Model
{
    protected $fillable = [
        'name', 'tagline', 'description', 'phone', 'email', 'address', 'instagram_url', 'opening_hours',
        'timezone', 'slot_interval_minutes', 'buffer_minutes', 'booking_horizon_days', 'min_notice_minutes',
        'cancel_deadline_minutes', 'max_active_bookings_per_phone', 'auto_confirm',
        'telegram_enabled', 'telegram_bot_token', 'telegram_manager_chat_id',
    ];

    /** Bot token heç vaxt view-a / JSON-a təsadüfən düşməsin. */
    protected $hidden = ['telegram_bot_token'];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'slot_interval_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'booking_horizon_days' => 'integer',
            'min_notice_minutes' => 'integer',
            'cancel_deadline_minutes' => 'integer',
            'max_active_bookings_per_phone' => 'integer',
            'auto_confirm' => 'boolean',
            'telegram_enabled' => 'boolean',
            // APP_KEY ilə şifrələnir: DB sızsa belə token oxunmur
            'telegram_bot_token' => 'encrypted',
        ];
    }

    /** Bir request ərzində bir dəfə DB-dən oxunur (once() — Laravel memoization). */
    public static function current(): self
    {
        return once(fn () => static::query()->firstOrFail());
    }

    /** Berberxananın saat qurşağı. Parametr sətri hələ yoxdursa (məs. ilk quraşdırma) — Asia/Baku. */
    public static function timezone(): string
    {
        return once(fn () => static::query()->value('timezone') ?? 'Asia/Baku');
    }
}
