<?php

namespace App\Modules\Vetements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestylerPieceVetementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'instruction' => ['required', 'string', 'min:8', 'max:400'],
        ];
    }

    public function messages(): array
    {
        return [
            'instruction.required' => 'Décrivez la réparation à visualiser.',
            'instruction.min' => 'Décrivez la réparation en quelques mots.',
            'instruction.max' => 'L\'instruction ne doit pas dépasser 400 caractères.',
        ];
    }
}
