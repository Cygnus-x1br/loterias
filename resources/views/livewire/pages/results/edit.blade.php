<?php

use App\Models\HistoricalResult;
use App\Services\HistoricalResultService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Editar Concurso'])] class extends Component
{
    public HistoricalResult $result;

    public int $resultId;

    public ?int $contest_number = null;

    public string $draw_date = '';

    public array $drawn_numbers = [];

    // Campos de premiação opcionais
    public ?int $winners_15_hits = null;

    public ?string $payout_15_hits = null;

    public ?int $winners_14_hits = null;

    public ?string $payout_14_hits = null;

    public ?int $winners_13_hits = null;

    public ?string $payout_13_hits = null;

    public ?int $winners_12_hits = null;

    public ?string $payout_12_hits = null;

    public ?int $winners_11_hits = null;

    public ?string $payout_11_hits = null;

    public bool $processing = false;

    public function mount(HistoricalResult $result): void
    {
        $this->result = $result;
        $this->resultId = $result->id;

        $this->contest_number = $result->contest_number;
        $this->draw_date = $result->draw_date ? \Carbon\Carbon::parse($result->draw_date)->format('Y-m-d') : '';
        $this->drawn_numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : [];
        sort($this->drawn_numbers);

        $this->winners_15_hits = $result->winners_15_hits;
        $this->payout_15_hits = $result->payout_15_hits ? (string) $result->payout_15_hits : null;

        $this->winners_14_hits = $result->winners_14_hits;
        $this->payout_14_hits = $result->payout_14_hits ? (string) $result->payout_14_hits : null;

        $this->winners_13_hits = $result->winners_13_hits;
        $this->payout_13_hits = $result->payout_13_hits ? (string) $result->payout_13_hits : null;

        $this->winners_12_hits = $result->winners_12_hits;
        $this->payout_12_hits = $result->payout_12_hits ? (string) $result->payout_12_hits : null;

        $this->winners_11_hits = $result->winners_11_hits;
        $this->payout_11_hits = $result->payout_11_hits ? (string) $result->payout_11_hits : null;
    }

    public function rules(): array
    {
        return [
            'contest_number' => [
                'required',
                'integer',
                'min:1',
                'unique:historical_results,contest_number,' . $this->resultId,
            ],
            'draw_date' => [
                'required',
                'date',
            ],
            'drawn_numbers' => [
                'required',
                'array',
                'size:15',
            ],
            'drawn_numbers.*' => [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ],
            'winners_15_hits' => ['nullable', 'integer', 'min:0'],
            'payout_15_hits' => ['nullable', 'numeric', 'min:0'],
            'winners_14_hits' => ['nullable', 'integer', 'min:0'],
            'payout_14_hits' => ['nullable', 'numeric', 'min:0'],
            'winners_13_hits' => ['nullable', 'integer', 'min:0'],
            'payout_13_hits' => ['nullable', 'numeric', 'min:0'],
            'winners_12_hits' => ['nullable', 'integer', 'min:0'],
            'payout_12_hits' => ['nullable', 'numeric', 'min:0'],
            'winners_11_hits' => ['nullable', 'integer', 'min:0'],
            'payout_11_hits' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'contest_number.required' => 'O número do concurso é obrigatório.',
            'contest_number.integer' => 'O número do concurso deve ser um número inteiro.',
            'contest_number.min' => 'O número do concurso deve ser maior que zero.',
            'contest_number.unique' => 'Este número de concurso já está cadastrado em outro registro.',

            'draw_date.required' => 'A data do sorteio é obrigatória.',
            'draw_date.date' => 'Informe uma data válida para o sorteio.',

            'drawn_numbers.required' => 'Selecione as 15 dezenas sorteadas.',
            'drawn_numbers.array' => 'As dezenas sorteadas devem ser enviadas em formato de lista.',
            'drawn_numbers.size' => 'O sorteio deve conter exatamente 15 dezenas.',

            'drawn_numbers.*.required' => 'Todas as dezenas devem ser válidas.',
            'drawn_numbers.*.integer' => 'As dezenas devem ser números inteiros.',
            'drawn_numbers.*.distinct' => 'Não é permitido repetir dezenas.',
            'drawn_numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',
        ];
    }

    public function toggleNumber(int $number): void
    {
        if ($number < 1 || $number > 25) {
            return;
        }

        if (in_array($number, $this->drawn_numbers, true)) {
            $this->drawn_numbers = array_values(
                array_filter(
                    $this->drawn_numbers,
                    fn (int $selected): bool => $selected !== $number
                )
            );

            return;
        }

        if (count($this->drawn_numbers) >= 15) {
            $this->addError(
                'drawn_numbers',
                'Você já selecionou 15 dezenas. Remova uma antes de selecionar outra.'
            );

            return;
        }

        $this->resetErrorBag('drawn_numbers');
        $this->drawn_numbers[] = $number;
        sort($this->drawn_numbers);
    }

    public function clearNumbers(): void
    {
        $this->drawn_numbers = [];
        $this->resetValidation('drawn_numbers');
    }

    public function selectRandomNumbers(): void
    {
        $available = range(1, 25);
        shuffle($available);
        $this->drawn_numbers = array_slice($available, 0, 15);
        sort($this->drawn_numbers);
        $this->resetValidation('drawn_numbers');
    }

    public function save(HistoricalResultService $service): void
    {
        $this->processing = true;

        try {
            $validated = $this->validate();

            $service->update($this->result, $validated);

            session()->flash('success', "Concurso {$validated['contest_number']} atualizado com sucesso!");

            $this->redirectRoute('results.index');
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
                <a href="{{ route('dashboard') }}" class="transition hover:text-indigo-600">
                    Dashboard
                </a>
                <span>/</span>
                <a href="{{ route('results.index') }}" class="transition hover:text-indigo-600">
                    Sorteios
                </a>
                <span>/</span>
                <span class="font-medium text-slate-700">
                    Editar concurso #{{ $contest_number }}
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-amber-100 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                Edição de Concurso
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Editar Concurso #{{ $contest_number }}
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Atualize as dezenas ou informações de rateio do sorteio.
            </p>
        </div>

        <a
            href="{{ route('results.index') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Voltar para sorteios
        </a>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        {{-- Seletor de Dezenas --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Dezenas Sorteadas (15 números) <span class="text-rose-500">*</span>
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Clique sobre os números para selecionar ou remover as 15 dezenas do concurso.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                        {{ count($drawn_numbers) }}/15 selecionadas
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

            <div class="mt-6 grid grid-cols-5 gap-2.5 sm:grid-cols-5 md:grid-cols-5 lg:grid-cols-5">
                @foreach (range(1, 25) as $number)
                    @php
                        $selected = in_array($number, $drawn_numbers, true);
                    @endphp

                    <button
                        type="button"
                        wire:key="number-{{ $number }}"
                        wire:click="toggleNumber({{ $number }})"
                        @class([
                            'flex h-12 items-center justify-center rounded-xl border text-base font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => $selected,
                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! $selected,
                        ])
                    >
                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                    </button>
                @endforeach
            </div>

            @error('drawn_numbers')
                <p class="mt-4 text-sm font-medium text-rose-600">
                    {{ $message }}
                </p>
            @enderror
        </section>

        {{-- Resumo Lateral --}}
        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Resumo do Sorteio
                    </h2>
                    <p class="text-sm text-slate-500">
                        Dezenas ordenadas
                    </p>
                </div>
            </div>

            <div class="mt-6">
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                    Dezenas Selecionadas
                </p>

                @if (count($drawn_numbers) > 0)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($drawn_numbers as $num)
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white shadow-sm">
                                {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="mt-3 rounded-xl bg-slate-50 p-4 text-sm text-slate-500">
                        Nenhuma dezena selecionada ainda.
                    </p>
                @endif
            </div>

            <div class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Concurso</span>
                    <span class="font-bold text-slate-800">
                        {{ $contest_number ? '#' . $contest_number : 'Não definido' }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Data</span>
                    <span class="font-semibold text-slate-800">
                        {{ $draw_date ? \Carbon\Carbon::parse($draw_date)->format('d/m/Y') : 'Não definida' }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-slate-500">Total de dezenas</span>
                    <span class="font-bold {{ count($drawn_numbers) === 15 ? 'text-emerald-600' : 'text-amber-600' }}">
                        {{ count($drawn_numbers) }} / 15
                    </span>
                </div>
            </div>
        </aside>
    </div>

    {{-- Dados do Concurso e Premiações --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-6">
            <h2 class="text-base font-bold text-slate-900">
                Informações do Concurso
            </h2>
            <p class="mt-1 text-sm text-slate-500">
                Campos marcados com <span class="text-rose-500">*</span> são obrigatórios.
            </p>
        </div>

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="contest_number" class="block text-sm font-semibold text-slate-700">
                    Número do Concurso <span class="text-rose-500">*</span>
                </label>
                <input
                    id="contest_number"
                    type="number"
                    wire:model="contest_number"
                    min="1"
                    required
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                @error('contest_number')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="draw_date" class="block text-sm font-semibold text-slate-700">
                    Data do Sorteio <span class="text-rose-500">*</span>
                </label>
                <input
                    id="draw_date"
                    type="date"
                    wire:model="draw_date"
                    required
                    class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                @error('draw_date')
                    <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="mt-8 border-t border-slate-100 pt-6">
            <h3 class="text-sm font-bold text-slate-900">
                Premiação e Ganhadores (Opcional)
            </h3>
            <p class="mt-1 text-xs text-slate-500">
                Você pode registrar a quantidade de ganhadores e o valor do rateio para cada faixa de acerto.
            </p>

            <div class="mt-4 space-y-4">
                {{-- 15 Acertos --}}
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="winners_15_hits" class="block text-xs font-semibold text-slate-700">
                            Ganhadores (15 acertos)
                        </label>
                        <input
                            id="winners_15_hits"
                            type="number"
                            min="0"
                            wire:model="winners_15_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('winners_15_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payout_15_hits" class="block text-xs font-semibold text-slate-700">
                            Rateio R$ (15 acertos)
                        </label>
                        <input
                            id="payout_15_hits"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="payout_15_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('payout_15_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 14 Acertos --}}
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="winners_14_hits" class="block text-xs font-semibold text-slate-700">
                            Ganhadores (14 acertos)
                        </label>
                        <input
                            id="winners_14_hits"
                            type="number"
                            min="0"
                            wire:model="winners_14_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('winners_14_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payout_14_hits" class="block text-xs font-semibold text-slate-700">
                            Rateio R$ (14 acertos)
                        </label>
                        <input
                            id="payout_14_hits"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="payout_14_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('payout_14_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 13 Acertos --}}
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="winners_13_hits" class="block text-xs font-semibold text-slate-700">
                            Ganhadores (13 acertos)
                        </label>
                        <input
                            id="winners_13_hits"
                            type="number"
                            min="0"
                            wire:model="winners_13_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('winners_13_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payout_13_hits" class="block text-xs font-semibold text-slate-700">
                            Rateio R$ (13 acertos)
                        </label>
                        <input
                            id="payout_13_hits"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="payout_13_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('payout_13_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 12 Acertos --}}
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="winners_12_hits" class="block text-xs font-semibold text-slate-700">
                            Ganhadores (12 acertos)
                        </label>
                        <input
                            id="winners_12_hits"
                            type="number"
                            min="0"
                            wire:model="winners_12_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('winners_12_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payout_12_hits" class="block text-xs font-semibold text-slate-700">
                            Rateio R$ (12 acertos)
                        </label>
                        <input
                            id="payout_12_hits"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="payout_12_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('payout_12_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                {{-- 11 Acertos --}}
                <div class="grid gap-4 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <div>
                        <label for="winners_11_hits" class="block text-xs font-semibold text-slate-700">
                            Ganhadores (11 acertos)
                        </label>
                        <input
                            id="winners_11_hits"
                            type="number"
                            min="0"
                            wire:model="winners_11_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('winners_11_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="payout_11_hits" class="block text-xs font-semibold text-slate-700">
                            Rateio R$ (11 acertos)
                        </label>
                        <input
                            id="payout_11_hits"
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model="payout_11_hits"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('payout_11_hits') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Ações de Submissão --}}
    <section class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <a
            href="{{ route('results.index') }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            Cancelar
        </a>

        <button
            type="button"
            wire:click="save"
            wire:loading.attr="disabled"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
        >
            <svg
                wire:loading
                wire:target="save"
                class="h-4 w-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>

            <span wire:loading.remove wire:target="save">
                Salvar Alterações
            </span>

            <span wire:loading wire:target="save">
                Salvando alterações...
            </span>
        </button>
    </section>
</div>
