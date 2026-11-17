<?php

namespace App\Http\Requests\Panel;

use App\Models\BusinessSetting;
use App\Support\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isManager();
    }

    protected function prepareForValidation(): void
    {
        $hours = [];
        foreach (array_keys(Weekday::LABELS) as $wd) {
            $day = $this->input("opening_hours.$wd", []);
            $hours[$wd] = [
                'open_day' => filter_var($day['open_day'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'open' => $day['open'] ?? null,
                'close' => $day['close'] ?? null,
            ];
        }

        $this->merge([
            'opening_hours' => $hours,
            'auto_confirm' => $this->boolean('auto_confirm'),
            'telegram_enabled' => $this->boolean('telegram_enabled'),
            'clear_telegram_token' => $this->boolean('clear_telegram_token'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'url:https', 'max:255'],

            'opening_hours' => ['required', 'array:1,2,3,4,5,6,7'],
            'opening_hours.*.open_day' => ['boolean'],
            'opening_hours.*.open' => ['exclude_unless:opening_hours.*.open_day,true', 'required', 'date_format:H:i'],
            'opening_hours.*.close' => ['exclude_unless:opening_hours.*.open_day,true', 'required', 'date_format:H:i'],

            'timezone' => ['required', 'timezone:all'],
            'slot_interval_minutes' => ['required', 'integer', Rule::in([5, 10, 15, 20, 30, 60])],
            'buffer_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:180'],
            'min_notice_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'cancel_deadline_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'max_active_bookings_per_phone' => ['required', 'integer', 'min:1', 'max:20'],
            'auto_confirm' => ['boolean'],

            'telegram_enabled' => ['boolean'],
            // Boş saxlanılırsa, mövcud token dəyişmir (forma heç vaxt tokeni geri göstərmir)
            'telegram_bot_token' => ['nullable', 'string', 'regex:/^\d{5,15}:[A-Za-z0-9_-]{30,50}$/'],
            'clear_telegram_token' => ['boolean'],
            'telegram_manager_chat_id' => ['nullable', 'string', 'regex:/^-?\d{5,20}$/'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->input('opening_hours') as $wd => $day) {
                if ($day['open_day'] && $day['open'] && $day['close'] && $day['open'] >= $day['close']) {
                    $validator->errors()->add("opening_hours.$wd.close", Weekday::label($wd).': bağlanma saatı açılışdan sonra olmalıdır.');
                }
            }

            if ($this->boolean('telegram_enabled')) {
                $hasToken = filled($this->input('telegram_bot_token'))
                    || (! $this->boolean('clear_telegram_token') && filled(BusinessSetting::current()->telegram_bot_token));

                if (! $hasToken) {
                    $validator->errors()->add('telegram_bot_token', 'Bildirişləri aktiv etmək üçün bot token lazımdır.');
                }
                if (! filled($this->input('telegram_manager_chat_id'))) {
                    $validator->errors()->add('telegram_manager_chat_id', 'Bildirişləri aktiv etmək üçün menecerin chat ID-si lazımdır.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'tagline' => 'Şüar',
            'instagram_url' => 'Instagram linki',
            'timezone' => 'Saat qurşağı',
            'slot_interval_minutes' => 'Slot addımı',
            'buffer_minutes' => 'Buffer',
            'booking_horizon_days' => 'Rezervasiya üfüqü',
            'min_notice_minutes' => 'Minimum xəbərdarlıq',
            'cancel_deadline_minutes' => 'Ləğv müddəti',
            'max_active_bookings_per_phone' => 'Bir nömrəyə limit',
            'telegram_bot_token' => 'Bot token',
            'telegram_manager_chat_id' => 'Menecerin chat ID-si',
        ];
    }

    public function messages(): array
    {
        return [
            'telegram_bot_token.regex' => 'Token formatı yanlışdır. Nümunə: 123456789:AAH…',
            'telegram_manager_chat_id.regex' => 'Chat ID yalnız rəqəmlərdən ibarət olmalıdır.',
            'phone.regex' => 'Telefon nömrəsi düzgün formatda deyil.',
        ];
    }

    /** DB-yə yazılacaq dəyərlər: iş saatları JSON formatına salınır, token qaydası tətbiq olunur. */
    public function settings(): array
    {
        $data = $this->validated();

        $data['opening_hours'] = collect($data['opening_hours'])
            ->map(fn ($d) => $d['open_day'] ? ['open' => $d['open'], 'close' => $d['close']] : null)
            ->all();

        if ($data['clear_telegram_token']) {
            $data['telegram_bot_token'] = null;
            $data['telegram_enabled'] = false;
        } elseif (blank($data['telegram_bot_token'] ?? null)) {
            unset($data['telegram_bot_token']); // boş → mövcud token qalır
        }
        unset($data['clear_telegram_token']);

        return $data;
    }
}
