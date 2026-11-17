<?php

namespace App\Http\Requests\Public;

use App\Models\BusinessSetting;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Müştərinin rezervasiya formu. Burada yalnız FORMAT yoxlanır;
 * vaxtın həqiqətən boş olması BookingService-də, lock altında yoxlanır.
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // login tələb olunmur
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'barber_id' => $this->input('barber_id') ?: 'any',
        ]);
    }

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->where('is_active', true)],
            'barber_id' => ['required', Rule::when(
                $this->input('barber_id') !== 'any',
                ['integer', Rule::exists('barbers', 'id')->where('is_active', true)],
            )],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail) {
                if (! PhoneNumber::isValid($value)) {
                    $fail('Telefon nömrəsi düzgün deyil. Nümunə: 050 123 45 67');
                }
            }],
            // Honeypot: insan görmür, bot doldurur. Doludursa — rədd.
            'website' => ['prohibited'],
        ];
    }

    public function attributes(): array
    {
        return ['service_id' => 'Xidmət', 'barber_id' => 'Bərbər', 'website' => 'Sahə'];
    }

    public function messages(): array
    {
        return [
            'service_id.exists' => 'Seçilmiş xidmət hazırda mövcud deyil.',
            'barber_id.exists' => 'Seçilmiş bərbər hazırda rezervasiya qəbul etmir.',
            'name.min' => 'Adınızı tam yazın.',
        ];
    }

    /** Formadakı tarix + saat berberxananın vaxtıdır → UTC. */
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->validated('date').' '.$this->validated('time'), BusinessSetting::timezone())->utc();
    }
}
