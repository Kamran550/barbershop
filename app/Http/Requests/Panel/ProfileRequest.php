<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Bərbərin öz profili. Burada YALNIZ şəxsi sahələr var — is_active, sort_order, email
 * qaydalarda olmadığı üçün göndərilsələr belə validated()-ə düşmür (privilege escalation qoruması).
 */
class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barber = $this->user()->barber;

        return $barber !== null && $this->user()->can('updateProfile', $barber);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['remove_photo' => $this->boolean('remove_photo')]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'telegram_chat_id' => ['nullable', 'string', 'regex:/^-?\d{5,20}$/'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_photo' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'Vəzifə', 'bio' => 'Haqqında', 'telegram_chat_id' => 'Telegram chat ID', 'photo' => 'Şəkil'];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Telefon nömrəsi düzgün formatda deyil.',
            'telegram_chat_id.regex' => 'Telegram chat ID yalnız rəqəmlərdən ibarət olmalıdır.',
        ];
    }
}
