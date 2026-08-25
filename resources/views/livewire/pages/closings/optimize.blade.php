<?php

use Livewire\Volt\Component;
use App\Models\Closing;
use App\Models\Bet;
use App\Services\Betting\OptimizerService;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app', ['title' => 'Otimizar Fechamento'])] class extends Component
{
    public Closing $closing;

    public int $original_bets_count;
    public int $target_bets;
    
    // Filtros
    public bool $force_diversity = true;
    public ?int $min_score = null;
    public ?int $max_score = null;
    public ?int $min_even = null;
    public ?int $max_even = null;
    public ?int $min_sum = null;
    public ?int $max_sum = null;
    public ?int $min_primes = null;
    public ?int $max_primes = null;
    public ?int $min_fibonacci = null;
    public ?int $max_fibonacci = null;
    public ?int $min_repeated_last_draw = null;
    public ?int $max_repeated_last_draw = null;

    // Resultados da simulação
    public array $simulatedBets = [];
    public bool $hasSimulated = false;

    public function mount(Closing $closing): void
    {
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        $this->closing = $closing;
        $this->original_bets_count = $closing->bets()->count();
        $this->target_bets = $this->original_bets_count > 10 ? (int) floor($this->original_bets_count * 0.8) : $this->original_bets_count;
    }

    public function simulate(): void
    {
        $bets = $this->closing->bets()->pluck('numbers')->toArray();
        
        $params = [
            'target_bets' => $this->target_bets,
            'force_diversity' => $this->force_diversity,
            'min_score' => $this->min_score,
            'max_score' => $this->max_score,
            'min_even' => $this->min_even,
            'max_even' => $this->max_even,
            'min_sum' => $this->min_sum,
            'max_sum' => $this->max_sum,
            'min_primes' => $this->min_primes,
            'max_primes' => $this->max_primes,
            'min_fibonacci' => $this->min_fibonacci,
            'max_fibonacci' => $this->max_fibonacci,
            'min_repeated_last_draw' => $this->min_repeated_last_draw,
            'max_repeated_last_draw' => $this->max_repeated_last_draw,
            'last_drawn_numbers' => app(\App\Services\LotofacilStatisticsService::class)->getLastContestWithSum()['result']['drawn_numbers'] ?? []
        ];

        $service = app(OptimizerService::class);
        $this->simulatedBets = $service->optimize($bets, $params);
        $this->hasSimulated = true;
    }

    public function saveOptimizedClosing(): void
    {
        if (!$this->hasSimulated || empty($this->simulatedBets)) {
            $this->addError('general', 'Você precisa simular uma otimização com resultados válidos antes de salvar.');
            return;
        }

        $newClosing = $this->closing->replicate();
        $newClosing->name = $this->closing->name . ' - Otimizado';
        $newClosing->planned_bets = count($this->simulatedBets);
        $newClosing->save();

        $betsToInsert = [];
        $now = now();
        foreach ($this->simulatedBets as $bet) {
            $betsToInsert[] = [
                'user_id' => $newClosing->user_id,
                'closing_id' => $newClosing->id,
                'name' => 'Aposta Otimizada',
                'numbers' => json_encode($bet['numbers']),
                'source' => 'optimizer',
                'method' => $newClosing->method,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Bet::insert($betsToInsert);

        $this->redirectRoute('closings.show', ['closing' => $newClosing->id], navigate: true);
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-extrabold text-slate-800">
            Otimizar Fechamento
        </h1>
        <a href="{{ route('closings.show', $closing) }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800" wire:navigate>
            &larr; Voltar
        </a>
    </div>

    <!-- Alert -->
    <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
        <div class="flex">
            <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-rose-400" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
            </div>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-rose-800">Atenção sobre Garantias Matemáticas</h3>
                <div class="mt-2 text-sm text-rose-700">
                    <p>Ao descartar apostas originais, você perde qualquer garantia 100% (ex: 14 pontos) que o fechamento possuía. O sistema irá manter apenas os jogos mais fortes estatisticamente.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <!-- Parâmetros de Otimização -->
        <div class="lg:col-span-1 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-bold text-slate-800 mb-4">Parâmetros</h2>
                
                <form wire:submit.prevent="simulate" class="space-y-4">
                    <div>
                        <label for="target_bets" class="block text-sm font-semibold text-slate-700">
                            Cortar para quantas apostas?
                        </label>
                        <div class="mt-2 flex items-center gap-4">
                            <input
                                id="target_bets"
                                type="range"
                                wire:model="target_bets"
                                min="1"
                                max="{{ $original_bets_count }}"
                                step="1"
                                class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                            >
                            <input type="number" wire:model="target_bets" class="w-20 rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-center font-bold text-indigo-700" min="1" max="{{ $original_bets_count }}">
                        </div>
                        <div class="flex justify-between text-xs text-slate-400 mt-1 px-1">
                            <span>1</span>
                            <span>{{ $original_bets_count }} orig.</span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <div class="relative">
                                <input type="checkbox" wire:model="force_diversity" class="sr-only">
                                <div class="block h-6 w-10 rounded-full bg-slate-200 shadow-inner"></div>
                                <div class="dot absolute left-1 top-1 h-4 w-4 rounded-full bg-white transition {{ $force_diversity ? 'translate-x-4 bg-indigo-600' : '' }}"></div>
                            </div>
                            <div>
                                <span class="block text-sm font-semibold text-slate-700">Forçar Diversidade Estatística</span>
                                <span class="block text-xs text-slate-500">Impede que as apostas resultantes sejam excessivamente parecidas.</span>
                            </div>
                        </label>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Score de Aposta (0-1000)</label>
                        <div class="flex gap-2">
                            <input type="number" wire:model="min_score" placeholder="Mínimo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <input type="number" wire:model="max_score" placeholder="Máximo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Dezenas Pares (0-15)</label>
                        <div class="flex gap-2">
                            <input type="number" wire:model="min_even" placeholder="Mínimo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <input type="number" wire:model="max_even" placeholder="Máximo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-100">
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Soma Total (120-255)</label>
                        <div class="flex gap-2">
                            <input type="number" wire:model="min_sum" placeholder="Mínimo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <input type="number" wire:model="max_sum" placeholder="Máximo" class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div class="pt-6">
                        <button type="submit" class="w-full inline-flex justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <svg wire:loading wire:target="simulate" class="-ml-1 mr-2 h-5 w-5 animate-spin text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            <span>Simular Otimização</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Resultados -->
        <div class="lg:col-span-2">
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm h-full flex flex-col">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-800">Resultado da Simulação</h2>
                    @if($hasSimulated && count($simulatedBets) > 0)
                        <button wire:click="saveOptimizedClosing" class="inline-flex justify-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                            Salvar Novo Fechamento
                        </button>
                    @endif
                </div>

                <div class="p-6 flex-1 bg-slate-50">
                    @if(!$hasSimulated)
                        <div class="flex h-full flex-col items-center justify-center text-center">
                            <svg class="h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-semibold text-slate-900">Nenhuma simulação rodada</h3>
                            <p class="mt-1 text-sm text-slate-500">Ajuste os parâmetros ao lado e clique em Simular.</p>
                        </div>
                    @else
                        @if(count($simulatedBets) === 0)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-center">
                                <h3 class="text-sm font-medium text-amber-800">Filtros muito restritivos</h3>
                                <p class="mt-1 text-sm text-amber-700">Nenhuma aposta atendeu a todas as regras estipuladas. Tente relaxar os limites de Score, Soma ou Pares.</p>
                            </div>
                        @else
                            @error('general')
                                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 mb-4 text-center">
                                    <p class="text-sm text-rose-700">{{ $message }}</p>
                                </div>
                            @enderror
                            <div class="mb-6 grid grid-cols-2 gap-4">
                                <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-sm text-center">
                                    <div class="text-sm font-medium text-slate-500">Apostas Sobreviventes</div>
                                    <div class="mt-1 text-2xl font-bold text-indigo-600">{{ count($simulatedBets) }} <span class="text-sm font-medium text-slate-400">/ {{ $original_bets_count }}</span></div>
                                </div>
                                <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-sm text-center">
                                    <div class="text-sm font-medium text-slate-500">Score Médio</div>
                                    @php
                                        $avgScore = collect($simulatedBets)->avg('score');
                                    @endphp
                                    <div class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($avgScore, 0) }}</div>
                                </div>
                            </div>

                            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                                <ul role="list" class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                                    @foreach(array_slice($simulatedBets, 0, 50) as $index => $bet)
                                        <li class="p-4 hover:bg-slate-50 flex items-center justify-between">
                                            <div>
                                                <span class="text-xs font-semibold text-slate-400 mr-2">#{{ $index + 1 }}</span>
                                                <div class="inline-flex gap-1">
                                                    @foreach($bet['numbers'] as $num)
                                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-700">
                                                            {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-semibold text-slate-500">Score</span>
                                                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 ring-1 ring-inset ring-indigo-700/10">
                                                    {{ $bet['score'] }}
                                                </span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                                @if(count($simulatedBets) > 50)
                                    <div class="p-3 text-center text-xs font-medium text-slate-500 border-t border-slate-100 bg-slate-50">
                                        Exibindo apenas as 50 primeiras apostas.
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
