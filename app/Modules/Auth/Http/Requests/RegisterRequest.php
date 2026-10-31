<?php

namespace App\Modules\Auth\Http\Requests;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }

        if ($this->input('role') !== User::ROLE_CITOYEN) {
            $this->merge(['phone' => null]);
        } elseif (is_string($this->input('phone'))) {
            $this->merge(['phone' => trim($this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::ROLES_INSCRIPTION)],
            'phone' => [
                Rule::requiredIf(fn () => $this->input('role') === User::ROLE_CITOYEN),
                'nullable',
                'string',
                'max:30',
                'regex:/^\+?[0-9\s().-]{6,30}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom complet est obligatoire.',
            'email.required' => 'L\'adresse e-mail est obligatoire.',
            'email.unique' => 'Cette adresse e-mail est déjà utilisée.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
            'role.required' => 'Choisissez le type de compte.',
            'role.in' => 'Choisissez un rôle parmi citoyen, atelier ou association.',
            'phone.required' => 'Le numéro de téléphone est obligatoire pour un compte client.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser 30 caractères.',
            'phone.regex' => 'Le numéro de téléphone ne doit contenir que des chiffres, espaces, +, -, ( ou ).',
        ];
    }
}
