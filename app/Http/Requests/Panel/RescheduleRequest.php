<?php

namespace App\Http\Requests\Panel;

use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RescheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reschedule', $this->route('appointment'));
    }

    public function rules(): array
    {
        return [
            'barber_id' => ['required', 'integer', Rule::exists('barbers', 'id')],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
        ];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->validated('date').' '.$this->validated('time'), BusinessSetting::timezone())->utc();
    }
}
