<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBetRequest extends FormRequest
{
    /**
     * Autoriza usuários autenticados a criar apostas.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Regras de validação para criação de uma aposta.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'numbers' => [
                'required',
                'array',
                'size:15',
            ],

            'numbers.*' => [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ],

            'source' => [
                'nullable',
                'string',
                'in:manual,generated,imported,demonstrative',
            ],

            'method' => [
                'nullable',
                'string',
                'in:manual,integral,reduced,wheel,random,balanced',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Mensagens personalizadas de validação.
     */
    public function messages(): array
    {
        return [
            'name.string' => 'O nome da aposta deve ser um texto.',
            'name.max' => 'O nome da aposta não pode ultrapassar 255 caracteres.',

            'numbers.required' => 'Selecione as dezenas da aposta.',
            'numbers.array' => 'As dezenas devem ser informadas em formato de lista.',
            'numbers.size' => 'A aposta deve conter exatamente 15 dezenas.',

            'numbers.*.required' => 'Todas as dezenas devem ser informadas.',
            'numbers.*.integer' => 'As dezenas devem ser números inteiros.',
            'numbers.*.distinct' => 'Não é permitido repetir dezenas na mesma aposta.',
            'numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',

            'source.in' => 'A origem informada não é válida.',
            'method.in' => 'O método informado não é válido.',

            'notes.string' => 'As observações devem ser um texto.',
            'notes.max' => 'As observações não podem ultrapassar 2.000 caracteres.',
        ];
    }

    /**
     * Nomes amigáveis dos campos.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'numbers' => 'dezenas',
            'numbers.*' => 'dezena',
            'source' => 'origem',
            'method' => 'método',
            'notes' => 'observações',
        ];
    }
}
