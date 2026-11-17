<?php

namespace App\Http\Requests\Panel;

use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class AvailabilityExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSchedule', $this->route('barber'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_day_off' => $this->boolean('is_day_off')]);
    }

    public function rules(): array
    {
        // "Bu gün" berberxananın saat qurşağında hesablanır, server-in UTC vaxtında yox
        $today = CarbonImmutable::now(BusinessSetting::timezone())->toDateString();

        return [
            'date' => ['required', 'date_format:Y-m-d', "after_or_equal:$today"],
            'is_day_off' => ['boolean'],
            'start' => ['exclude_if:is_day_off,true', 'required', 'date_format:H:i'],
            'end' => ['exclude_if:is_day_off,true', 'required', 'date_format:H:i', 'after:start'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['start' => 'Başlama saatı', 'end' => 'Bitmə saatı', 'reason' => 'Səbəb'];
    }

    public function messages(): array
    {
        return ['date.after_or_equal' => 'Keçmiş tarix üçün istisna əlavə etmək olmaz.'];
    }
}
