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

    public bool $isCalculatingRepetitions = false;

    protected LotofacilStatisticsService $statisticsService;

    public function boot(LotofacilStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    public function mount()
    {
        $this->mostDrawnNumbers = $this->statisticsService->getMostDrawnNumbers(10);
        $this->leastDrawnNumbers = $this->statisticsService->getLeastDrawnNumbers(10);
        $this->numberFrequencies = $this->statisticsService->getNumberFrequencies();
        $this->mostFrequentPairs = $this->statisticsService->getMostFrequentPairs(10);
        $this->mostFrequentTrios = $this->statisticsService->getMostFrequentTrios(10);

        // Carrega a análise de repetições
        $this->repeatedDrawsAnalysis = $this->statisticsService->checkRepeatedDraws();

        $lastContestData = $this->statisticsService->getLastContestWithSum();
        if ($lastContestData) {
            $this->lastContest = $lastContestData;
        } else {
            $this->lastContest = null;
        }
    }

    /**
     * Força o recálculo da análise de repetições (limpando o cache correspondente).
     */
    public function recalculateRepeatedDraws(): void
    {
        Cache::forget('repeated_draws_analysis');
        $this->repeatedDrawsAnalysis = $this->statisticsService->checkRepeatedDraws();
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
