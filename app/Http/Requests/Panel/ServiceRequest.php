<?php

namespace App\Http\Requests\Panel;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Xidmət yaratma və redaktə. İcazə ServicePolicy-dədir:
 * bərbər yalnız öz xidmətini redaktə edə bilər; başqasınınkına → 403.
 */
class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $service = $this->route('service');

        return $service instanceof Service
            ? $this->user()->can('update', $service)
            : $this->user()->can('create', Service::class);
    }

    /** Checkbox seçilməyəndə brauzer heç nə göndərmir — false kimi qəbul edirik. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
            'sort_order' => $this->input('sort_order', 0) ?? 0,
        ]);
    }

    public function rules(): array
    {
        $creatingAsManager = $this->user()->isManager() && ! $this->route('service');

        return [
            // Menecer yeni xidməti istənilən bərbərin adına yaradır; bərbər üçün bu sahə nəzərə alınmır
            // (sahib həmişə özüdür). Mövcud xidmətin sahibi dəyişmir.
            'barber_id' => $creatingAsManager
                ? ['required', 'integer', Rule::exists('barbers', 'id')]
                : ['exclude'],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999.99'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480', 'multiple_of:5'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:999'],
            // image: SVG-ni avtomatik rədd edir. dimensions: "decompression bomb" şəkillərə qarşı
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_image' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'barber_id' => 'Bərbər',
            'image' => 'Şəkil',
            'sort_order' => 'Sıra',
        ];
    }

    public function messages(): array
    {
        return [
            'duration_minutes.multiple_of' => 'Müddət 5 dəqiqəyə bölünən olmalıdır.',
        ];
    }
}
