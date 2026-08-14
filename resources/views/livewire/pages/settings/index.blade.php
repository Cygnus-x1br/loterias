<?php

use App\Models\LotofacilSetting;
use App\Services\LotofacilSettingService;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Configurações do Sistema'])] class extends Component
{
    /**
     * Tabela de preços das apostas da Lotofácil (15 a 20 dezenas).
     *
     * @var array<int, float|string>
     */
    public array $prices = [];

    /**
     * Carrega os preços atuais configurados no sistema.
     */
    public function mount(LotofacilSettingService $service): void
    {
        $this->loadPrices($service);
    }

    /**
     * Regras de validação para os preços.
     */
    public function rules(): array
    {
        return [
            'prices' => ['required', 'array'],
            'prices.15' => ['required', 'numeric', 'min:0.01'],
            'prices.16' => ['required', 'numeric', 'min:0.01'],
            'prices.17' => ['required', 'numeric', 'min:0.01'],
            'prices.18' => ['required', 'numeric', 'min:0.01'],
            'prices.19' => ['required', 'numeric', 'min:0.01'],
            'prices.20' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'prices.*.required' => 'O valor da aposta é obrigatório.',
            'prices.*.numeric' => 'O valor deve ser numérico.',
            'prices.*.min' => 'O valor da aposta deve ser maior que zero.',
        ];
    }

    /**
     * Atualiza e persiste os novos preços configurados.
     */
    public function save(LotofacilSettingService $service): void
    {
        $this->validate();

        $service->savePrices($this->prices);

        session()->flash('success', 'Preços das apostas da Lotofácil atualizados com sucesso!');
        
        $this->loadPrices($service);
    }

    /**
     * Restaura os valores padrões oficiais da Caixa Econômica Federal.
     */
    public function restoreDefaults(LotofacilSettingService $service): void
    {
        $service->resetToDefaults();

        session()->flash('success', 'Preços padrões da Caixa Econômica Federal restaurados com sucesso!');

        $this->loadPrices($service);
    }

    /**
     * Recarrega os preços formatados no componente.
     */
    protected function loadPrices(LotofacilSettingService $service): void
    {
        $loadedPrices = $service->getPrices();
        $this->prices = [];
        
        for ($count = 15; $count <= 20; $count++) {
            $this->prices[$count] = number_format((float) ($loadedPrices[$count] ?? 0), 2, '.', '');
        }
    }
};
?>

<div class="mx-auto max-w-6xl space-y-6">
    {{-- Cabeçalho da Página --}}
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

                <span class="font-medium text-slate-700">
                    Configurações
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Parâmetros e Preços
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Configurações da Lotofácil
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Gerencie os valores unitários das apostas de acordo com a quantidade de dezenas jogadas (15 a 20 dezenas).
            </p>
        </div>

        <button
            type="button"
            wire:click="restoreDefaults"
            wire:confirm="Deseja realmente restaurar os preços oficiais da Caixa Econômica Federal?"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
        >
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Restaurar preços padrão
        </button>
    </section>

    {{-- Feedback de Sucesso --}}
    @if (session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>

            <p class="text-sm font-medium">
                {{ session('success') }}
            </p>
        </div>
    @endif

    {{-- Tabela e Formulário de Preços --}}
    <form wire:submit="save">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 p-5 sm:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Tabela de Preços por Quantidade de Dezenas
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Defina o valor em reais (R$) para cada formato de aposta permitida na Lotofácil.
                        </p>
                    </div>

                    <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                        15 a 20 dezenas
                    </span>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @php
                    $equivalences = [
                        15 => '1 aposta simples (15 dezenas)',
                        16 => 'Equivale a 16 apostas simples',
                        17 => 'Equivale a 136 apostas simples',
                        18 => 'Equivale a 816 apostas simples',
                        19 => 'Equivale a 3.876 apostas simples',
                        20 => 'Equivale a 15.504 apostas simples',
                    ];
                @endphp

                @foreach (range(15, 20) as $count)
                    <div class="flex flex-col gap-4 p-5 transition hover:bg-slate-50/50 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-lg font-black text-white shadow-md shadow-indigo-600/20">
                                {{ $count }}
                            </div>

                            <div>
                                <h3 class="font-bold text-slate-900">
                                    Aposta com {{ $count }} dezenas
                                </h3>
                                <p class="text-xs text-slate-500">
                                    {{ $equivalences[$count] }}
                                </p>
                            </div>
                        </div>

                        <div class="w-full sm:w-72">
                            <div class="relative rounded-xl shadow-sm">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <span class="text-sm font-semibold text-slate-400">R$</span>
                                </div>
                                
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    wire:model="prices.{{ $count }}"
                                    id="price_{{ $count }}"
                                    placeholder="0,00"
                                    class="block w-full rounded-xl border-slate-300 pl-10 text-sm font-semibold text-slate-800 shadow-sm transition focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>

                            @error("prices.{$count}")
                                <p class="mt-1 text-xs font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 p-5 sm:p-6">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" />
                    </svg>
                    <span>Os valores configurados serão usados para cálculo automático de orçamentos e estimativas.</span>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:opacity-60"
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
                        Salvar configurações
                    </span>

                    <span wire:loading wire:target="save">
                        Salvando...
                    </span>
                </button>
            </div>
        </section>
    </form>

    {{-- Card Informativo --}}
    <section class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5 sm:p-6">
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                </svg>
            </div>

            <div class="space-y-1">
                <h3 class="font-bold text-indigo-950">
                    Como funciona a precificação da Lotofácil?
                </h3>
                <p class="text-sm leading-6 text-indigo-900/80">
                    Cada aposta acima de 15 dezenas corresponde matematicamente a uma combinação de múltiplos jogos simples de 15 dezenas (C<sub>n,15</sub>). 
                    Por exemplo, marcar 16 dezenas equivale a 16 jogos simples (16 × R$ 3,50 = R$ 56,00), e 20 dezenas equivale a 15.504 jogos simples (15.504 × R$ 3,50 = R$ 54.264,00). 
                    Você pode personalizar os valores caso deseje simular cenários com descontos ou custos adicionais de serviços/bolões.
                </p>
            </div>
        </div>
    </section>
</div>
