<?php

namespace App\Http\Requests\Panel;

use App\Models\BusinessSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BlockedPeriodRequest extends FormRequest
{
    /** Konkret icazə (kimin üçün blok) controller-də BlockedPeriodPolicy::create ilə yoxlanır. */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', \App\Models\BlockedPeriod::class);
    }

    public function rules(): array
    {
        return [
            // Yalnız menecer seçir (boş = bütün berberxana). Bərbər üçün bu sahə tamamilə nəzərə alınmır.
            'barber_id' => $this->user()->isManager()
                ? ['nullable', 'integer', Rule::exists('barbers', 'id')]
                : ['exclude'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],                 // <input type="datetime-local">
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('ends_at')) {
                return;
            }

            $tz = BusinessSetting::timezone();
            if (CarbonImmutable::parse($this->input('ends_at'), $tz)->isPast()) {
                $validator->errors()->add('ends_at', 'Tamamilə keçmişdə qalan vaxtı bloklamaq olmaz.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['barber_id' => 'Bərbər', 'starts_at' => 'Başlama vaxtı', 'ends_at' => 'Bitmə vaxtı', 'reason' => 'Səbəb'];
    }
}
