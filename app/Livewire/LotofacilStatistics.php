<?php

namespace App\Livewire;

use App\Models\HistoricalResult;
use App\Services\LotofacilStatisticsService;
use Carbon\Carbon; // Certifique-se de que o modelo está importado
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

// Certifique-se de que Carbon está importado

class LotofacilStatistics extends Component
{
    public $mostDrawnNumbers;

    public $leastDrawnNumbers;

    public $lastContest; // Será um array, não um objeto HistoricalResult diretamente

    public $numberFrequencies;

    public $mostFrequentPairs;

    public $mostFrequentTrios;

    public ?array $repeatedDrawsAnalysis = null;

    public ?array $averageScoreData = null;

    public array $numberTemperatures = [];

    public array $lastContestTemperatures = [
        'hot_count' => 0,
        'neutral_count' => 0,
        'cold_count' => 0,
    ];

    public bool $isCalculatingRepetitions = false;

    protected LotofacilStatisticsService $statisticsService;

    public function boot(LotofacilStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function mount()
    {
        $this->loadData();
    }

    public function loadData(): void
    {
        $this->mostDrawnNumbers = $this->statisticsService->getMostDrawnNumbers(10);
        $this->leastDrawnNumbers = $this->statisticsService->getLeastDrawnNumbers(10);
        $this->numberFrequencies = $this->statisticsService->getNumberFrequencies();
        $this->mostFrequentPairs = $this->statisticsService->getMostFrequentPairs(10);
        $this->mostFrequentTrios = $this->statisticsService->getMostFrequentTrios(10);
        $this->numberTemperatures = $this->statisticsService->getNumberTemperatureClassification(20);

        // Carrega a análise de repetições e a média de score histórico
        $this->repeatedDrawsAnalysis = $this->statisticsService->checkRepeatedDraws();
        $this->averageScoreData = $this->statisticsService->getHistoricalAverageScore();

        $lastContestData = $this->statisticsService->getLastContestWithSum();
        if ($lastContestData) {
            $this->lastContest = $lastContestData;

            $drawnNumbers = $this->lastContest['result']['drawn_numbers'] ?? [];
            if (is_string($drawnNumbers)) {
                $drawnNumbers = json_decode($drawnNumbers, true) ?? [];
            }

            $hot = 0;
            $neutral = 0;
            $cold = 0;

            foreach ($drawnNumbers as $num) {
                $t = $this->numberTemperatures[$num]['temperature'] ?? 'neutral';
                if ($t === 'hot') {
                    $hot++;
                } elseif ($t === 'cold') {
                    $cold++;
                } else {
                    $neutral++;
                }
            }

            $this->lastContestTemperatures = [
                'hot_count' => $hot,
                'neutral_count' => $neutral,
                'cold_count' => $cold,
            ];
        } else {
            $this->lastContest = null;
        }
    }

    /**
     * Força o recálculo das análises estatísticas (limpando o cache correspondente).
     */
    public function recalculateRepeatedDraws(): void
    {
        Cache::forget('repeated_draws_analysis');
        Cache::forget('historical_average_score');
        Cache::forget('number_temperature_classification_20');
        $this->loadData();
    }

    public function useLastResultNumbers()
    {
        if ($this->lastContest && isset($this->lastContest['result']['drawn_numbers'])) {
            $drawnNumbers = $this->lastContest['result']['drawn_numbers'];
            $this->dispatch('numbersSelected', $drawnNumbers);

            if (request()->routeIs('dashboard')) {
                return redirect()->route('closings.create', [
                    'numbers' => implode(',', $drawnNumbers),
                ]);
            }
        }
    }

    public function render()
    {
        return view('livewire.lotofacil-statistics');
    }
}
