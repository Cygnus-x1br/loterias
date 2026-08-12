<?php

use App\Models\Closing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Novo fechamento'])] class extends Component
{
    public string $name = '';

    public string $method = 'reduced';

    public array $base_numbers = [];

    public int $bet_size = 15;

    public int $planned_bets = 10;

    public string $guarantee = '';

    public string $budget = '';

    public string $notes = '';

    public bool $processing = false;

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

            if ($this->bet_size > count($this->base_numbers)) {
                $this->bet_size = max(15, count($this->base_numbers));
            }

            $this->resetValidation('base_numbers');

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

    public function clearNumbers(): void
    {
        $this->base_numbers = [];
        $this->bet_size = 15;

        $this->resetValidation('base_numbers');
    }

    public function selectRandomNumbers(): void
    {
        $availableNumbers = range(1, 25);

        shuffle($availableNumbers);

        $this->base_numbers = array_slice($availableNumbers, 0, 20);

        sort($this->base_numbers);

        $this->bet_size = 15;

        $this->resetValidation('base_numbers');
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
                'parameters' => null,
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
                        A seleção aleatória apenas preenche 20 dezenas no grupo-base.
                        Nenhuma combinação é gerada nesta etapa.
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
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l4.414 4.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Resumo
                    </h2>

                    <p class="text-sm text-slate-500">
                        Parâmetros selecionados
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                    Dezenas do grupo-base
                </p>

                @if (count($base_numbers) > 0)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($base_numbers as $number)
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
                    <span class="text-slate-500">Grupo-base</span>
                    <span class="font-bold text-slate-800">
                        {{ count($base_numbers) }} dezenas
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Tamanho da aposta</span>
                    <span class="font-bold text-slate-800">
                        {{ $bet_size }} dezenas
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Apostas planejadas</span>
                    <span class="font-bold text-slate-800">
                        {{ $planned_bets }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Status</span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                        Rascunho
                    </span>
                </div>
            </div>
        </aside>
    </div>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-6">
            <h2 class="text-base font-bold text-slate-900">
                Parâmetros do fechamento
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Informe como o fechamento deverá ser configurado.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
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
                    maxlength="255"
                    placeholder="Ex.: Fechamento principal"
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
                    <option value="reduced">Fechamento reduzido</option>
                    <option value="integral">Combinação integral</option>
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

            <div>
                <label
                    for="bet_size"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Dezenas por aposta
                </label>

                <select
                    id="bet_size"
                    wire:model="bet_size"
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    @for ($size = 15; $size <= 25; $size++)
                        <option
                            value="{{ $size }}"
                            @disabled($size > count($base_numbers))
                        >
                            {{ $size }} dezenas
                        </option>
                    @endfor
                </select>

                @error('bet_size')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="planned_bets"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Quantidade planejada de apostas
                </label>

                <input
                    id="planned_bets"
                    type="number"
                    min="1"
                    wire:model="planned_bets"
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >

                @error('planned_bets')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="guarantee"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Garantia desejada
                </label>

                <select
                    id="guarantee"
                    wire:model="guarantee"
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Não informar</option>
                    <option value="15">15 acertos</option>
                </select>

                @error('guarantee')
                    <p class="mt-2 text-sm text-rose-600">
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <div>
                <label
                    for="budget"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Orçamento estimado
                </label>

                <div class="relative mt-2">
                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">
                        R$
                    </span>

                    <input
                        id="budget"
                        type="number"
                        min="0"
                        step="0.01"
                        wire:model="budget"
                        placeholder="0,00"
                        class="block w-full rounded-xl border-slate-300 pl-10 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                @error('budget')
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
                    placeholder="Adicione observações sobre este fechamento."
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
            href="{{ route('closings.index') }}"
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
                Salvar rascunho
            </span>

            <span wire:loading wire:target="save">
                Salvando...
            </span>
        </button>
    </section>

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <svg
                class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 3h-15a2 2 0 01-1.7-3l7.5-13.3a2 2 0 013.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                O fechamento será salvo inicialmente como rascunho.
                Nesta etapa ainda não são geradas combinações nem calculadas garantias matemáticas.
            </p>
        </div>
    </section>
</div>
