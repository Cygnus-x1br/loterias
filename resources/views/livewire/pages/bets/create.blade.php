<?php

use App\Models\Bet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Nova aposta'])] class extends Component
{
    public string $name = '';

    public array $numbers = [];

    public string $source = 'manual';

    public string $method = 'manual';

    public string $notes = '';

    public bool $processing = false;

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
                'required',
                'string',
                'in:manual,generated,imported,demonstrative',
            ],

            'method' => [
                'required',
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

    public function messages(): array
    {
        return [
            'name.max' => 'O nome da aposta não pode ultrapassar 255 caracteres.',

            'numbers.required' => 'Selecione as dezenas da aposta.',
            'numbers.array' => 'As dezenas devem ser informadas em formato de lista.',
            'numbers.size' => 'A aposta deve conter exatamente 15 dezenas.',

            'numbers.*.required' => 'Todas as dezenas devem ser informadas.',
            'numbers.*.integer' => 'As dezenas devem ser números inteiros.',
            'numbers.*.distinct' => 'Não é permitido repetir dezenas.',
            'numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',

            'source.required' => 'Selecione a origem da aposta.',
            'source.in' => 'A origem informada não é válida.',

            'method.required' => 'Selecione o método da aposta.',
            'method.in' => 'O método informado não é válido.',

            'notes.max' => 'As observações não podem ultrapassar 2.000 caracteres.',
        ];
    }

    public function toggleNumber(int $number): void
    {
        if ($number < 1 || $number > 25) {
            return;
        }

        if (in_array($number, $this->numbers, true)) {
            $this->numbers = array_values(
                array_filter(
                    $this->numbers,
                    fn (int $selectedNumber): bool => $selectedNumber !== $number
                )
            );

            return;
        }

        if (count($this->numbers) >= 15) {
            $this->addError(
                'numbers',
                'A aposta já possui 15 dezenas. Remova uma dezena antes de selecionar outra.'
            );

            return;
        }

        $this->resetErrorBag('numbers');

        $this->numbers[] = $number;

        sort($this->numbers);
    }

    public function clearNumbers(): void
    {
        $this->numbers = [];

        $this->resetValidation('numbers');
    }

    public function selectRandomNumbers(): void
    {
        $availableNumbers = range(1, 25);

        shuffle($availableNumbers);

        $this->numbers = array_slice($availableNumbers, 0, 15);

        sort($this->numbers);

        $this->resetValidation('numbers');
    }

    public function save(): void
    {
        $this->processing = true;

        try {
            $validated = $this->validate();

            $bet = Bet::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'] ?: 'Aposta manual',
                'numbers' => $validated['numbers'],
                'source' => $validated['source'],
                'method' => $validated['method'],
                'status' => 'active',
                'hits' => null,
                'notes' => $validated['notes'] ?: null,
            ]);

            session()->flash(
                'success',
                "Aposta {$bet->id} criada com sucesso."
            );

            $this->redirectRoute('bets.index');
        } finally {
            $this->processing = false;
        }
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
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
                    href="{{ route('bets.index') }}"
                    class="transition hover:text-indigo-600"
                >
                    Apostas
                </a>

                <span>/</span>

                <span class="font-medium text-slate-700">
                    Nova aposta
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Cadastro manual
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Nova aposta
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Selecione exatamente 15 dezenas entre 1 e 25 para cadastrar uma aposta.
            </p>
        </div>

        <a
            href="{{ route('bets.index') }}"
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

            Voltar para apostas
        </a>
    </section>

    @if (session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>

            <p class="text-sm font-medium">
                {{ session('success') }}
            </p>
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Escolha as dezenas
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Clique nas dezenas para adicionar ou remover da aposta.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                        {{ count($numbers) }}/15
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
                        $selected = in_array($number, $numbers, true);
                    @endphp

                    <button
                        type="button"
                        wire:key="number-{{ $number }}"
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

            @error('numbers')
                <p class="mt-4 text-sm font-medium text-rose-600">
                    {{ $message }}
                </p>
            @enderror

            <div class="mt-6 flex flex-col gap-3 rounded-2xl border border-sky-100 bg-sky-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z"
                        />
                    </svg>

                    <p class="text-sm leading-6 text-sky-800">
                        A seleção aleatória é apenas uma facilidade visual para preencher o formulário.
                        Nenhum algoritmo de geração é executado nesta etapa.
                    </p>
                </div>

                <button
                    type="button"
                    wire:click="selectRandomNumbers"
                    wire:loading.attr="disabled"
                    class="shrink-0 rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Selecionar aleatoriamente
                </button>
            </div>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Resumo da aposta
                    </h2>

                    <p class="text-sm text-slate-500">
                        Dados selecionados
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                    Dezenas escolhidas
                </p>

                @if (count($numbers) > 0)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($numbers as $number)
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white">
                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                        Nenhuma dezena selecionada.
                    </p>
                @endif
            </div>

            <div class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Quantidade</span>
                    <span class="font-bold text-slate-800">
                        {{ count($numbers) }} de 15
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Origem</span>
                    <span class="font-semibold text-slate-800">Manual</span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Status</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                        Aguardando cadastro
                    </span>
                </div>
            </div>
        </aside>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-6">
            <h2 class="text-base font-bold text-slate-900">
                Informações adicionais
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Opcionalmente, identifique e descreva esta aposta.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label
                    for="name"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Nome da aposta
                </label>

                <input
                    id="name"
                    type="text"
                    wire:model="name"
                    maxlength="255"
                    placeholder="Ex.: Aposta principal"
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >

                @error('name')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="method"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Método
                </label>

                <select
                    id="method"
                    wire:model="method"
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="manual">Seleção manual</option>
                    <option value="integral">Combinação integral</option>
                    <option value="reduced">Fechamento reduzido</option>
                    <option value="wheel">Sistema de roda</option>
                    <option value="random">Geração aleatória</option>
                    <option value="balanced">Geração equilibrada</option>
                </select>

                @error('method')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label
                    for="notes"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Observações
                </label>

                <textarea
                    id="notes"
                    wire:model="notes"
                    rows="4"
                    maxlength="2000"
                    placeholder="Adicione uma observação opcional sobre esta aposta."
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>

                @error('notes')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>
        </div>
    </section>

    <section class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <a
            href="{{ route('bets.index') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            Cancelar
        </a>

        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <svg
                wire:loading
                wire:target="save"
                class="h-4 w-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>

                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                ></path>
            </svg>

            <span wire:loading.remove wire:target="save">
                Salvar aposta
            </span>

            <span wire:loading wire:target="save">
                Salvando...
            </span>
        </button>
    </section>

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                Esta interface apenas cadastra a aposta selecionada.
                Não são realizados cálculos de probabilidade, geração de combinações ou previsão de resultados.
            </p>
        </div>
    </section>
</div>
