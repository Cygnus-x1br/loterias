<?php

use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Services\LotteryPrizeCalculatorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Illuminate\Support\Collection;

new #[Layout('layouts.app', ['title' => 'Simulações e Backtesting'])] class extends Component
{
    public ?int $selectedClosingId = null;
    public string $period = '100'; // Default: últimos 100
    
    // Resultados da Simulação
    public bool $hasSimulated = false;
    public bool $isSimulating = false;
    public ?array $simulationResults = null;

    public function mount(): void
    {
        // Se houver algum fechamento disponível, pré-seleciona
        $firstClosing = Closing::where('user_id', Auth::id())->whereHas('bets')->latest()->first();
        if ($firstClosing) {
            $this->selectedClosingId = $firstClosing->id;
        }
    }

    public function getAvailableClosingsProperty(): Collection
    {
        return Closing::query()
            ->where('user_id', Auth::id())
            ->whereHas('bets') // Só fechamentos que tenham apostas
            ->latest()
            ->get();
    }

    public function runBacktesting(LotteryPrizeCalculatorService $prizeCalculator): void
    {
        $this->validate([
            'selectedClosingId' => ['required', 'exists:closings,id'],
            'period' => ['required', 'string'],
        ], [
            'selectedClosingId.required' => 'Selecione um fechamento válido.',
            'period.required' => 'Selecione o período de simulação.',
        ]);

        $closing = Closing::with('bets')->find($this->selectedClosingId);
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        $query = HistoricalResult::query()->orderByDesc('contest_number');

        // Filtro de período
        if ($this->period === '10') {
            $query->take(10);
        } elseif ($this->period === '50') {
            $query->take(50);
        } elseif ($this->period === '100') {
            $query->take(100);
        } elseif ($this->period === 'year') {
            $query->whereYear('draw_date', date('Y'));
        } elseif ($this->period === 'all') {
            // Não limita
        } else {
            $query->take(100);
        }

        $contests = $query->get();

        if ($contests->isEmpty()) {
            $this->addError('period', 'Nenhum sorteio encontrado para este período.');
            return;
        }

        $bets = $closing->bets;
        $betSize = $closing->bet_size;
        
        $totalCost = 0.0;
        $totalPrizesAmount = 0.0;
        
        $hitsDistribution = [
            15 => 0, 14 => 0, 13 => 0, 12 => 0, 11 => 0, 'less' => 0
        ];

        // Calcular Custo Total = (Custo Unitário da Aposta) * (Qtd de Apostas) * (Qtd de Sorteios)
        $costPerBet = $prizeCalculator->getBetCost($betSize);
        $totalCost = $costPerBet * $bets->count() * $contests->count();

        // Extrai matriz de dezenas
        $betNumbersArray = [];
        foreach ($bets as $bet) {
            $nums = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $betNumbersArray[] = array_map('intval', $nums);
        }

        // Loop nos concursos e checa cada aposta
        foreach ($contests as $contest) {
            $drawnNumbers = is_array($contest->drawn_numbers) 
                ? $contest->drawn_numbers 
                : (json_decode((string) $contest->drawn_numbers, true) ?? []);
            $drawnNumbers = array_map('intval', $drawnNumbers);
            
            $payouts = [
                'payout_15_hits' => $contest->payout_15_hits,
                'payout_14_hits' => $contest->payout_14_hits,
            ];

            foreach ($betNumbersArray as $betNums) {
                $hits = count(array_intersect($betNums, $drawnNumbers));
                
                if ($hits >= 11) {
                    $hitsDistribution[$hits]++;
                    
                    // Soma financeiro dessa aposta usando o multiplicador matemático correto
                    $prize = $prizeCalculator->calculateTotalPrizeAmount($betSize, $hits, $payouts);
                    $totalPrizesAmount += $prize;
                } else {
                    $hitsDistribution['less']++;
                }
            }
        }

        $this->simulationResults = [
            'type' => 'backtesting',
            'contests_analyzed' => $contests->count(),
            'total_bets_played' => $bets->count() * $contests->count(),
            'total_cost' => $totalCost,
            'total_prizes' => $totalPrizesAmount,
            'profit' => $totalPrizesAmount - $totalCost,
            'roi_percentage' => $totalCost > 0 ? (($totalPrizesAmount - $totalCost) / $totalCost) * 100 : 0,
            'distribution' => $hitsDistribution,
            'closing_name' => $closing->name,
            'bet_size' => $betSize,
            'period_label' => $this->getPeriodLabel($this->period),
        ];

        $this->hasSimulated = true;
    }

    public function runMonteCarlo(\App\Services\MonteCarloSimulationService $mcService): void
    {
        $this->validate([
            'selectedClosingId' => ['required', 'exists:closings,id'],
            'period' => ['required', 'string'],
        ], [
            'selectedClosingId.required' => 'Selecione um fechamento válido.',
            'period.required' => 'Selecione a quantidade de simulações.',
        ]);

        $closing = Closing::with('bets')->find($this->selectedClosingId);
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }
        
        $numberOfSimulations = (int) $this->period;
        if (!in_array($numberOfSimulations, [1000, 5000, 10000, 50000])) {
            $numberOfSimulations = 1000;
        }

        $mcResults = $mcService->runSimulation($closing, $numberOfSimulations);
        
        $mcResults['type'] = 'montecarlo';
        $mcResults['period_label'] = number_format($numberOfSimulations, 0, ',', '.') . ' sorteios fictícios';
        $mcResults['contests_analyzed'] = $numberOfSimulations;
        
        $this->simulationResults = $mcResults;
        $this->hasSimulated = true;
    }

    private function getPeriodLabel(string $period): string
    {
        return match($period) {
            '10' => 'Últimos 10 Sorteios',
            '50' => 'Últimos 50 Sorteios',
            '100' => 'Últimos 100 Sorteios',
            'year' => 'Sorteios Deste Ano',
            'all' => 'Todo o Histórico da Lotofácil',
            default => 'Período Indefinido',
        };
    }
};
?>

<div x-data="{ activeTab: 'backtesting' }" class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ route('dashboard') }}" class="transition hover:text-indigo-600">Dashboard</a>
                <span>/</span>
                <span class="font-medium text-slate-700">Simulações</span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-sky-100 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700">
                <span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>
                Laboratório
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900" x-text="activeTab === 'backtesting' ? 'Backtesting de Fechamentos' : 'Simulador de Monte Carlo'">
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base" x-show="activeTab === 'backtesting'">
                Simule como os seus fechamentos teriam se saído financeiramente se tivessem sido apostados nos sorteios passados reais da Lotofácil.
            </p>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base" x-show="activeTab === 'montecarlo'" style="display: none;">
                Teste seus fechamentos contra milhares de sorteios fictícios matematicamente gerados para prever a taxa de lucro a longo prazo.
            </p>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {{-- Formulário de Simulação (Sidebar) --}}
        <aside class="lg:col-span-1 space-y-5">
            {{-- Tabs Control --}}
            <div class="flex rounded-xl bg-slate-100 p-1">
                <button 
                    @click="activeTab = 'backtesting'; $wire.hasSimulated = false"
                    :class="activeTab === 'backtesting' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-all"
                >
                    Backtesting
                </button>
                <button 
                    @click="activeTab = 'montecarlo'; $wire.hasSimulated = false"
                    :class="activeTab === 'montecarlo' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-bold transition-all"
                >
                    Monte Carlo
                </button>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-bold text-slate-900 mb-4 flex items-center gap-2">
                    <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
                    </svg>
                    Configurar Teste
                </h3>

                {{-- FORM BACKTESTING --}}
                <form wire:submit="runBacktesting" class="space-y-4" x-show="activeTab === 'backtesting'">
                    <div>
                        <label for="selectedClosingId" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Seu Fechamento
                        </label>
                        <select
                            id="selectedClosingId"
                            wire:model="selectedClosingId"
                            class="block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50"
                        >
                            <option value="">Selecione...</option>
                            @foreach ($this->availableClosings as $closing)
                                <option value="{{ $closing->id }}">
                                    {{ $closing->name }} ({{ $closing->bets()->count() }} jogos)
                                </option>
                            @endforeach
                        </select>
                        @error('selectedClosingId')
                            <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="period" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Período de Sorteios
                        </label>
                        <select
                            id="period"
                            wire:model="period"
                            class="block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50"
                        >
                            <option value="10">Últimos 10 Concursos</option>
                            <option value="50">Últimos 50 Concursos</option>
                            <option value="100">Últimos 100 Concursos</option>
                            <option value="year">Concursos Deste Ano</option>
                            <option value="all">Todo o Histórico (Lento)</option>
                        </select>
                        @error('period')
                            <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading.remove wire:target="runBacktesting" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span wire:loading.remove wire:target="runBacktesting">Rodar Backtesting</span>
                        <span wire:loading wire:target="runBacktesting">Processando...</span>
                    </button>
                </form>

                {{-- FORM MONTE CARLO --}}
                <form wire:submit="runMonteCarlo" class="space-y-4" x-show="activeTab === 'montecarlo'" style="display: none;">
                    <div>
                        <label for="mcClosingId" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Seu Fechamento
                        </label>
                        <select
                            id="mcClosingId"
                            wire:model="selectedClosingId"
                            class="block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50"
                        >
                            <option value="">Selecione...</option>
                            @foreach ($this->availableClosings as $closing)
                                <option value="{{ $closing->id }}">
                                    {{ $closing->name }} ({{ $closing->bets()->count() }} jogos)
                                </option>
                            @endforeach
                        </select>
                        @error('selectedClosingId')
                            <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="mcPeriod" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Quantidade de Sorteios
                        </label>
                        <select
                            id="mcPeriod"
                            wire:model="period"
                            class="block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 bg-slate-50"
                        >
                            <option value="1000">1.000 (Rápido)</option>
                            <option value="5000">5.000 (Moderado)</option>
                            <option value="10000">10.000 (Longo)</option>
                            <option value="50000">50.000 (Muito Lento)</option>
                        </select>
                        @error('period')
                            <p class="mt-1.5 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full mt-2 inline-flex items-center justify-center gap-2 rounded-xl bg-purple-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-purple-600/20 transition hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading.remove wire:target="runMonteCarlo" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span wire:loading.remove wire:target="runMonteCarlo">Rodar Monte Carlo</span>
                        <span wire:loading wire:target="runMonteCarlo">Gerando Cenários...</span>
                    </button>
                </form>
            </div>
            
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4">
                <h4 class="text-xs font-bold text-sky-900 uppercase tracking-wider mb-2">Como Funciona?</h4>
                <p class="text-xs text-sky-800 leading-relaxed" x-show="activeTab === 'backtesting'">
                    O sistema irá pegar as apostas do seu fechamento, simular que você as apostou e registrá-las contra os resultados reais passados. Ele calculará o valor gasto e os prêmios recebidos para dizer se essa estratégia seria lucrativa.
                </p>
                <p class="text-xs text-sky-800 leading-relaxed" x-show="activeTab === 'montecarlo'" style="display: none;">
                    O método de Monte Carlo gera cenários completamente fictícios e aleatórios. Ele testa seu fechamento contra milhares de "futuros possíveis" da Lotofácil, indicando a probabilidade matemática pura da sua estratégia dar certo.
                </p>
            </div>
        </aside>

        {{-- Área de Resultados --}}
        <main class="lg:col-span-3">
            @if ($hasSimulated && $simulationResults)
                <div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    
                    {{-- Cards Principais (ROI) --}}
                    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                        <div class="rounded-3xl border border-slate-200 bg-white p-6 lg:p-8 shadow-sm flex flex-col justify-center">
                            <span class="text-sm font-bold uppercase tracking-wider text-slate-500">
                                Custo Estimado
                            </span>
                            <p class="mt-3 text-3xl font-black text-rose-600 tracking-tight">
                                R$ {{ number_format($simulationResults['total_cost'], 2, ',', '.') }}
                            </p>
                            <p class="mt-3 text-sm text-slate-400 font-medium">
                                {{ number_format($simulationResults['total_bets_played'], 0, ',', '.') }} volantes apostados
                            </p>
                        </div>

                        <div class="rounded-3xl border border-slate-200 bg-white p-6 lg:p-8 shadow-sm flex flex-col justify-center">
                            <span class="text-sm font-bold uppercase tracking-wider text-slate-500">
                                Prêmios Ganhos
                            </span>
                            <p class="mt-3 text-3xl font-black text-emerald-600 tracking-tight">
                                R$ {{ number_format($simulationResults['total_prizes'], 2, ',', '.') }}
                            </p>
                            <p class="mt-3 text-sm text-slate-400 font-medium">
                                Retorno financeiro bruto
                            </p>
                        </div>

                        <div @class([
                            'rounded-3xl border p-6 lg:p-8 shadow-sm relative overflow-hidden flex flex-col justify-center min-h-[12rem]',
                            'border-emerald-200 bg-emerald-50' => $simulationResults['profit'] >= 0,
                            'border-rose-200 bg-rose-50' => $simulationResults['profit'] < 0,
                        ])>
                            {{-- Ícone decorativo bg --}}
                            @if ($simulationResults['profit'] >= 0)
                                <svg class="absolute -right-6 -bottom-6 h-32 w-32 text-emerald-500/10" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.586 14.586 7H12z" clip-rule="evenodd" /></svg>
                            @else
                                <svg class="absolute -right-6 -bottom-6 h-32 w-32 text-rose-500/10" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12 13a1 1 0 100 2h5a1 1 0 001-1V9a1 1 0 10-2 0v2.586l-4.293-4.293a1 1 0 00-1.414 0L8 9.586 3.707 5.293a1 1 0 00-1.414 1.414l5 5a1 1 0 001.414 0L11 9.414 14.586 13H12z" clip-rule="evenodd" /></svg>
                            @endif

                            <span @class([
                                'text-sm font-bold uppercase tracking-wider relative z-10',
                                'text-emerald-700' => $simulationResults['profit'] >= 0,
                                'text-rose-700' => $simulationResults['profit'] < 0,
                            ])>
                                {{ $simulationResults['profit'] >= 0 ? 'Lucro Líquido' : 'Prejuízo' }}
                            </span>
                            <p @class([
                                'mt-3 text-4xl lg:text-5xl font-black relative z-10 tracking-tighter',
                                'text-emerald-600' => $simulationResults['profit'] >= 0,
                                'text-rose-600' => $simulationResults['profit'] < 0,
                            ])>
                                R$ {{ number_format(abs($simulationResults['profit']), 2, ',', '.') }}
                            </p>
                            <div class="mt-3 flex items-center gap-2 relative z-10">
                                <span @class([
                                    'inline-flex items-center rounded-full px-3 py-1.5 text-sm font-bold shadow-sm',
                                    'bg-emerald-200/50 text-emerald-800' => $simulationResults['profit'] >= 0,
                                    'bg-rose-200/50 text-rose-800' => $simulationResults['profit'] < 0,
                                ])>
                                    ROI: {{ $simulationResults['profit'] >= 0 ? '+' : '' }}{{ number_format($simulationResults['roi_percentage'], 1, ',', '.') }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Distribuição de Prêmios (Matriz) --}}
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-slate-100 bg-slate-50 px-6 py-5">
                            <h2 class="text-base font-bold text-slate-800">
                                Desempenho e Distribuição de Prêmios
                            </h2>
                            <p class="text-sm text-slate-500 mt-1">
                                Sorteios analisados: <strong>{{ $simulationResults['period_label'] }}</strong> ({{ $simulationResults['contests_analyzed'] }}). Jogos de <strong>{{ $simulationResults['bet_size'] }} dezenas</strong>.
                            </p>
                        </div>
                        <div class="p-6">
                            @php
                                $maxDist = max(array_merge($simulationResults['distribution'], [1]));
                            @endphp
                            
                            <div class="flex flex-col gap-5">
                                {{-- Linha 15 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-indigo-700">15 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-indigo-600 transition-all" style="width: {{ ($simulationResults['distribution'][15] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-black {{ $simulationResults['distribution'][15] > 0 ? 'text-indigo-600' : 'text-slate-400' }}">
                                        {{ $simulationResults['distribution'][15] }}
                                    </div>
                                </div>
                                
                                {{-- Linha 14 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-indigo-500">14 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-indigo-400 transition-all" style="width: {{ ($simulationResults['distribution'][14] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-black {{ $simulationResults['distribution'][14] > 0 ? 'text-indigo-500' : 'text-slate-400' }}">
                                        {{ $simulationResults['distribution'][14] }}
                                    </div>
                                </div>
                                
                                {{-- Linha 13 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-emerald-600">13 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ ($simulationResults['distribution'][13] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-black {{ $simulationResults['distribution'][13] > 0 ? 'text-emerald-600' : 'text-slate-400' }}">
                                        {{ $simulationResults['distribution'][13] }}
                                    </div>
                                </div>
                                
                                {{-- Linha 12 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-emerald-500">12 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-emerald-400 transition-all" style="width: {{ ($simulationResults['distribution'][12] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-black {{ $simulationResults['distribution'][12] > 0 ? 'text-emerald-500' : 'text-slate-400' }}">
                                        {{ $simulationResults['distribution'][12] }}
                                    </div>
                                </div>
                                
                                {{-- Linha 11 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-emerald-400">11 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-emerald-300 transition-all" style="width: {{ ($simulationResults['distribution'][11] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-black {{ $simulationResults['distribution'][11] > 0 ? 'text-emerald-400' : 'text-slate-400' }}">
                                        {{ $simulationResults['distribution'][11] }}
                                    </div>
                                </div>
                                
                                {{-- Linha <= 10 pontos --}}
                                <div class="flex items-center gap-4">
                                    <div class="w-16 shrink-0 text-right text-sm font-bold text-slate-400">&le; 10 Pts</div>
                                    <div class="h-6 flex-1 rounded-full bg-slate-100 overflow-hidden relative shadow-inner">
                                        <div class="h-full rounded-full bg-slate-300 transition-all" style="width: {{ ($simulationResults['distribution']['less'] / $maxDist) * 100 }}%"></div>
                                    </div>
                                    <div class="w-16 shrink-0 text-left text-lg font-bold text-slate-400">
                                        {{ $simulationResults['distribution']['less'] }}
                                    </div>
                                </div>
                            </div>

                            @if ($simulationResults['bet_size'] > 15)
                                <div class="mt-5 rounded-lg border border-sky-100 bg-sky-50/50 p-3 text-xs text-sky-800">
                                    <strong class="font-bold">Aviso sobre Prêmios Múltiplos:</strong> Como o seu fechamento foi gerado utilizando apostas com <strong>{{ $simulationResults['bet_size'] }} dezenas</strong>, os acertos mostrados acima (ex: 14 Pts) renderam não só o prêmio de 14, mas também múltiplos prêmios menores (13, 12 e 11) simultaneamente no mesmo bilhete, conforme as regras da Caixa. Esses valores já foram todos calculados e somados no "Prêmios Ganhos".
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                {{-- Empty State --}}
                <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50/50 py-24 text-center px-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-sm border border-slate-200 text-slate-400 mb-4">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Nenhuma simulação executada</h3>
                    <p class="mt-1 max-w-sm text-sm text-slate-500">
                        Selecione seu fechamento ao lado e clique em <strong>Rodar Simulação</strong> para ver se a sua estratégia traria lucro no passado.
                    </p>
                </div>
            @endif
        </main>
    </div>
</div>
