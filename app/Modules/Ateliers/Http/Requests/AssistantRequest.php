<?php

namespace App\Modules\Ateliers\Http\Requests;

use App\Modules\Ateliers\Services\AssistantService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Question posée à l'assistant de réparation : ouvert à tous, limité en nombre par minute sur la route.
 */
class AssistantRequest extends FormRequest
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
            'question' => ['required', 'string', 'min:3', 'max:'.AssistantService::QUESTION_MAX],
        ];
    }

    public function messages(): array
    {
        return [
            'question.required' => 'Écrivez votre question.',
            'question.string' => 'La question doit être un texte.',
            'question.min' => 'Votre question est trop courte : décrivez le problème en quelques mots.',
            'question.max' => 'La question ne doit pas dépasser '.AssistantService::QUESTION_MAX.' caractères.',
        ];
    }
}
