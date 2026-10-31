<?php

namespace App\Modules\Vetements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestylerVetementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'instruction' => ['required', 'string', 'min:8', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Choisissez la photo d\'origine du vêtement.',
            'photo.image' => 'La photo doit être une image (JPG, PNG ou WebP).',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'instruction.required' => 'Décrivez la réparation à visualiser.',
            'instruction.min' => 'Décrivez la réparation en quelques mots.',
            'instruction.max' => 'L\'instruction ne doit pas dépasser 400 caractères.',
        ];
    }
}
