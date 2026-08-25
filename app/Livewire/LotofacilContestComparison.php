<?php

namespace App\Livewire;

use App\Services\LotofacilStatisticsService;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class LotofacilContestComparison extends Component
{
    /**
     * Quantidade de concursos selecionados para a análise comparativa (10, 25, 50, 100).
     */
    public int $limit = 25;

    /**
     * Modo de visualização ('matrix' para tabela matricial 1-25 ou 'cards' para cards).
     */
    public string $viewMode = 'matrix';

    /**
     * Altera o período de análise.
     */
    public function setLimit(int $limit): void
    {
        if (in_array($limit, [10, 25, 50, 100], true)) {
            $this->limit = $limit;
        }
    }

    /**
     * Força o recálculo limpando o cache do período atual.
     */
    public function recalculate(): void
    {
        Cache::forget("lotofacil_contests_comparison_{$this->limit}");
    }

    public function render()
    {
        $statisticsService = app(LotofacilStatisticsService::class);
        $analysisData = $statisticsService->getContestsComparisonAnalysis($this->limit);

        return view('livewire.lotofacil-contest-comparison', [
            'analysis' => $analysisData,
        ])->layout('layouts.app', [
            'title' => "Comparativo dos Últimos {$this->limit} Concursos",
        ]);
    }
}
