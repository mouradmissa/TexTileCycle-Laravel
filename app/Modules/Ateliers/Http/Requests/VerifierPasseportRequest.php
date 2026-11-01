<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Services\PasseportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * « Vérifier un passeport » : le visiteur colle le code ou l'adresse lue sous le QR (étiquette abîmée, lien reçu).
 */
class VerifierPasseportRequest extends FormRequest
{
    protected $redirectRoute = 'front.passeport.verifier';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Saisissez le code du passeport ou collez son adresse.',
            'code.string' => 'Le code du passeport doit être un texte.',
            'code.max' => 'Le code du passeport ne doit pas dépasser 300 caractères.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('code')) {
                return;
            }

            if (PasseportService::extraireCode((string) $this->input('code')) === null) {
                $validator->errors()->add('code', 'Ce n\'est pas un code de passeport : il compte 22 lettres et chiffres (ou collez l\'adresse complète écrite sous le QR).');
            }
        });
    }

    public function code(): string
    {
        return (string) PasseportService::extraireCode((string) $this->validated()['code']);
    }
}
