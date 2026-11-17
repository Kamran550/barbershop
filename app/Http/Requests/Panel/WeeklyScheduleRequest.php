<?php

namespace App\Http\Requests\Panel;

use App\Support\Weekday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Forma strukturu:
 *   days[1][enabled]=1, days[1][start]=10:00, days[1][end]=20:00,
 *   days[1][breaks][0][start]=14:00, days[1][breaks][0][end]=15:00
 *
 * Sadə qaydalar rules()-dadır; günlər arası məntiq (fasilə iş saatına sığırmı, fasilələr kəsişirmi)
 * after() mərhələsində yoxlanır.
 */
class WeeklyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSchedule', $this->route('barber'));
    }

    protected function prepareForValidation(): void
    {
        $days = [];
        foreach (array_keys(Weekday::LABELS) as $weekday) {
            $day = $this->input("days.$weekday", []);
            $day['enabled'] = filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $day['breaks'] = array_values(array_filter(
                $day['breaks'] ?? [],
                fn ($b) => filled($b['start'] ?? null) || filled($b['end'] ?? null), // boş sətirləri at
            ));
            $days[$weekday] = $day;
        }

        $this->merge(['days' => $days]);
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array:1,2,3,4,5,6,7'],
            'days.*.enabled' => ['boolean'],
            'days.*.start' => ['exclude_unless:days.*.enabled,true', 'required', 'date_format:H:i'],
            'days.*.end' => ['exclude_unless:days.*.enabled,true', 'required', 'date_format:H:i'],
            'days.*.breaks' => ['exclude_unless:days.*.enabled,true', 'array', 'max:5'],
            'days.*.breaks.*.start' => ['required', 'date_format:H:i'],
            'days.*.breaks.*.end' => ['required', 'date_format:H:i'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return; // format xətaları varsa, müqayisə mənasızdır
            }

            foreach ($this->input('days') as $weekday => $day) {
                if (! $day['enabled']) {
                    continue;
                }

                $label = Weekday::label($weekday);

                if ($day['start'] >= $day['end']) {
                    $validator->errors()->add("days.$weekday.end", "{$label}: bitmə saatı başlama saatından sonra olmalıdır.");

                    continue;
                }

                $breaks = collect($day['breaks'])->sortBy('start')->values();

                foreach ($breaks as $i => $break) {
                    if ($break['start'] >= $break['end']) {
                        $validator->errors()->add("days.$weekday.breaks", "{$label}: fasilənin bitməsi başlamasından sonra olmalıdır.");
                    } elseif ($break['start'] < $day['start'] || $break['end'] > $day['end']) {
                        $validator->errors()->add("days.$weekday.breaks", "{$label}: fasilə ({$break['start']}–{$break['end']}) iş saatlarının daxilində olmalıdır.");
                    } elseif ($i > 0 && $break['start'] < $breaks[$i - 1]['end']) {
                        $validator->errors()->add("days.$weekday.breaks", "{$label}: fasilələr üst-üstə düşür.");
                    }
                }
            }
        }];
    }
}
