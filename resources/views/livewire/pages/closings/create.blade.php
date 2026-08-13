<?php

use App\Models\Closing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Novo fechamento'])] class extends Component
{
    public string $name = '';

    public string $method = 'reduced'; // Valor inicial pode ser 'wheel' se quiser testar

    public array $base_numbers = [];

    public int $bet_size = 15;

    public int $planned_bets = 10;

    public string $guarantee = '';

    public string $budget = '';

    public string $notes = '';

    public bool $processing = false;

    // Parâmetros para geração equilibrada
    public ?int $min_even = null;
    public ?int $max_even = null;
    public ?int $min_sum = null;
    public ?int $max_sum = null;
    public ?int $min_primes = null;
    public ?int $max_primes = null;
    public ?int $min_fibonacci = null;
    public ?int $max_fibonacci = null;

    // Novos parâmetros para sistema de roda
    public array $fixed_numbers = [];
    public array $variable_numbers = [];
    public ?int $wheel_size = null;

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

        // Regras condicionais para o método 'balanced'
        if ($this->method === 'balanced') {
            $rules['min_even'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_even'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_even'];
            $rules['min_sum'] = ['nullable', 'integer', 'min:15', 'max:300'];
            $rules['max_sum'] = ['nullable', 'integer', 'min:15', 'max:300', 'gte:min_sum'];
            $rules['min_primes'] = ['nullable', 'integer', 'min:0', 'max:9'];
            $rules['max_primes'] = ['nullable', 'integer', 'min:0', 'max:9', 'gte:min_primes'];
            $rules['min_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7'];
            $rules['max_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7', 'gte:min_fibonacci'];
        }

        // Regras condicionais para o método 'wheel'
        if ($this->method === 'wheel') {
            $rules['fixed_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . ($this->bet_size - 1), // Pelo menos uma variável
            ];
            $rules['fixed_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ];
            $rules['variable_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . (count($this->base_numbers) - count($this->fixed_numbers)), // Não pode exceder o restante do grupo-base
            ];
            $rules['variable_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ];
            $rules['wheel_size'] = [
                'required',
                'integer',
                'min:1',
                'max:' . count($this->variable_numbers),
                'size:' . ($this->bet_size - count($this->fixed_numbers)), // fixed + wheel_size = bet_size
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para o fechamento.',
            'name.max' => 'O nome não pode ultrapassar 255 caracteres.',

            'method.required' => 'Selecione o método do fechamento.',
            'method.in' => 'O método informado não é válido.',

            'base_numbers.required' => 'Selecione as dezenas do grupo-base.',
            'base_numbers.between' => 'O grupo-base deve conter entre 15 e 25 dezenas.',
            'base_numbers.*.distinct' => 'Não é permitido repetir dezenas no grupo-base.',
            'base_numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',

            'bet_size.required' => 'Informe o tamanho de cada aposta.',
            'bet_size.between' => 'O tamanho da aposta deve estar entre 15 e 25.',

            'planned_bets.required' => 'Informe a quantidade planejada de apostas.',
            'planned_bets.min' => 'A quantidade planejada deve ser maior que zero.',

            'guarantee.between' => 'A garantia informada não é válida.',

            'budget.numeric' => 'O orçamento deve ser um valor numérico.',
            'budget.min' => 'O orçamento não pode ser negativo.',

            'notes.max' => 'As observações não podem ultrapassar 2.000 caracteres.',

            // Mensagens para os parâmetros de equilíbrio
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

            // Mensagens para os parâmetros do sistema de roda
            'fixed_numbers.required' => 'Selecione as dezenas fixas.',
            'fixed_numbers.array' => 'As dezenas fixas devem ser uma lista.',
            'fixed_numbers.min' => 'Selecione pelo menos uma dezena fixa.',
            'fixed_numbers.max' => 'O número de dezenas fixas não pode ser maior que o tamanho da aposta menos 1.',
            'fixed_numbers.*.required' => 'A dezena fixa não pode ser vazia.',
            'fixed_numbers.*.distinct' => 'Não é permitido repetir dezenas fixas.',
            'fixed_numbers.*.between' => 'As dezenas fixas devem estar entre 1 e 25.',

            'variable_numbers.required' => 'Selecione as dezenas variáveis.',
            'variable_numbers.array' => 'As dezenas variáveis devem ser uma lista.',
            'variable_numbers.min' => 'Selecione pelo menos uma dezena variável.',
            'variable_numbers.max' => 'O número de dezenas variáveis excede o restante do grupo-base.',
            'variable_numbers.*.required' => 'A dezena variável não pode ser vazia.',
            'variable_numbers.*.distinct' => 'Não é permitido repetir dezenas variáveis.',
            'variable_numbers.*.between' => 'As dezenas variáveis devem estar entre 1 e 25.',

            'wheel_size.required' => 'Informe o tamanho da roda.',
            'wheel_size.integer' => 'O tamanho da roda deve ser um número inteiro.',
            'wheel_size.min' => 'O tamanho da roda deve ser de pelo menos 1.',
            'wheel_size.max' => 'O tamanho da roda não pode exceder o número de dezenas variáveis.',
            'wheel_size.size' => 'A soma das dezenas fixas e o tamanho da roda deve ser igual ao tamanho da aposta.',
        ];
    }

    public function toggleNumber(int $number): void
    {
        if ($number < 1 || $number > 25) {
            return;
        }

        if (in_array($number, $this->base_numbers, true)) {
            $this->base_numbers = array_values(
                array_filter(
                    $this->base_numbers,
                    fn (int $selectedNumber): bool => $selectedNumber !== $number
                )
            );

            // Remove dezenas fixas/variáveis se forem desmarcadas do grupo-base
            $this->fixed_numbers = array_values(array_filter($this->fixed_numbers, fn($n) => $n !== $number));
            $this->variable_numbers = array_values(array_filter($this->variable_numbers, fn($n) => $n !== $number));

            if ($this->bet_size > count($this->base_numbers)) {
                $this->bet_size = max(15, count($this->base_numbers));
            }

            $this->resetValidation('base_numbers');
            $this->resetValidation('fixed_numbers');
            $this->resetValidation('variable_numbers');

            return;
        }

        if (count($this->base_numbers) >= 25) {
            $this->addError(
                'base_numbers',
                'O grupo-base pode conter no máximo 25 dezenas.'
            );

            return;
        }

        $this->resetValidation('base_numbers');

        $this->base_numbers[] = $number;

        sort($this->base_numbers);
    }

    // Métodos para toggle de dezenas fixas/variáveis
    public function toggleFixedNumber(int $number): void
    {
        if (!in_array($number, $this->base_numbers, true)) {
            $this->addError('fixed_numbers', "A dezena {$number} não está no grupo-base.");
            return;
        }

        if (in_array($number, $this->fixed_numbers, true)) {
            $this->fixed_numbers = array_values(array_filter($this->fixed_numbers, fn($n) => $n !== $number));
        } elseif (!in_array($number, $this->variable_numbers, true)) { // Não pode ser fixa e variável ao mesmo tempo
            $this->fixed_numbers[] = $number;
            sort($this->fixed_numbers);
        }
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
    }

    public function toggleVariableNumber(int $number): void
    {
        if (!in_array($number, $this->base_numbers, true)) {
            $this->addError('variable_numbers', "A dezena {$number} não está no grupo-base.");
            return;
        }

        if (in_array($number, $this->variable_numbers, true)) {
            $this->variable_numbers = array_values(array_filter($this->variable_numbers, fn($n) => $n !== $number));
        } elseif (!in_array($number, $this->fixed_numbers, true)) { // Não pode ser fixa e variável ao mesmo tempo
            $this->variable_numbers[] = $number;
            sort($this->variable_numbers);
        }
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
    }

    public function clearNumbers(): void
    {
        $this->base_numbers = [];
        $this->bet_size = 15;
        $this->fixed_numbers = [];
        $this->variable_numbers = [];
        $this->wheel_size = null;

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
    }

    public function selectRandomNumbers(): void
    {
        $availableNumbers = range(1, 25);

        shuffle($availableNumbers);

        $this->base_numbers = array_slice($availableNumbers, 0, 20);

        sort($this->base_numbers);

        $this->bet_size = 15;
        $this->fixed_numbers = [];
        $this->variable_numbers = [];
        $this->wheel_size = null;

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
    }

    public function save(): void
    {
        $this->processing = true;

        try {
            $this->validate();

            if ($this->bet_size > count($this->base_numbers)) {
                $this->addError(
                    'bet_size',
                    'O tamanho da aposta não pode ser maior que o grupo-base.'
                );

                return;
            }

            // Validações manuais para o método 'wheel'
            if ($this->method === 'wheel') {
                // 1. Dezenas fixas devem estar no grupo-base
                foreach ($this->fixed_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('fixed_numbers', "A dezena fixa {$number} não está no grupo-base.");
                        return;
                    }
                }

                // 2. Dezenas variáveis devem estar no grupo-base
                foreach ($this->variable_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} não está no grupo-base.");
                        return;
                    }
                }

                // 3. Dezenas variáveis não podem estar nas dezenas fixas
                foreach ($this->variable_numbers as $number) {
                    if (in_array($number, $this->fixed_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} também está nas dezenas fixas.");
                        return;
                    }
                }
            }

            $parameters = null;
            if ($this->method === 'balanced') {
                $parameters = [];
                if ($this->min_even !== null && $this->max_even !== null) {
                    $parameters['even_odd_balance'] = [(int) $this->min_even, (int) $this->max_even];
                }
                if ($this->min_sum !== null && $this->max_sum !== null) {
                    $parameters['sum_range'] = [(int) $this->min_sum, (int) $this->max_sum];
                }
                if ($this->min_primes !== null && $this->max_primes !== null) {
                    $parameters['primes_count'] = [(int) $this->min_primes, (int) $this->max_primes];
                }
                if ($this->min_fibonacci !== null && $this->max_fibonacci !== null) {
                    $parameters['fibonacci_count'] = [(int) $this->min_fibonacci, (int) $this->max_fibonacci];
                }
                if (empty($parameters)) {
                    $parameters = null;
                }
            } elseif ($this->method === 'wheel') {
                $parameters = [];
                if (!empty($this->fixed_numbers)) {
                    $parameters['fixed_numbers'] = $this->fixed_numbers;
                }
                if (!empty($this->variable_numbers)) {
                    $parameters['variable_numbers'] = $this->variable_numbers;
                }
                if ($this->wheel_size !== null) {
                    $parameters['wheel_size'] = (int) $this->wheel_size;
                }
                if (empty($parameters)) {
                    $parameters = null;
                }
            }

            $closing = Closing::create([
                'user_id' => Auth::id(),
                'name' => $this->name,
                'method' => $this->method,
                'base_numbers' => $this->base_numbers,
                'bet_size' => $this->bet_size,
                'planned_bets' => $this->planned_bets,
                'guarantee' => $this->guarantee !== ''
                    ? (int) $this->guarantee
                    : null,
                'budget' => $this->budget !== ''
                    ? $this->budget
                    : null,
                'parameters' => $parameters,
                'status' => 'draft',
                'notes' => $this->notes !== ''
                    ? $this->notes
                    : null,
            ]);

            session()->flash(
                'success',
                "Fechamento {$closing->id} criado como rascunho."
            );

            $this->redirectRoute('closings.index');
        } finally {
            $this->processing = false;
        }
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
                <a
                    href="{{ route('dashboard') }}"
                    class="transition hover:text-indigo-600"
                >
                    Dashboard
                </a>

                <span>/</span>

                <a
                    href="{{ route('closings.index') }}"
                    class="transition hover:text-indigo-600"
                >
                    Fechamentos
                </a>

                <span>/</span>

                <span class="font-medium text-slate-700">
                    Novo fechamento
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Configuração de fechamento
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Novo fechamento
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Defina o grupo-base e os parâmetros que serão utilizados em um futuro fechamento.
            </p>
        </div>

        <a
            href="{{ route('closings.index') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                />
            </svg>

            Voltar para fechamentos
        </a>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Grupo-base
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Selecione entre 15 e 25 dezenas para formar o grupo-base.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                        {{ count($base_numbers) }}/25
                    </span>

                    <button
                        type="button"
                        wire:click="clearNumbers"
                        wire:loading.attr="disabled"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Limpar
                    </button>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                @foreach (range(1, 25) as $number)
                    @php
                        $selected = in_array($number, $base_numbers, true);
                    @endphp

                    <button
                        type="button"
                        wire:key="closing-number-{{ $number }}"
                        wire:click="toggleNumber({{ $number }})"
                        @class([
                            'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => $selected,
                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! $selected,
                        ])
                    >
                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                    </button>
                @endforeach
            </div>

            @error('base_numbers')
                <p class="mt-4 text-sm font-medium text-rose-600">
                    {{ $message }}
                </p>
            @enderror

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    wire:click="selectRandomNumbers"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004 12c0 2.972 1.154 5.667 3.034 7.75M20 20v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>

                    Selecionar 20 aleatórias
                </button>
            </div>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l4.414 4.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Configuração
                    </h2>

                    <p class="text-sm text-slate-500">
                        Parâmetros do fechamento
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <label
                        for="name"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Nome do fechamento
                    </label>

                    <input
                        id="name"
                        type="text"
                        wire:model="name"
                        placeholder="Ex: Meus números da sorte"
                        autocomplete="off"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('name')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="method"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Método de geração
                    </label>

                    <select
                        id="method"
                        wire:model.live="method"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="integral">Combinação integral</option>
                        <option value="random">Geração aleatória</option>
                        <option value="balanced">Geração equilibrada</option>
                        <option value="wheel">Sistema de roda</option>
                        <option value="reduced">Fechamento reduzido (em breve)</option>
                    </select>

                    @error('method')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="bet_size"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Tamanho da aposta
                    </label>

                    <input
                        id="bet_size"
                        type="number"
                        wire:model="bet_size"
                        min="15"
                        max="25"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('bet_size')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="planned_bets"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Apostas planejadas
                    </label>

                    <input
                        id="planned_bets"
                        type="number"
                        wire:model="planned_bets"
                        min="1"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('planned_bets')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Campos para o método Balanced --}}
                @if ($method === 'balanced')
                    <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <h3 class="text-base font-bold text-indigo-700">
                            Parâmetros de Equilíbrio
                        </h3>

                        <div>
                            <label
                                for="min_even"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Pares (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_even"
                                    type="number"
                                    wire:model="min_even"
                                    placeholder="Mín"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_even"
                                    placeholder="Máx"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_even')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_even')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_sum"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Soma das Dezenas (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_sum"
                                    type="number"
                                    wire:model="min_sum"
                                    placeholder="Mín"
                                    min="15"
                                    max="300"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_sum"
                                    placeholder="Máx"
                                    min="15"
                                    max="300"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_sum')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_sum')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_primes"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Primas (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_primes"
                                    type="number"
                                    wire:model="min_primes"
                                    placeholder="Mín"
                                    min="0"
                                    max="9"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_primes"
                                    placeholder="Máx"
                                    min="0"
                                    max="9"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_primes')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_primes')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_fibonacci"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Fibonacci (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_fibonacci"
                                    type="number"
                                    wire:model="min_fibonacci"
                                    placeholder="Mín"
                                    min="0"
                                    max="7"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_fibonacci"
                                    placeholder="Máx"
                                    min="0"
                                    max="7"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_fibonacci')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_fibonacci')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- Campos para o método Wheel --}}
                @if ($method === 'wheel')
                    <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <h3 class="text-base font-bold text-indigo-700">
                            Parâmetros do Sistema de Roda
                        </h3>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Dezenas Fixas (presentes em todas as apostas)
                            </label>
                            <div class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                                @foreach ($base_numbers as $number)
                                    <button
                                        type="button"
                                        wire:key="fixed-number-{{ $number }}"
                                        wire:click="toggleFixedNumber({{ $number }})"
                                        @class([
                                            'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => in_array($number, $fixed_numbers, true),
                                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! in_array($number, $fixed_numbers, true),
                                        ])
                                    >
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </button>
                                @endforeach
                            </div>
                            @error('fixed_numbers')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('fixed_numbers.*')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Dezenas Variáveis (serão combinadas)
                            </label>
                            <div class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                                @foreach ($base_numbers as $number)
                                    @if (!in_array($number, $fixed_numbers, true))
                                        <button
                                            type="button"
                                            wire:key="variable-number-{{ $number }}"
                                            wire:click="toggleVariableNumber({{ $number }})"
                                            @class([
                                                'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                                'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => in_array($number, $variable_numbers, true),
                                                'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! in_array($number, $variable_numbers, true),
                                            ])
                                        >
                                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                            @error('variable_numbers')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('variable_numbers.*')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="wheel_size"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Tamanho da Roda (quantas variáveis por aposta)
                            </label>

                            <input
                                id="wheel_size"
                                type="number"
                                wire:model="wheel_size"
                                min="1"
                                max="{{ count($variable_numbers) }}"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >

                            @error('wheel_size')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endif

                <div>
                    <label
                        for="guarantee"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Garantia (ex: 15 acertos)
                    </label>

                    <input
                        id="guarantee"
                        type="number"
                        wire:model="guarantee"
                        min="15"
                        max="15"
                        placeholder="Não obrigatório"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('guarantee')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="budget"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Orçamento (R$)
                    </label>

                    <input
                        id="budget"
                        type="number"
                        wire:model="budget"
                        min="0"
                        step="0.01"
                        placeholder="Não obrigatório"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('budget')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="notes"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Observações
                    </label>

                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="3"
                        maxlength="2000"
                        placeholder="Anote detalhes importantes sobre este fechamento..."
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>

                    @error('notes')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </aside>
    </div>

    <div class="flex justify-end">
        <button
            type="submit"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>

            <span wire:loading.remove wire:target="save">
                Salvar fechamento
            </span>

            <span wire:loading wire:target="save">
                Salvando...
            </span>
        </button>
    </div>
</div>
