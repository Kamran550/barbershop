<?php

namespace App\Http\Requests\Panel;

use App\Models\Barber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class BarberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $barber = $this->route('barber');

        return $barber instanceof Barber
            ? $this->user()->can('update', $barber)
            : $this->user()->can('create', Barber::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_photo' => $this->boolean('remove_photo'),
            'sort_order' => $this->input('sort_order', 0) ?? 0,
            'email' => $this->filled('email') ? mb_strtolower(trim($this->input('email'))) : null,
        ]);
    }

    public function rules(): array
    {
        /** @var Barber|null $barber */
        $barber = $this->route('barber');
        $userId = $barber?->user_id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'telegram_chat_id' => ['nullable', 'string', 'regex:/^-?\d{5,20}$/'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:999'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_photo' => ['boolean'],

            // Login hesabı optionaldır. Hesabı olan bərbərin email-i boşaldıla bilməz.
            'email' => [
                Rule::requiredIf($userId !== null),
                'nullable', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            // Yeni hesab üçün şifrə mütləqdir; mövcud hesabda yalnız dəyişmək istəyəndə
            'password' => [
                Rule::requiredIf($userId === null && $this->filled('email')),
                'nullable', 'string', Password::min(8)->letters()->numbers(),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'Vəzifə',
            'bio' => 'Haqqında',
            'telegram_chat_id' => 'Telegram chat ID',
            'photo' => 'Şəkil',
            'sort_order' => 'Sıra',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Telefon nömrəsi düzgün formatda deyil.',
            'telegram_chat_id.regex' => 'Telegram chat ID yalnız rəqəmlərdən ibarət olmalıdır.',
            'email.required' => 'Hesabı olan bərbərin e-poçtu boş ola bilməz.',
            'password.required' => 'Yeni login hesabı üçün şifrə mütləqdir.',
        ];
    }
}
