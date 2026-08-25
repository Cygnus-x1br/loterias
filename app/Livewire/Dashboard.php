<?php

namespace App\Livewire;

use App\Models\Bet;
use App\Models\Closing;
use App\Services\FinancialAnalysisService;
use Livewire\Component;

class Dashboard extends Component
{
    public array $metrics = [];

    public array $activities = [];

    public array $distribution = [];

    public array $financialSummary = [];

    public function mount(): void
    {
        $this->loadDashboardData();
    }

    public function loadDashboardData(): void
    {
        $userId = (int) auth()->id();

        $financialService = app(FinancialAnalysisService::class);
        $this->financialSummary = $financialService->getOverallSummary($userId);

        $closingsQuery = Closing::query()
            ->where('user_id', $userId);

        $betsQuery = Bet::query()
            ->where('user_id', $userId);

        $totalClosings = (clone $closingsQuery)->count();

        $totalBets = (clone $betsQuery)->count();

        $totalBudget = (clone $closingsQuery)->sum('budget');

        $processingClosings = (clone $closingsQuery)
            ->whereIn('status', [
                'pending',
                'processing',
                'em processamento',
                'Pendente',
                'Processando',
            ])
            ->count();

        $lastClosing = (clone $closingsQuery)
            ->latest()
            ->first();

        $this->metrics = [
            [
                'label' => 'Fechamentos criados',
                'value' => number_format($totalClosings, 0, ',', '.'),
                'description' => $totalClosings === 0
                    ? 'nenhum fechamento criado'
                    : 'total no seu histórico',
                'icon' => 'layers',
                'color' => 'indigo',
                'trend' => $totalClosings === 0
                    ? 'comece agora'
                    : 'dados reais',
            ],
            [
                'label' => 'Apostas geradas',
                'value' => number_format($totalBets, 0, ',', '.'),
                'description' => $totalBets === 0
                    ? 'nenhuma aposta gerada'
                    : 'apostas vinculadas à sua conta',
                'icon' => 'ticket',
                'color' => 'emerald',
                'trend' => $totalBets === 0
                    ? 'aguardando geração'
                    : 'dados reais',
            ],
            [
                'label' => 'Fechamentos em andamento',
                'value' => number_format($processingClosings, 0, ',', '.'),
                'description' => $processingClosings === 0
                    ? 'nenhum processo pendente'
                    : 'aguardando conclusão',
                'icon' => 'clock',
                'color' => 'sky',
                'trend' => $processingClosings === 0
                    ? 'tudo atualizado'
                    : 'atenção necessária',
            ],
            [
                'label' => 'Orçamento utilizado',
                'value' => 'R$ '.number_format((float) $totalBudget, 2, ',', '.'),
                'description' => $totalBudget > 0
                    ? 'soma dos fechamentos'
                    : 'nenhum orçamento registrado',
                'icon' => 'wallet',
                'color' => 'rose',
                'trend' => $totalBudget > 0
                    ? 'dados reais'
                    : 'sem movimentação',
            ],
            [
                'label' => 'Cobertura média',
                'value' => '—',
                'description' => 'módulo de cobertura ainda indisponível',
                'icon' => 'chart',
                'color' => 'amber',
                'trend' => 'em breve',
            ],
            [
                'label' => 'Última atividade',
                'value' => $lastClosing
                    ? $lastClosing->created_at?->diffForHumans() ?? 'Hoje'
                    : '—',
                'description' => $lastClosing
                    ? 'último fechamento criado'
                    : 'nenhuma atividade registrada',
                'icon' => 'grid',
                'color' => 'violet',
                'trend' => $lastClosing
                    ? 'dados reais'
                    : 'aguardando atividade',
            ],
        ];

        $this->distribution = $this->buildDistribution($closingsQuery);

        $this->activities = $this->buildActivities($userId);
    }

    private function buildDistribution($closingsQuery): array
    {
        $colors = [
            'integral' => 'bg-indigo-500',
            'reduzido' => 'bg-violet-500',
            'roda' => 'bg-emerald-500',
            'aleatorio' => 'bg-amber-500',
            'aleatório' => 'bg-amber-500',
            'equilibrado' => 'bg-sky-500',
        ];

        $labels = [
            'integral' => 'Fechamento integral',
            'reduzido' => 'Fechamento reduzido',
            'roda' => 'Sistema de roda',
            'aleatorio' => 'Geração aleatória',
            'aleatório' => 'Geração aleatória',
            'equilibrado' => 'Geração equilibrada',
        ];

        $grouped = (clone $closingsQuery)
            ->selectRaw('method, COUNT(*) as total')
            ->groupBy('method')
            ->pluck('total', 'method');

        $total = (int) $grouped->sum();

        if ($total === 0) {
            return [];
        }

        return $grouped
            ->map(function ($amount, $method) use ($total, $colors, $labels) {
                $methodKey = mb_strtolower((string) $method);

                return [
                    'label' => $labels[$methodKey] ?? ucfirst((string) $method),
                    'value' => (int) round(($amount / $total) * 100),
                    'count' => (int) $amount,
                    'color' => $colors[$methodKey] ?? 'bg-slate-500',
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();
    }

    private function buildActivities(int $userId): array
    {
        $closings = Closing::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        $bets = Bet::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(5)
            ->get();

        $activities = collect();

        foreach ($closings as $closing) {
            $status = $this->formatStatus($closing->status);

            $activities->push([
                'type' => 'Fechamento',
                'name' => $closing->name ?? 'Fechamento #'.$closing->id,
                'date' => $closing->created_at?->format('d/m/Y H:i') ?? '—',
                'status' => $status['label'],
                'statusColor' => $status['color'],
                'url' => route('closings.show', $closing),
                'timestamp' => $closing->created_at,
            ]);
        }

        foreach ($bets as $bet) {
            $activities->push([
                'type' => 'Aposta',
                'name' => 'Aposta #'.$bet->id,
                'date' => $bet->created_at?->format('d/m/Y H:i') ?? '—',
                'status' => 'Gerada',
                'statusColor' => 'emerald',
                'url' => route('bets.index'),
                'timestamp' => $bet->created_at,
            ]);
        }

        return $activities
            ->sortByDesc('timestamp')
            ->take(5)
            ->values()
            ->map(function (array $activity) {
                unset($activity['timestamp']);

                return $activity;
            })
            ->all();
    }

    private function formatStatus(?string $status): array
    {
        $normalized = mb_strtolower(trim((string) $status));

        return match ($normalized) {
            'completed',
            'completedo',
            'concluído',
            'concluido',
            'finalizado',
            'finished' => [
                'label' => 'Concluído',
                'color' => 'emerald',
            ],

            'pending',
            'pendente',
            'processing',
            'processando',
            'em processamento' => [
                'label' => 'Em andamento',
                'color' => 'amber',
            ],

            'failed',
            'falhou',
            'erro',
            'cancelado' => [
                'label' => 'Atenção',
                'color' => 'rose',
            ],

            default => [
                'label' => $status ?: 'Registrado',
                'color' => 'sky',
            ],
        };
    }

    public function render()
    {
        return view('livewire.dashboard')
            ->layout('layouts.app', [
                'title' => 'Dashboard',
            ]);
    }
}
