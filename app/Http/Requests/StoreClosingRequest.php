<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class StoreClosingRequest extends FormRequest
{
    /**
     * Autoriza usuários autenticados a criar fechamentos.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Regras de validação do fechamento.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'method' => [
                'required',
                'string',
                'in:integral,reduced,wheel,random,balanced',
            ],

            'base_numbers' => [
                'required',
                'array',
                'between:15,25',
            ],

            'base_numbers.*' => [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ],

            'bet_size' => [
                'required',
                'integer',
                'between:15,25',
            ],

            'planned_bets' => [
                'required',
                'integer',
                'min:1',
            ],

            'guarantee' => [
                'nullable',
                'integer',
                'between:15,15',
                // 'between:11,15',
            ],

            'budget' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    /**
     * Validações adicionais após as regras principais.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $baseNumbers = $this->input('base_numbers', []);
            $betSize = $this->input('bet_size');

            if (
                is_array($baseNumbers)
                && is_numeric($betSize)
                && (int) $betSize > count($baseNumbers)
            ) {
                $validator->errors()->add(
                    'bet_size',
                    'O tamanho de cada aposta não pode ser maior que a quantidade de dezenas do grupo-base.'
                );
            }
        });
    }

    /**
     * Mensagens personalizadas de validação.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para o fechamento.',
            'name.string' => 'O nome do fechamento deve ser um texto.',
            'name.max' => 'O nome do fechamento não pode ultrapassar 255 caracteres.',

            'method.required' => 'Selecione o método do fechamento.',
            'method.in' => 'O método informado não é válido.',

            'base_numbers.required' => 'Selecione as dezenas do grupo-base.',
            'base_numbers.array' => 'As dezenas do grupo-base devem ser informadas em formato de lista.',
            'base_numbers.between' => 'O grupo-base deve conter entre 15 e 25 dezenas.',

            'base_numbers.*.required' => 'Todas as dezenas do grupo-base devem ser informadas.',
            'base_numbers.*.integer' => 'As dezenas do grupo-base devem ser números inteiros.',
            'base_numbers.*.distinct' => 'Não é permitido repetir dezenas no grupo-base.',
            'base_numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',

            'bet_size.required' => 'Informe o tamanho de cada aposta.',
            'bet_size.integer' => 'O tamanho da aposta deve ser um número inteiro.',
            'bet_size.between' => 'O tamanho de cada aposta deve estar entre 15 e 25.',

            'planned_bets.required' => 'Informe a quantidade planejada de apostas.',
            'planned_bets.integer' => 'A quantidade planejada deve ser um número inteiro.',
            'planned_bets.min' => 'A quantidade planejada deve ser maior que zero.',

            'guarantee.integer' => 'A garantia deve ser um número inteiro.',
            'guarantee.between' => 'A garantia informada não é válida.',

            'budget.numeric' => 'O orçamento deve ser um valor numérico.',
            'budget.min' => 'O orçamento não pode ser negativo.',

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
            'method' => 'método',
            'base_numbers' => 'grupo-base',
            'base_numbers.*' => 'dezena',
            'bet_size' => 'tamanho da aposta',
            'planned_bets' => 'quantidade planejada de apostas',
            'guarantee' => 'garantia',
            'budget' => 'orçamento',
            'notes' => 'observações',
        ];
    }
}
