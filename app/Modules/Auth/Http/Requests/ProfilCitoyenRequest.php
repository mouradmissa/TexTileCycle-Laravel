<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfilCitoyenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isCitoyen();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        if (is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        $userId = auth()->user()?->getKey();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique(User::class, 'email')->ignore($userId !== null ? (string) $userId : null, '_id'),
            ],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s().-]{6,30}$/'],
            'current_password' => [
                'required_with:password',
                'nullable',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->filled('password')) {
                        return;
                    }

                    $hash = (string) ($this->user()?->password ?? '');
                    if ($hash === '' || ! Hash::check((string) $value, $hash)) {
                        $fail('Le mot de passe actuel est incorrect.');
                    }
                },
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'phone.required' => 'Le numéro de téléphone est obligatoire.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser 30 caractères.',
            'phone.regex' => 'Le numéro de téléphone ne doit contenir que des chiffres, espaces, +, -, ( ou ).',
            'current_password.required_with' => 'Indiquez votre mot de passe actuel pour le changer.',
            'password.min' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ];
    }
}
