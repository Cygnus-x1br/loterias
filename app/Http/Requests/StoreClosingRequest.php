<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator; // Importar Rule para validação condicional

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
        $rules = [
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
                // 'between:11,15', // Manter comentado por enquanto, pois o campo 'guarantee' é genérico
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

        // Regras condicionais para o método 'reduced'
        if ($this->input('method') === 'reduced') {
            $rules['guarantee_hits'] = [
                'required',
                'integer',
                'min:'.$this->input('bet_size'), // Deve ser pelo menos o tamanho da aposta
                'max:'.count($this->input('base_numbers', [])), // Não pode exceder o grupo-base
            ];
            $rules['guarantee_points'] = [
                'required',
                'integer',
                'between:11,'.($this->input('bet_size') - 1), // Pontos garantidos entre 11 e (tamanho da aposta - 1)
            ];
        }

        // Regras condicionais para o método 'balanced' (copiadas do seu Livewire, se aplicável)
        if ($this->input('method') === 'balanced') {
            $rules['min_even'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_even'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_even'];
            $rules['min_sum'] = ['nullable', 'integer', 'min:15', 'max:300'];
            $rules['max_sum'] = ['nullable', 'integer', 'min:15', 'max:300', 'gte:min_sum'];
            $rules['min_primes'] = ['nullable', 'integer', 'min:0', 'max:9'];
            $rules['max_primes'] = ['nullable', 'integer', 'min:0', 'max:9', 'gte:min_primes'];
            $rules['min_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7'];
            $rules['max_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7', 'gte:min_fibonacci'];
        }

        // Regras condicionais para o método 'wheel' (copiadas do seu Livewire, se aplicável)
        if ($this->input('method') === 'wheel') {
            $rules['fixed_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:'.($this->input('bet_size') - 1),
            ];
            $rules['fixed_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->input('base_numbers', [])), // Dezenas fixas devem estar no grupo-base
            ];
            $rules['variable_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:'.(count($this->input('base_numbers', [])) - count($this->input('fixed_numbers', []))),
            ];
            $rules['variable_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->input('base_numbers', [])), // Dezenas variáveis devem estar no grupo-base
                Rule::notIn($this->input('fixed_numbers', [])), // Não pode estar nas fixas
            ];
            $rules['wheel_size'] = [
                'required',
                'integer',
                'min:1',
                'max:'.count($this->input('variable_numbers', [])),
                'size:'.($this->input('bet_size') - count($this->input('fixed_numbers', []))),
            ];
        }

        return $rules;
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

            // Validação adicional para o método 'reduced'
            if ($this->input('method') === 'reduced') {
                $guaranteeHits = $this->input('guarantee_hits');
                $guaranteePoints = $this->input('guarantee_points');

                if ($guaranteeHits !== null && $guaranteePoints !== null) {
                    if ($guaranteePoints >= $betSize) {
                        $validator->errors()->add(
                            'guarantee_points',
                            'Os pontos garantidos devem ser menores que o tamanho da aposta.'
                        );
                    }
                    if ($guaranteeHits < $betSize) {
                        $validator->errors()->add(
                            'guarantee_hits',
                            'O número de acertos na base para garantia deve ser maior ou igual ao tamanho da aposta.'
                        );
                    }
                    if ($guaranteeHits > count($baseNumbers)) {
                        $validator->errors()->add(
                            'guarantee_hits',
                            'O número de acertos na base para garantia não pode ser maior que o grupo-base.'
                        );
                    }
                }
            }
        });
    }

    /**
     * Mensagens personalizadas de validação.
     */
    public function messages(): array
    {
        $messages = [
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

            // Mensagens para o método 'reduced'
            'guarantee_hits.required' => 'Informe o número de acertos na base para garantia.',
            'guarantee_hits.integer' => 'O número de acertos na base para garantia deve ser um número inteiro.',
            'guarantee_hits.min' => 'O número de acertos na base para garantia deve ser pelo menos :min.',
            'guarantee_hits.max' => 'O número de acertos na base para garantia não pode exceder :max.',

            'guarantee_points.required' => 'Informe os pontos garantidos.',
            'guarantee_points.integer' => 'Os pontos garantidos devem ser um número inteiro.',
            'guarantee_points.between' => 'Os pontos garantidos devem estar entre :min e :max.',

            // Mensagens para os parâmetros de equilíbrio (copiadas do seu Livewire)
            'min_even.min' => 'O mínimo de pares não pode ser negativo.',
            'min_even.max' => 'O mínimo de pares não pode exceder 15.',
            'max_even.min' => 'O máximo de pares não pode ser negativo.',
            'max_even.max' => 'O máximo de pares não pode exceder 15.',
            'max_even.gte' => 'O máximo de pares deve ser maior ou igual ao mínimo.',

            'min_sum.min' => 'A soma mínima não pode ser menor que 15.',
            'min_sum.max' => 'A soma mínima não pode exceder 300.',
            'max_sum.min' => 'A soma máxima não pode ser menor que 15.',
            'max_sum.max' => 'A soma máxima não pode exceder 300.',
            'max_sum.gte' => 'A soma máxima deve ser maior ou igual à soma mínima.',

            'min_primes.min' => 'O mínimo de primos não pode ser negativo.',
            'min_primes.max' => 'O mínimo de primos não pode exceder 9.',
            'max_primes.min' => 'O máximo de primos não pode ser negativo.',
            'max_primes.max' => 'O máximo de primos não pode exceder 9.',
            'max_primes.gte' => 'O máximo de primos deve ser maior ou igual ao mínimo.',

            'min_fibonacci.min' => 'O mínimo de Fibonacci não pode ser negativo.',
            'min_fibonacci.max' => 'O mínimo de Fibonacci não pode exceder 7.',
            'max_fibonacci.min' => 'O máximo de Fibonacci não pode ser negativo.',
            'max_fibonacci.max' => 'O máximo de Fibonacci não pode exceder 7.',
            'max_fibonacci.gte' => 'O máximo de Fibonacci deve ser maior ou igual ao mínimo.',

            // Mensagens para os parâmetros do sistema de roda (copiadas do seu Livewire)
            'fixed_numbers.required' => 'Selecione as dezenas fixas.',
            'fixed_numbers.array' => 'As dezenas fixas devem ser uma lista.',
            'fixed_numbers.min' => 'Selecione pelo menos uma dezena fixa.',
            'fixed_numbers.max' => 'O número de dezenas fixas não pode ser maior que o tamanho da aposta menos 1.',
            'fixed_numbers.*.required' => 'A dezena fixa não pode ser vazia.',
            'fixed_numbers.*.distinct' => 'Não é permitido repetir dezenas fixas.',
            'fixed_numbers.*.between' => 'As dezenas fixas devem estar entre 1 e 25.',
            'fixed_numbers.*.in' => 'A dezena fixa :input não está no grupo-base.',

            'variable_numbers.required' => 'Selecione as dezenas variáveis.',
            'variable_numbers.array' => 'As dezenas variáveis devem ser uma lista.',
            'variable_numbers.min' => 'Selecione pelo menos uma dezena variável.',
            'variable_numbers.max' => 'O número de dezenas variáveis excede o restante do grupo-base.',
            'variable_numbers.*.required' => 'A dezena variável não pode ser vazia.',
            'variable_numbers.*.distinct' => 'Não é permitido repetir dezenas variáveis.',
            'variable_numbers.*.between' => 'As dezenas variáveis devem estar entre 1 e 25.',
            'variable_numbers.*.in' => 'A dezena variável :input não está no grupo-base.',
            'variable_numbers.*.not_in' => 'A dezena variável :input também está nas dezenas fixas.',

            'wheel_size.required' => 'Informe o tamanho da roda.',
            'wheel_size.integer' => 'O tamanho da roda deve ser um número inteiro.',
            'wheel_size.min' => 'O tamanho da roda deve ser de pelo menos 1.',
            'wheel_size.max' => 'O tamanho da roda não pode exceder o número de dezenas variáveis.',
            'wheel_size.size' => 'A soma das dezenas fixas e o tamanho da roda deve ser igual ao tamanho da aposta.',
        ];

        return $messages;
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
            'guarantee_hits' => 'acertos na base para garantia',
            'guarantee_points' => 'pontos garantidos',
            'min_even' => 'mínimo de pares',
            'max_even' => 'máximo de pares',
            'min_sum' => 'soma mínima',
            'max_sum' => 'soma máxima',
            'min_primes' => 'mínimo de primos',
            'max_primes' => 'máximo de primos',
            'min_fibonacci' => 'mínimo de Fibonacci',
            'max_fibonacci' => 'máximo de Fibonacci',
            'fixed_numbers' => 'dezenas fixas',
            'fixed_numbers.*' => 'dezena fixa',
            'variable_numbers' => 'dezenas variáveis',
            'variable_numbers.*' => 'dezena variável',
            'wheel_size' => 'tamanho da roda',
        ];
    }
}
