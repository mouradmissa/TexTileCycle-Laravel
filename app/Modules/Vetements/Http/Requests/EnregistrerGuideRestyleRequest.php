<?php

namespace App\Modules\Vetements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnregistrerGuideRestyleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'photo' => ['required_without:vetement_id', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'instruction' => ['required', 'string', 'min:8', 'max:400'],
            'vetement_id' => ['nullable', 'string', 'max:64'],
            'jeton' => ['required_without:image_apres', 'nullable', 'string', 'max:200'],
            'image_apres' => ['required_without:jeton', 'nullable', 'string', 'max:12000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required_without' => 'Choisissez la photo d\'origine du vêtement.',
            'photo.image' => 'La photo doit être une image (JPG, PNG ou WebP).',
            'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
            'instruction.required' => 'Décrivez la réparation à enregistrer.',
            'instruction.min' => 'Décrivez la réparation en quelques mots.',
            'instruction.max' => 'L\'instruction ne doit pas dépasser 400 caractères.',
            'jeton.required_without' => 'Relancez la génération avant d\'enregistrer le guide.',
            'image_apres.required_without' => 'L\'image après réparation est manquante.',
        ];
    }
}
