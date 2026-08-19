<?php

use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Services\Betting\ClosingGenerator;
use App\Services\LotofacilStatisticsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Detalhes do fechamento'])] class extends Component
{
    public Closing $closing;

    public ?string $generationError = null;

    public ?string $generationSuccess = null;

    public array $lastResultNumbers = [];

    public ?int $lastContestNumber = null;

    public bool $compareLastResult = true;

    // Modal e dados de "Marcar como Apostado"
    public bool $showMarkAsPlacedModal = false;
    public ?int $placedContestNumber = null;
    public ?string $placedDrawDate = null;

    // Conferência e Relatório
    public ?array $checkSummary = null;
    public ?string $checkError = null;
    public ?array $checkedContestInfo = null;
    public ?array $baseNumbersCoverage = null;

    public function mount(Closing $closing): void
    {
        // Garante que apenas o proprietário pode ver o fechamento
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        $this->closing = $closing;
        $this->placedContestNumber = $closing->contest_number;
        $this->placedDrawDate = $closing->draw_date ? $closing->draw_date->format('Y-m-d') : null;

        $lastContestData = app(LotofacilStatisticsService::class)->getLastContestWithSum();
        if ($lastContestData && isset($lastContestData['result']['drawn_numbers'])) {
            $this->lastResultNumbers = $lastContestData['result']['drawn_numbers'];
            $this->lastContestNumber = $lastContestData['result']['contest_number'] ?? null;
        }

        if ($closing->status === 'placed' || $closing->status === 'checked') {
            $this->evaluateResults();
        }
        
        if (in_array($closing->status, ['completed', 'placed', 'checked'])) {
            $this->checkBaseNumbersCoverage();
        }
    }

    public function toggleCompareLastResult(): void
    {
        $this->compareLastResult = ! $this->compareLastResult;
    }

    public function openMarkAsPlacedModal(): void
    {
        $this->placedContestNumber = $this->closing->contest_number ?? ($this->lastContestNumber ? $this->lastContestNumber + 1 : null);
        $this->placedDrawDate = $this->closing->draw_date ? $this->closing->draw_date->format('Y-m-d') : now()->format('Y-m-d');
        $this->resetErrorBag();
        $this->showMarkAsPlacedModal = true;
    }

    public function closeMarkAsPlacedModal(): void
    {
        $this->showMarkAsPlacedModal = false;
    }

    public function markAsPlaced(): void
    {
        $this->validate([
            'placedContestNumber' => ['required', 'integer', 'min:1'],
            'placedDrawDate' => ['nullable', 'date'],
        ], [
            'placedContestNumber.required' => 'Informe o número do concurso.',
            'placedContestNumber.integer' => 'O número do concurso deve ser um número inteiro.',
            'placedContestNumber.min' => 'O número do concurso deve ser maior que zero.',
        ]);

        $this->closing->update([
            'status' => 'placed',
            'contest_number' => $this->placedContestNumber,
            'draw_date' => $this->placedDrawDate ?: null,
        ]);

        // Atualiza as apostas vinculadas para 'placed' e vincula ao concurso
        $this->closing->bets()->update([
            'status' => 'placed',
            'contest_number' => $this->placedContestNumber,
            'draw_date' => $this->placedDrawDate ?: null,
        ]);

        $this->closing->refresh();
        $this->showMarkAsPlacedModal = false;
        $this->generationSuccess = 'Fechamento marcado como "Apostado" para o concurso #' . $this->placedContestNumber . '!';

        // Tenta conferir se o concurso já estiver cadastrado
        $this->evaluateResults();
    }

    /**
     * Confere as apostas do fechamento contra o resultado do concurso cadastrado manualmente.
     */
    public function checkResults(): void
    {
        $this->checkError = null;
        $this->evaluateResults(true);
    }

    /**
     * Avalia os acertos com base no HistoricalResult.
     */
    private function evaluateResults(bool $isManualTrigger = false): void
    {
        $contestNumber = $this->closing->contest_number;
        if (! $contestNumber) {
            if ($isManualTrigger) {
                $this->checkError = 'Número do concurso não informado no fechamento.';
            }
            return;
        }

        $historicalResult = HistoricalResult::query()
            ->where('contest_number', $contestNumber)
            ->first();

        if (! $historicalResult) {
            if ($isManualTrigger) {
                $this->checkError = "O resultado do Concurso #{$contestNumber} ainda não foi cadastrado no sistema em 'Resultados Anteriores'.";
            }
            return;
        }

        $drawnNumbers = is_array($historicalResult->drawn_numbers)
            ? $historicalResult->drawn_numbers
            : (json_decode((string) $historicalResult->drawn_numbers, true) ?? []);

        $drawnNumbers = array_map('intval', $drawnNumbers);
        sort($drawnNumbers);

        $this->checkedContestInfo = [
            'contest_number' => $historicalResult->contest_number,
            'draw_date' => $historicalResult->draw_date ? $historicalResult->draw_date->format('d/m/Y') : null,
            'drawn_numbers' => $drawnNumbers,
            'drawn_not_in_base' => array_values(array_diff($drawnNumbers, $this->closing->base_numbers ?? [])),
        ];

        // Atualiza os hits de cada aposta vinculada
        $bets = $this->closing->bets()->get();
        $totalHitsByTier = [
            15 => 0,
            14 => 0,
            13 => 0,
            12 => 0,
            11 => 0,
            'outros' => 0,
        ];

        $totalBets = $bets->count();
        $awardedBets = 0;

        foreach ($bets as $bet) {
            $betNumbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $hits = count(array_intersect($betNumbers, $drawnNumbers));

            $bet->update([
                'hits' => $hits,
                'status' => 'checked',
            ]);

            if (isset($totalHitsByTier[$hits])) {
                $totalHitsByTier[$hits]++;
                $awardedBets++;
            } else {
                $totalHitsByTier['outros']++;
            }
        }

        $this->closing->update(['status' => 'checked']);
        $this->closing->refresh();

        $this->checkSummary = [
            'total_bets' => $totalBets,
            'awarded_bets' => $awardedBets,
            'hits_15' => $totalHitsByTier[15],
            'hits_14' => $totalHitsByTier[14],
            'hits_13' => $totalHitsByTier[13],
            'hits_12' => $totalHitsByTier[12],
            'hits_11' => $totalHitsByTier[11],
            'hits_less' => $totalHitsByTier['outros'],
        ];

        if ($isManualTrigger) {
            $this->generationSuccess = "Conferência do Concurso #{$contestNumber} concluída com sucesso!";
        }
    }

    /**
     * Retorna o nome amigável do método.
     */
    public function methodLabel(?string $method): string
    {
        return match ($method) {
            'integral' => 'Combinação integral',
            'reduced' => 'Fechamento reduzido',
            'wheel' => 'Sistema de roda',
            'random' => 'Geração aleatória',
            'balanced' => 'Geração equilibrada',
            default => ucfirst((string) $method),
        };
    }

    /**
     * Retorna o nome amigável do status.
     */
    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Rascunho',
            'processing' => 'Em processamento',
            'completed' => 'Concluído',
            'placed' => 'Apostado',
            'checked' => 'Conferido',
            'failed' => 'Falhou',
            default => ucfirst((string) $status),
        };
    }

    /**
     * Retorna as classes CSS para o status.
     */
    public function statusClasses(?string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-100 text-slate-700',
            'processing' => 'bg-sky-100 text-sky-700',
            'completed' => 'bg-indigo-100 text-indigo-700',
            'placed' => 'bg-amber-100 text-amber-800 border border-amber-200',
            'checked' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
            'failed' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Indica se o método de geração do fechamento está implementado.
     */
    public function isMethodImplemented(): bool
    {
        return in_array($this->closing->method, ClosingGenerator::implementedMethods(), true);
    }

    /**
     * Dispara a geração de apostas para o fechamento.
     */
    public function generateBets(): void
    {
        $this->generationError = null;
        $this->generationSuccess = null;

        if ($this->closing->status !== 'draft' && $this->closing->status !== 'failed') {
            $this->generationError = 'A geração só pode ser disparada para fechamentos em rascunho ou que falharam.';
            return;
        }

        if (! $this->isMethodImplemented()) {
            $this->generationError = 'Este método de fechamento ainda não possui geração implementada.';
            return;
        }

        try {
            $createdBets = app(ClosingGenerator::class)->generate($this->closing);
            $this->generationSuccess = "{$createdBets} aposta(s) gerada(s) com sucesso.";
            $this->checkBaseNumbersCoverage();
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->generationError = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->generationError = 'Ocorreu um erro inesperado ao gerar as apostas. Tente novamente.';
        } finally {
            $this->closing->refresh();
        }
    }

    public function checkBaseNumbersCoverage(): void
    {
        $baseNumbers = $this->closing->base_numbers ?? [];
        if (empty($baseNumbers)) {
            return;
        }

        $allBetNumbers = [];
        $bets = $this->closing->bets()->get();
        foreach ($bets as $bet) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $allBetNumbers = array_merge($allBetNumbers, $numbers);
        }

        $usedNumbers = array_unique($allBetNumbers);
        sort($usedNumbers);

        $unusedNumbers = array_diff($baseNumbers, $usedNumbers);
        sort($unusedNumbers);

        $this->baseNumbersCoverage = [
            'total_base' => count($baseNumbers),
            'total_used' => count($usedNumbers),
            'unused_numbers' => array_values($unusedNumbers),
            'all_used' => empty($unusedNumbers)
        ];
    }

    /**
     * Consulta paginada das apostas vinculadas ao fechamento.
     */
    public function with(): array
    {
        $bets = $this->closing->bets()->paginate(10);
        $scoringService = app(\App\Services\BetScoringService::class);
        $bets->getCollection()->transform(function ($bet) use ($scoringService) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $bet->scoreData = $scoringService->calculateScore($numbers);
            return $bet;
        });

        return [
            'bets' => $bets,
        ];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <main class="lg:col-span-2 space-y-6">
            {{-- Topo do Fechamento --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
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
                                Detalhes
                            </span>
                        </div>

                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                            {{ $closing->name }}
                        </h1>

                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span @class([
                                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                                $this->statusClasses($closing->status),
                            ])>
                                {{ $this->statusLabel($closing->status) }}
                            </span>

                            @if ($closing->contest_number)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                    Concurso #{{ $closing->contest_number }}
                                </span>
                            @endif

                            @if ($closing->draw_date)
                                <span class="text-xs text-slate-500">
                                    Data do Sorteio: {{ $closing->draw_date->format('d/m/Y') }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($closing->status === 'draft' || $closing->status === 'failed')
                            <a
                                href="{{ route('closings.edit', $closing) }}"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100"
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
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                                    />
                                </svg>

                                Editar Fechamento
                            </a>
                        @endif

                        <a
                            href="{{ route('closings.index') }}"
                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
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
                                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                                />
                            </svg>

                            Voltar
                        </a>
                    </div>
                </div>

                @if ($closing->notes)
                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="whitespace-pre-line text-sm leading-6 text-slate-600">
                            {{ $closing->notes }}
                        </p>
                    </div>
                @endif
            </section>

            {{-- Painel de Ações: Gerar, Marcar como Apostado, Conferir --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Ações do Fechamento
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Gerencie o ciclo de vida do fechamento e realize a conferência dos resultados.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2.5">
                        {{-- Botão Marcar como Apostado --}}
                        @if (in_array($closing->status, ['completed', 'placed', 'checked']))
                            <button
                                type="button"
                                wire:click="openMarkAsPlacedModal"
                                class="inline-flex items-center justify-center gap-2 rounded-xl border border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-semibold text-amber-800 shadow-sm transition hover:bg-amber-100"
                            >
                                <svg class="h-4 w-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                </svg>
                                {{ $closing->status === 'placed' || $closing->status === 'checked' ? 'Editar Concurso Apostado' : 'Marcar como Apostado' }}
                            </button>
                        @endif

                        {{-- Botão Conferir Resultado --}}
                        @if (in_array($closing->status, ['placed', 'checked']))
                            <button
                                type="button"
                                wire:click="checkResults"
                                wire:loading.attr="disabled"
                                wire:target="checkResults"
                                class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-emerald-600/20 transition hover:bg-emerald-700 disabled:opacity-50"
                            >
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span wire:loading.remove wire:target="checkResults">Conferir Resultado</span>
                                <span wire:loading wire:target="checkResults">Conferindo...</span>
                            </button>
                        @endif

                        {{-- Botão Gerar Apostas --}}
                        @if ($closing->status === 'draft' || $closing->status === 'failed')
                            @if (! $this->isMethodImplemented())
                                <span class="text-xs font-semibold text-amber-700 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                                    Método não implementado
                                </span>
                            @else
                                <button
                                    type="button"
                                    wire:click="generateBets"
                                    wire:loading.attr="disabled"
                                    wire:target="generateBets"
                                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                    </svg>
                                    <span wire:loading.remove wire:target="generateBets">Gerar apostas</span>
                                    <span wire:loading wire:target="generateBets">Gerando...</span>
                                </button>
                            @endif
                        @endif
                    </div>
                </div>

                @if ($generationError || $checkError)
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" />
                            </svg>
                            <p class="text-sm leading-6 text-rose-800">
                                {{ $generationError ?: $checkError }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($generationSuccess)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            <p class="text-sm leading-6 text-emerald-800">
                                {{ $generationSuccess }}
                            </p>
                        </div>
                    </div>
                @endif
                
                @if ($baseNumbersCoverage)
                    <div class="mt-5 rounded-xl border {{ $baseNumbersCoverage['all_used'] ? 'border-indigo-200 bg-indigo-50' : 'border-amber-200 bg-amber-50' }} p-4">
                        <div class="flex items-start gap-3">
                            @if ($baseNumbersCoverage['all_used'])
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-sm leading-6 text-indigo-800">
                                    <strong>Excelente!</strong> Todas as {{ $baseNumbersCoverage['total_base'] }} dezenas do seu grupo base foram utilizadas em pelo menos uma aposta gerada.
                                </p>
                            @else
                                <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <div>
                                    <p class="text-sm leading-6 text-amber-800">
                                        <strong>Atenção:</strong> Foram utilizadas apenas {{ $baseNumbersCoverage['total_used'] }} das {{ $baseNumbersCoverage['total_base'] }} dezenas do seu grupo base nas apostas geradas. 
                                    </p>
                                    <p class="mt-1 text-xs text-amber-700 flex items-center flex-wrap gap-1">
                                        <span>Dezenas não utilizadas:</span> 
                                        @foreach ($baseNumbersCoverage['unused_numbers'] as $uNum)
                                            <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-amber-200/50 font-bold">
                                                {{ str_pad($uNum, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </section>

            {{-- Relatório de Conferência (Exibido quando conferido) --}}
            @if ($checkSummary && $checkedContestInfo)
                <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/60 via-white to-slate-50 p-5 shadow-sm sm:p-6 space-y-5">
                    <div class="flex flex-col justify-between gap-3 border-b border-emerald-100 pb-4 sm:flex-row sm:items-center">
                        <div>
                            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                                Relatório Oficial de Conferência
                            </div>
                            <h2 class="mt-1 text-xl font-black text-slate-900">
                                Concurso #{{ $checkedContestInfo['contest_number'] }}
                                @if ($checkedContestInfo['draw_date'])
                                    <span class="text-sm font-normal text-slate-500">({{ $checkedContestInfo['draw_date'] }})</span>
                                @endif
                            </h2>
                        </div>

                        <div class="text-right">
                            <span class="text-xs font-semibold text-slate-500">Apostas Premiadas:</span>
                            <p class="text-lg font-black text-emerald-700">
                                {{ $checkSummary['awarded_bets'] }} de {{ $checkSummary['total_bets'] }}
                            </p>
                        </div>
                    </div>

                    {{-- Dezenas Sorteadas no Concurso Conferido --}}
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">
                            Dezenas Sorteadas no Concurso #{{ $checkedContestInfo['contest_number'] }}:
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($checkedContestInfo['drawn_numbers'] as $dNum)
                                @php
                                    $notInBase = in_array($dNum, $checkedContestInfo['drawn_not_in_base'] ?? []);
                                @endphp
                                <span @class([
                                    'inline-flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-white shadow-sm transition-colors',
                                    'bg-rose-500 ring-2 ring-rose-400/50' => $notInBase,
                                    'bg-emerald-600' => ! $notInBase,
                                ]) title="{{ $notInBase ? 'Dezena sorteada que não estava no grupo base' : 'Dezena do grupo base' }}">
                                    {{ str_pad($dNum, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endforeach
                        </div>
                        @if(!empty($checkedContestInfo['drawn_not_in_base']))
                            <p class="mt-2 text-xs text-rose-600 font-medium">
                                * As dezenas em vermelho foram sorteadas, mas não estavam presentes no seu grupo base.
                            </p>
                        @endif
                    </div>

                    {{-- Cards com a Distribuição de Acertos (15, 14, 13, 12, 11) --}}
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-6 pt-2">
                        <div class="rounded-xl border {{ $checkSummary['hits_15'] > 0 ? 'border-amber-300 bg-amber-50 ring-2 ring-amber-400' : 'border-slate-200 bg-white' }} p-3 text-center">
                            <span class="text-xs font-bold text-slate-600">15 Acertos</span>
                            <p class="mt-1 text-2xl font-black {{ $checkSummary['hits_15'] > 0 ? 'text-amber-700' : 'text-slate-800' }}">
                                {{ $checkSummary['hits_15'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border {{ $checkSummary['hits_14'] > 0 ? 'border-emerald-300 bg-emerald-50 ring-2 ring-emerald-400' : 'border-slate-200 bg-white' }} p-3 text-center">
                            <span class="text-xs font-bold text-slate-600">14 Acertos</span>
                            <p class="mt-1 text-2xl font-black {{ $checkSummary['hits_14'] > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                {{ $checkSummary['hits_14'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border {{ $checkSummary['hits_13'] > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-3 text-center">
                            <span class="text-xs font-bold text-slate-600">13 Acertos</span>
                            <p class="mt-1 text-2xl font-black {{ $checkSummary['hits_13'] > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                {{ $checkSummary['hits_13'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border {{ $checkSummary['hits_12'] > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-3 text-center">
                            <span class="text-xs font-bold text-slate-600">12 Acertos</span>
                            <p class="mt-1 text-2xl font-black {{ $checkSummary['hits_12'] > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                {{ $checkSummary['hits_12'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border {{ $checkSummary['hits_11'] > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-3 text-center">
                            <span class="text-xs font-bold text-slate-600">11 Acertos</span>
                            <p class="mt-1 text-2xl font-black {{ $checkSummary['hits_11'] > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                {{ $checkSummary['hits_11'] }}
                            </p>
                        </div>

                        <div class="rounded-xl border border-slate-200 bg-white p-3 text-center opacity-75">
                            <span class="text-xs font-bold text-slate-500">&le; 10 Acertos</span>
                            <p class="mt-1 text-2xl font-black text-slate-400">
                                {{ $checkSummary['hits_less'] }}
                            </p>
                        </div>
                    </div>
                </section>
            @endif

            {{-- Tabela de Apostas Vinculadas --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Apostas vinculadas
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Apostas geradas por este fechamento.
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        @if (! empty($lastResultNumbers) && ! $checkedContestInfo)
                            <button
                                type="button"
                                wire:click="toggleCompareLastResult"
                                @class([
                                    'inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs font-semibold transition shadow-sm',
                                    'bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100' => $compareLastResult,
                                    'bg-slate-100 text-slate-600 border border-slate-200 hover:bg-slate-200' => ! $compareLastResult,
                                ])
                            >
                                <span @class([
                                    'h-2 w-2 rounded-full',
                                    'bg-emerald-500 animate-pulse' => $compareLastResult,
                                    'bg-slate-400' => ! $compareLastResult,
                                ])></span>
                                Destacar último sorteio {{ $lastContestNumber ? '(#'.$lastContestNumber.')' : '' }}
                            </button>
                        @endif

                        <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                            {{ $closing->bets()->count() }} apostas
                        </span>
                    </div>
                </div>

                @if ($bets->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Aposta
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Dezenas
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Status
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Acertos
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($bets as $bet)
                                    @php
                                        $referenceNumbers = $checkedContestInfo['drawn_numbers'] ?? ($compareLastResult ? $lastResultNumbers : []);
                                        $matchedCount = count(array_intersect($bet->numbers ?? [], $referenceNumbers));
                                    @endphp
                                    <tr
                                        wire:key="closing-bet-{{ $bet->id }}"
                                        class="transition hover:bg-slate-50"
                                    >
                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            <div class="font-semibold text-slate-900">
                                                {{ $bet->name ?: 'Aposta #' . $bet->id }}
                                            </div>

                                            <div class="mt-1 text-xs text-slate-500">
                                                #{{ $bet->id }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                @foreach ($bet->numbers ?? [] as $number)
                                                    @php
                                                        $isDrawn = ! empty($referenceNumbers) && in_array($number, $referenceNumbers, true);
                                                    @endphp
                                                    <span
                                                        @class([
                                                            'inline-flex h-7 w-7 items-center justify-center rounded-lg text-xs font-bold transition-colors',
                                                            'bg-emerald-600 text-white shadow-sm ring-2 ring-emerald-600/30' => $isDrawn,
                                                            'bg-indigo-50 text-indigo-700' => ! $isDrawn,
                                                        ])
                                                    >
                                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                            
                                            @if(isset($bet->scoreData))
                                                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px]">
                                                    <span class="font-bold text-{{ $bet->scoreData['color'] }}-700 bg-{{ $bet->scoreData['color'] }}-50 px-2 py-0.5 rounded-full border border-{{ $bet->scoreData['color'] }}-200" title="Score de equilíbrio: {{ $bet->scoreData['total_score'] }} pts">
                                                        Score: {{ $bet->scoreData['total_score'] }}
                                                    </span>

                                                    {{-- Soma --}}
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700 border border-slate-200" title="Soma total das dezenas: {{ $bet->scoreData['sum'] ?? 'N/A' }} pontos">
                                                        <span class="text-slate-400 font-bold">∑</span>
                                                        <span>{{ $bet->scoreData['sum'] ?? '-' }}</span>
                                                    </span>

                                                    {{-- Pares e Ímpares --}}
                                                    <span class="inline-flex items-center gap-0.5 rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700 border border-slate-200" title="Proporção: {{ $bet->scoreData['evens'] ?? '-' }} Pares e {{ $bet->scoreData['odds'] ?? '-' }} Ímpares">
                                                        <span class="text-indigo-600">{{ $bet->scoreData['evens'] ?? '-' }}P</span>/<span class="text-emerald-600">{{ $bet->scoreData['odds'] ?? '-' }}I</span>
                                                    </span>

                                                    {{-- Quentes / Médias / Frias --}}
                                                    <span class="inline-flex items-center gap-1 rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-700 border border-slate-200" title="Distribuição de temperatura recente: {{ $bet->scoreData['hot_count'] ?? 0 }} Quentes, {{ $bet->scoreData['neutral_count'] ?? 0 }} Médias e {{ $bet->scoreData['cold_count'] ?? 0 }} Frias">
                                                        <span class="text-amber-700 flex items-center gap-0.5" title="{{ $bet->scoreData['hot_count'] ?? 0 }} Quentes">
                                                            <svg class="h-3 w-3 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                                                <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.527.82-1.124 1.93-1.64 3.12a20.08 20.08 0 01-1.393 2.748c-.5.845-.964 1.57-1.353 2.052a5.75 5.75 0 00-.737 1.258A6.002 6.002 0 0010 18a6.002 6.002 0 005.894-4.873c.07-.37.106-.75.106-1.127 0-1.197-.333-2.316-.913-3.268a15.733 15.733 0 00-1.89-2.544 19.86 19.86 0 00-.802-.835zM10 16a4 4 0 01-3.92-3.178c.036-.08.08-.16.13-.24.32-.51.72-1.17 1.18-1.95A18.09 18.09 0 008.66 8.01c.42-.98.88-1.87 1.34-2.58.3-.06.6.01.83.21.36.31.75.7 1.15 1.17.48.56.96 1.23 1.38 1.99.45.81.79 1.69.79 2.61A4.002 4.002 0 0110 16z" clip-rule="evenodd" />
                                                            </svg>
                                                            {{ $bet->scoreData['hot_count'] ?? 0 }}Q
                                                        </span>
                                                        <span class="text-slate-400">·</span>
                                                        <span class="text-slate-600 flex items-center gap-0.5" title="{{ $bet->scoreData['neutral_count'] ?? 0 }} Médias">
                                                            {{ $bet->scoreData['neutral_count'] ?? 0 }}N
                                                        </span>
                                                        <span class="text-slate-400">·</span>
                                                        <span class="text-sky-700 flex items-center gap-0.5" title="{{ $bet->scoreData['cold_count'] ?? 0 }} Frias">
                                                            <svg class="h-3 w-3 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                                <line x1="12" y1="2" x2="12" y2="22"></line>
                                                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                                <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                                                            </svg>
                                                            {{ $bet->scoreData['cold_count'] ?? 0 }}F
                                                        </span>
                                                    </span>
                                                </div>
                                            @endif
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            <span @class([
                                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                                match ($bet->status) {
                                                    'active' => 'bg-slate-100 text-slate-700',
                                                    'placed' => 'bg-amber-100 text-amber-800',
                                                    'checked' => 'bg-emerald-100 text-emerald-800',
                                                    default => 'bg-slate-100 text-slate-700',
                                                },
                                            ])>
                                                {{ match ($bet->status) {
                                                    'active' => 'Ativa',
                                                    'placed' => 'Apostada',
                                                    'checked' => 'Conferida',
                                                    default => ucfirst((string) $bet->status),
                                                } }}
                                            </span>
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            @if ($bet->hits !== null)
                                                <span @class([
                                                    'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-bold',
                                                    'bg-amber-100 text-amber-900 border border-amber-300 shadow-sm' => $bet->hits === 15,
                                                    'bg-emerald-100 text-emerald-800 border border-emerald-300' => in_array($bet->hits, [11, 12, 13, 14]),
                                                    'bg-slate-100 text-slate-600' => $bet->hits < 11,
                                                ])>
                                                    {{ $bet->hits }} acertos
                                                </span>
                                            @elseif (! empty($referenceNumbers))
                                                <span class="text-xs text-slate-500 font-medium">
                                                    {{ $matchedCount }} acertos (prévia)
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                        {{ $bets->links() }}
                    </div>
                @else
                    <div class="px-5 py-16 text-center sm:px-6">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586A2 2 0 0119 8.414V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-slate-900">
                            Nenhuma aposta vinculada
                        </h3>

                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                            Este fechamento ainda não possui apostas geradas.
                        </p>
                    </div>
                @endif
            </section>
        </main>

        {{-- Barra Lateral: Detalhes do Fechamento --}}
        <aside class="lg:col-span-1 space-y-6">
            <dl class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-4">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Status Atual
                    </dt>
                    <dd>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            $this->statusClasses($closing->status),
                        ])>
                            {{ $this->statusLabel($closing->status) }}
                        </span>
                    </dd>
                </div>

                @if ($closing->contest_number)
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                        <dt class="text-sm text-slate-500">
                            Concurso Alvo
                        </dt>
                        <dd class="text-sm font-bold text-indigo-700">
                            #{{ $closing->contest_number }}
                        </dd>
                    </div>
                @endif

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Método
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $this->methodLabel($closing->method) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Grupo-base
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($closing->base_numbers ?? [] as $number)
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-700">
                                    {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endforeach
                        </div>
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Tamanho da aposta
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->bet_size }} dezenas
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Apostas planejadas
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->planned_bets }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Garantia
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->guarantee ? $closing->guarantee . ' acertos' : 'Não informada' }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">
                        Orçamento
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->budget !== null ? 'R$ ' . number_format((float) $closing->budget, 2, ',', '.') : 'Não informado' }}
                    </dd>
                </div>
            </dl>
        </aside>
    </div>

    {{-- Modal Marcar como Apostado --}}
    @if ($showMarkAsPlacedModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl space-y-5">
                <div class="flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">
                            Marcar como Apostado
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Informe os dados do concurso em que as apostas foram efetivadas.
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="closeMarkAsPlacedModal"
                        class="text-slate-400 hover:text-slate-600 rounded-lg p-1"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-4">
                    <div>
                        <label for="placedContestNumber" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Número do Concurso <span class="text-rose-500">*</span>
                        </label>
                        <input
                            type="number"
                            id="placedContestNumber"
                            wire:model="placedContestNumber"
                            placeholder="Ex: 3120"
                            min="1"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('placedContestNumber')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="placedDrawDate" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Data do Sorteio (Opcional)
                        </label>
                        <input
                            type="date"
                            id="placedDrawDate"
                            wire:model="placedDrawDate"
                            class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        @error('placedDrawDate')
                            <p class="mt-1 text-xs text-rose-600 font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                    <button
                        type="button"
                        wire:click="closeMarkAsPlacedModal"
                        class="px-4 py-2 text-xs font-bold text-slate-600 rounded-xl hover:bg-slate-100"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="markAsPlaced"
                        class="px-4 py-2 text-xs font-bold text-white bg-amber-600 hover:bg-amber-700 rounded-xl shadow-sm"
                    >
                        Confirmar Apostado
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

