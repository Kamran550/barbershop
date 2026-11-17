<?php

namespace App\Http\Requests\Panel;

use App\Models\Appointment;
use App\Models\BusinessSetting;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Menecerin əl ilə rezervasiyası (telefonla zəng edən və ya qapıdan gələn müştəri). */
class ManualBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Appointment::class);
    }

    public function rules(): array
    {
        return [
            'barber_id' => ['required', 'integer', Rule::exists('barbers', 'id')->where('is_active', true)],
            // Xidmət seçilmiş bərbərə məxsus olmalıdır
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('barber_id', $this->integer('barber_id'))],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (! PhoneNumber::isValid($value)) {
                    $fail('Telefon nömrəsi düzgün deyil. Nümunə: 050 123 45 67');
                }
            }],
        ];
    }

    public function messages(): array
    {
        return ['service_id.exists' => 'Bu xidmət seçilmiş bərbərə aid deyil.'];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->validated('date').' '.$this->validated('time'), BusinessSetting::timezone())->utc();
    }
}
