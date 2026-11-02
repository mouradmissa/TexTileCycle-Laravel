<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Services\AssistantAtelierService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assistant de l'espace atelier : une question libre, ou un bouton rapide (outil choisi dans une liste fermée).
 */
class AssistantAtelierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('question'))) {
            $this->merge(['question' => trim(preg_replace('/\s+/u', ' ', $this->input('question')))]);
        }
    }

    public function rules(): array
    {
        return [
            'outil' => ['nullable', 'string', Rule::in(array_keys(AssistantAtelierService::RACCOURCIS))],
            'question' => ['nullable', 'required_without:outil', 'string', 'min:3', 'max:'.AssistantAtelierService::QUESTION_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'outil.string' => 'Ce raccourci est inconnu.',
            'outil.in' => 'Ce raccourci est inconnu.',
            'question.required_without' => 'Écrivez votre question ou choisissez un raccourci.',
            'question.string' => 'La question doit être un texte.',
            'question.min' => 'Votre question est trop courte.',
            'question.max' => 'La question ne doit pas dépasser '.AssistantAtelierService::QUESTION_MAX.' caractères.',
        ];
    }
}
