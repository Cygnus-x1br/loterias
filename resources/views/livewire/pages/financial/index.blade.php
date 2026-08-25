<?php

use App\Services\FinancialAnalysisService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Gastos e Retornos'])] class extends Component
{
    public ?int $contestFilter = null;

    public string $statusFilter = '';

    public array $expandedContests = [];

    public function mount(): void
    {
        // Por padrão, expande o concurso mais recente se existir
        $summary = app(FinancialAnalysisService::class)->getContestsBreakdown(Auth::id());
        if (! empty($summary)) {
            $firstContest = $summary[0]['contest_number'] ?? null;
            if ($firstContest) {
                $this->expandedContests[$firstContest] = true;
            }
        }
    }

    public function toggleContest(int $contestNumber): void
    {
        if (isset($this->expandedContests[$contestNumber])) {
            unset($this->expandedContests[$contestNumber]);
        } else {
            $this->expandedContests[$contestNumber] = true;
        }
    }

    public function expandAll(): void
    {
        $breakdown = app(FinancialAnalysisService::class)->getContestsBreakdown(
            Auth::id(),
            $this->contestFilter ?: null,
            $this->statusFilter ?: null
        );

        foreach ($breakdown as $item) {
            $this->expandedContests[$item['contest_number']] = true;
        }
    }

    public function collapseAll(): void
    {
        $this->expandedContests = [];
    }

    public function clearFilters(): void
    {
        $this->reset(['contestFilter', 'statusFilter']);
    }

    public function with(): array
    {
        $service = app(FinancialAnalysisService::class);
        $userId = Auth::id();

        $overallSummary = $service->getOverallSummary($userId);
        $contestsBreakdown = $service->getContestsBreakdown(
            $userId,
            $this->contestFilter ?: null,
            $this->statusFilter ?: null
        );

        return [
            'overallSummary' => $overallSummary,
            'contestsBreakdown' => $contestsBreakdown,
        ];
    }
}; ?>

<div class="mx-auto max-w-7xl space-y-6">
    <!-- Cabeçalho da Página -->
    <section class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Gestão Financeira & Desempenho
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Gastos e Retornos
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Acompanhe o balanço financeiro, premiações recebidas, custos e retorno sobre investimento (ROI) de todos os seus jogos apostados.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a
                href="{{ route('results.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
            >
                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Cadastrar sorteio
            </a>

            <a
                href="{{ route('closings.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Novo fechamento
            </a>
        </div>
    </section>

    <!-- Cards de Resumo Geral Acumulado -->
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <!-- Total Gasto -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Investido</p>
                    <p class="mt-2 text-2xl font-extrabold text-slate-900">
                        R$ {{ number_format($overallSummary['total_spent'], 2, ',', '.') }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $overallSummary['total_bets'] }} aposta(s) em {{ $overallSummary['total_contests'] }} concurso(s)
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 text-xs text-slate-500">
                Apenas jogos com status <strong>Apostado</strong> ou <strong>Conferido</strong>
            </div>
        </div>

        <!-- Total Ganho -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Prêmios Conquistados</p>
                    <p class="mt-2 text-2xl font-extrabold text-emerald-600">
                        R$ {{ number_format($overallSummary['total_return'], 2, ',', '.') }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $overallSummary['awarded_bets'] }} bilhete(s) premiado(s)
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 text-xs text-slate-500">
                Taxa de acerto premiado: <strong>{{ number_format($overallSummary['win_rate'], 1, ',', '.') }}%</strong>
            </div>
        </div>

        <!-- Saldo Líquido -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Saldo Líquido</p>
                    <p class="mt-2 text-2xl font-extrabold {{ $overallSummary['is_profit'] ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $overallSummary['is_profit'] ? '+' : '' }}R$ {{ number_format($overallSummary['net_profit'], 2, ',', '.') }}
                    </p>
                    <p class="mt-1 text-xs font-medium {{ $overallSummary['is_profit'] ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $overallSummary['is_profit'] ? '🟢 Lucro Acumulado' : '🔴 Prejuízo Acumulado' }}
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $overallSummary['is_profit'] ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600' }}">
                    @if ($overallSummary['is_profit'])
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    @else
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
                        </svg>
                    @endif
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 text-xs text-slate-500">
                Resultado líquido = Prêmios - Gastos
            </div>
        </div>

        <!-- ROI Geral -->
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Retorno sobre Investimento (ROI)</p>
                    <p class="mt-2 text-2xl font-extrabold {{ $overallSummary['roi'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                        {{ $overallSummary['roi'] >= 0 ? '+' : '' }}{{ number_format($overallSummary['roi'], 1, ',', '.') }}%
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $overallSummary['checked_bets'] }} conferidas / {{ $overallSummary['placed_bets'] }} aguardando
                    </p>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
            </div>
            <div class="mt-3 border-t border-slate-100 pt-2.5 text-xs text-slate-500">
                Rendimento percentual total
            </div>
        </div>
    </section>

    <!-- Seção de Filtros e Lista por Concurso -->
    <section class="space-y-4">
        <!-- Barra de Filtros e Controles -->
        <div class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center md:justify-between">
            <div class="flex flex-1 flex-wrap items-center gap-3">
                <!-- Filtro de Concurso -->
                <div class="w-full sm:w-48">
                    <label for="contestFilter" class="sr-only">Filtrar por Concurso</label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                            <span class="text-xs font-semibold text-slate-400">#</span>
                        </div>
                        <input
                            type="number"
                            id="contestFilter"
                            wire:model.live.debounce.300ms="contestFilter"
                            placeholder="Buscar concurso..."
                            class="block w-full rounded-xl border-slate-200 pl-7 text-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <!-- Filtro de Status -->
                <div class="w-full sm:w-52">
                    <label for="statusFilter" class="sr-only">Filtrar por Status</label>
                    <select
                        id="statusFilter"
                        wire:model.live="statusFilter"
                        class="block w-full rounded-xl border-slate-200 text-sm text-slate-700 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todos os status</option>
                        <option value="checked">Apenas Conferidos</option>
                        <option value="placed">Aguardando Sorteio</option>
                    </select>
                </div>

                @if ($contestFilter || $statusFilter !== '')
                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        Limpar filtros
                    </button>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="expandAll"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-slate-50"
                >
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    Expandir todos
                </button>

                <button
                    type="button"
                    wire:click="collapseAll"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-medium text-slate-600 shadow-sm transition hover:bg-slate-50"
                >
                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7" />
                    </svg>
                    Recolher todos
                </button>
            </div>
        </div>

        <!-- Lista Detalhada por Concurso -->
        @if (empty($contestsBreakdown))
            <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h3 class="mt-4 text-base font-bold text-slate-900">Nenhum concurso apostado encontrado</h3>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    @if ($contestFilter || $statusFilter !== '')
                        Não foram encontrados jogos para os filtros selecionados. Tente limpar os filtros acima.
                    @else
                        Para visualizar o balanço financeiro, marque suas apostas ou fechamentos como <strong>"Apostados"</strong> informando o número do concurso pretendido.
                    @endif
                </p>
                <div class="mt-6 flex justify-center gap-3">
                    <a
                        href="{{ route('closings.index') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        Ver meus Fechamentos
                    </a>
                    <a
                        href="{{ route('bets.index') }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        Ver minhas Apostas
                    </a>
                </div>
            </div>
        @else
            <div class="space-y-4">
                @foreach ($contestsBreakdown as $contest)
                    @php
                        $isExpanded = isset($expandedContests[$contest['contest_number']]);
                    @endphp

                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition">
                        <!-- Cabeçalho do Concurso (Toggle) -->
                        <div
                            wire:click="toggleContest({{ $contest['contest_number'] }})"
                            class="flex cursor-pointer flex-col justify-between gap-4 p-5 transition hover:bg-slate-50/80 md:flex-row md:items-center"
                        >
                            <!-- Identificação do Concurso -->
                            <div class="flex items-start gap-4">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700 font-black text-sm">
                                    #{{ $contest['contest_number'] }}
                                </div>

                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2 class="text-lg font-bold text-slate-900">
                                            Concurso #{{ $contest['contest_number'] }}
                                        </h2>

                                        @if ($contest['status'] === 'checked')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                Conferido
                                            </span>
                                        @elseif ($contest['status'] === 'partial')
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 border border-amber-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                Parcialmente conferido
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600 border border-slate-200">
                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                Aguardando Sorteio
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                        @if ($contest['draw_date'])
                                            <span>📅 {{ \Carbon\Carbon::parse($contest['draw_date'])->format('d/m/Y') }}</span>
                                        @endif
                                        <span>🎟️ {{ $contest['total_bets'] }} aposta(s)</span>
                                        @if ($contest['closings_count'] > 0)
                                            <span>📦 {{ $contest['closings_count'] }} fechamento(s)</span>
                                        @endif
                                        @if ($contest['individual_bets_count'] > 0)
                                            <span>📝 {{ $contest['individual_bets_count'] }} aposta(s) avulsa(s)</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Dezenas sorteadas (se houver) -->
                            @if (! empty($contest['drawn_numbers']))
                                <div class="hidden xl:flex flex-wrap items-center gap-1 max-w-xs">
                                    @foreach ($contest['drawn_numbers'] as $dNum)
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-indigo-600 text-[11px] font-bold text-white shadow-sm">
                                            {{ sprintf('%02d', $dNum) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Resumo Financeiro do Concurso e Chevron -->
                            <div class="flex items-center justify-between gap-6 border-t border-slate-100 pt-3 md:border-t-0 md:pt-0">
                                <div class="flex items-center gap-4 text-right">
                                    <div>
                                        <p class="text-xs text-slate-400">Gasto</p>
                                        <p class="text-sm font-semibold text-slate-700">
                                            R$ {{ number_format($contest['total_spent'], 2, ',', '.') }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-400">Retorno</p>
                                        <p class="text-sm font-semibold text-emerald-600">
                                            R$ {{ number_format($contest['total_return'], 2, ',', '.') }}
                                        </p>
                                    </div>

                                    <div>
                                        <p class="text-xs text-slate-400">Saldo</p>
                                        <p class="text-sm font-bold {{ $contest['is_profit'] ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $contest['is_profit'] ? '+' : '' }}R$ {{ number_format($contest['net_profit'], 2, ',', '.') }}
                                        </p>
                                    </div>

                                    <div class="hidden sm:block">
                                        <p class="text-xs text-slate-400">ROI</p>
                                        <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-bold {{ $contest['roi'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                            {{ $contest['roi'] >= 0 ? '+' : '' }}{{ number_format($contest['roi'], 1, ',', '.') }}%
                                        </span>
                                    </div>
                                </div>

                                <div class="text-slate-400 transition-transform duration-200 {{ $isExpanded ? 'rotate-180' : '' }}">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Sub-itens Expansíveis (Fechamentos e Apostas Avulsas) -->
                        @if ($isExpanded)
                            <div class="border-t border-slate-200 bg-slate-50/50 p-5 space-y-6">
                                <!-- Dezenas sorteadas em mobile/tablet -->
                                @if (! empty($contest['drawn_numbers']))
                                    <div class="xl:hidden rounded-xl border border-indigo-100 bg-indigo-50/50 p-3">
                                        <p class="text-xs font-semibold text-indigo-900 mb-2">Dezenas sorteadas no Concurso #{{ $contest['contest_number'] }}:</p>
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            @foreach ($contest['drawn_numbers'] as $dNum)
                                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white shadow-sm">
                                                    {{ sprintf('%02d', $dNum) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Bloco de Fechamentos do Concurso -->
                                @if (! empty($contest['closings']))
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                Fechamentos Realizados ({{ count($contest['closings']) }})
                                            </h3>
                                        </div>

                                        <div class="grid gap-3">
                                            @foreach ($contest['closings'] as $closing)
                                                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                                                    <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                                                        <div>
                                                            <div class="flex items-center gap-2">
                                                                <h4 class="font-bold text-slate-900">
                                                                    {{ $closing['name'] }}
                                                                </h4>
                                                                <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                                                    {{ $closing['method'] }}
                                                                </span>
                                                                @if ($closing['status'] === 'checked')
                                                                    <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                                                                        Conferido
                                                                    </span>
                                                                @else
                                                                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-500">
                                                                        Aguardando
                                                                    </span>
                                                                @endif
                                                            </div>
                                                            <p class="mt-1 text-xs text-slate-500">
                                                                {{ $closing['total_bets'] }} jogos gerados
                                                                @if ($closing['checked_bets'] > 0)
                                                                    • {{ $closing['checked_bets'] }} conferidos
                                                                @endif
                                                            </p>
                                                        </div>

                                                        <div class="flex flex-wrap items-center gap-4 text-sm">
                                                            <div>
                                                                <span class="text-xs text-slate-400">Gasto:</span>
                                                                <span class="font-semibold text-slate-700">R$ {{ number_format($closing['total_spent'], 2, ',', '.') }}</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-xs text-slate-400">Retorno:</span>
                                                                <span class="font-semibold text-emerald-600">R$ {{ number_format($closing['total_return'], 2, ',', '.') }}</span>
                                                            </div>
                                                            <div>
                                                                <span class="text-xs text-slate-400">Saldo:</span>
                                                                <span class="font-bold {{ $closing['net_profit'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">
                                                                    {{ $closing['net_profit'] >= 0 ? '+' : '' }}R$ {{ number_format($closing['net_profit'], 2, ',', '.') }}
                                                                </span>
                                                            </div>
                                                            <div>
                                                                <span class="inline-flex rounded-lg px-2 py-0.5 text-xs font-bold {{ $closing['roi'] >= 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                                                    ROI {{ $closing['roi'] >= 0 ? '+' : '' }}{{ number_format($closing['roi'], 1, ',', '.') }}%
                                                                </span>
                                                            </div>

                                                            <a
                                                                href="{{ route('closings.show', $closing['id']) }}"
                                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 transition"
                                                            >
                                                                Ver fechamento
                                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                                </svg>
                                                            </a>
                                                        </div>
                                                    </div>

                                                    <!-- Resumo de acertos do fechamento -->
                                                    @if ($closing['checked_bets'] > 0)
                                                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-xs">
                                                            <span class="font-semibold text-slate-500">Faixas de Acertos:</span>
                                                            @foreach ([15, 14, 13, 12, 11] as $hitsTier)
                                                                @if (! empty($closing['hits_distribution'][$hitsTier]))
                                                                    <span class="inline-flex items-center gap-1 rounded-md {{ $hitsTier >= 14 ? 'bg-amber-100 text-amber-800 font-bold' : 'bg-emerald-50 text-emerald-700 font-semibold' }} px-2 py-0.5">
                                                                        {{ $closing['hits_distribution'][$hitsTier] }}x com {{ $hitsTier }} acertos
                                                                    </span>
                                                                @endif
                                                            @endforeach

                                                            @if (! empty($closing['hits_distribution']['outros']))
                                                                <span class="rounded-md bg-slate-100 text-slate-500 px-2 py-0.5">
                                                                    {{ $closing['hits_distribution']['outros'] }}x abaixo de 11 acertos
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Bloco de Apostas Avulsas (Individuais) do Concurso -->
                                @if (! empty($contest['individual_bets']))
                                    <div class="space-y-3">
                                        <div class="flex items-center justify-between">
                                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                                                Apostas Individuais ({{ count($contest['individual_bets']) }})
                                            </h3>
                                        </div>

                                        <div class="grid gap-2">
                                            @foreach ($contest['individual_bets'] as $indBet)
                                                <div class="flex flex-col justify-between gap-3 rounded-xl border border-slate-200 bg-white p-3.5 shadow-sm sm:flex-row sm:items-center">
                                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4">
                                                        <div class="font-semibold text-sm text-slate-800">
                                                            {{ $indBet['name'] }}
                                                        </div>

                                                        <!-- Dezenas da Aposta -->
                                                        <div class="flex flex-wrap items-center gap-1">
                                                            @foreach ($indBet['numbers'] as $num)
                                                                @php
                                                                    $isHit = in_array((int) $num, $contest['drawn_numbers']);
                                                                @endphp
                                                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold {{ $isHit ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-700' }}">
                                                                    {{ sprintf('%02d', $num) }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    </div>

                                                    <div class="flex items-center justify-between gap-4 border-t border-slate-100 pt-2 text-xs sm:border-t-0 sm:pt-0">
                                                        @if ($indBet['status'] === 'checked')
                                                            <div class="flex items-center gap-1">
                                                                <span class="font-semibold text-slate-600">Acertos:</span>
                                                                <span class="rounded-md {{ $indBet['hits'] >= 11 ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-slate-100 text-slate-600' }} px-2 py-0.5">
                                                                    {{ $indBet['hits'] }} pts
                                                                </span>
                                                            </div>
                                                        @else
                                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-slate-500">
                                                                Aguardando
                                                            </span>
                                                        @endif

                                                        <div class="text-right">
                                                            <span class="text-slate-400">Custo:</span>
                                                            <span class="font-medium text-slate-700">R$ {{ number_format($indBet['cost'], 2, ',', '.') }}</span>
                                                        </div>

                                                        <div class="text-right">
                                                            <span class="text-slate-400">Prêmio:</span>
                                                            <span class="font-bold text-emerald-600">R$ {{ number_format($indBet['prize'], 2, ',', '.') }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
