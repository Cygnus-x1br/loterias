<?php

namespace App\Livewire;

use App\Models\HistoricalResult;
use App\Services\BetScoringService;
use App\Services\LotofacilStatisticsService;
use App\Services\StatisticsCache;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class LotofacilAnalysis extends Component
{
    /**
     * Dezenas que formam a Moldura (Borda) e o Centro no volante de 25 números.
     */
    public const FRAME_NUMBERS = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

    public const CENTER_NUMBERS = [7, 8, 9, 12, 13, 14, 17, 18, 19];

    /**
     * Propriedades para armazenar os resultados das análises.
     */
    public ?array $consecutiveRepetitionAnalysis = null;

    public ?array $sumAnalysis = null;

    public ?array $evenOddAnalysis = null;

    public ?array $frameCenterAnalysis = null;

    public ?array $consecutiveSequencesAnalysis = null;

    public ?array $decadesCycleAnalysis = null;

    public ?array $currentDelayAnalysis = null;

    public ?array $lastContest = null;

    public ?array $lastContestScoreDetails = null;

    public int $totalContests = 0;

    /**
     * Inicializa o componente e executa/carrega o cache das análises estatísticas.
     */
    public function mount(): void
    {
        $this->loadAnalysisData();
    }

    /**
     * Carrega todos os dados dos concursos e processa as análises estatísticas com cache.
     */
    public function loadAnalysisData(bool $forceRecalculate = false): void
    {
        if ($forceRecalculate) {
            app(StatisticsCache::class)->forget('lotofacil_advanced_analysis');
        }

        $analysisData = app(StatisticsCache::class)->remember('lotofacil_advanced_analysis', now()->addHours(6), function () {
            $results = HistoricalResult::query()
                ->orderBy('contest_number', 'asc')
                ->get(['id', 'contest_number', 'draw_date', 'drawn_numbers']);

            if ($results->isEmpty()) {
                return [
                    'total_contests' => 0,
                    'last_contest' => null,
                    'consecutive_repetition' => null,
                    'sum_analysis' => null,
                    'even_odd_analysis' => null,
                    'frame_center_analysis' => null,
                    'consecutive_sequences' => null,
                ];
            }

            $normalizedResults = $results->map(function (HistoricalResult $item) {
                $numbers = is_array($item->drawn_numbers)
                    ? $item->drawn_numbers
                    : (json_decode((string) $item->drawn_numbers, true) ?? []);

                $numbers = array_map('intval', $numbers);
                sort($numbers);

                $formattedDate = null;
                if ($item->draw_date instanceof \DateTimeInterface) {
                    $formattedDate = $item->draw_date->format('d/m/Y');
                } elseif ($item->draw_date) {
                    $formattedDate = Carbon::parse($item->draw_date)->format('d/m/Y');
                }

                return [
                    'id' => $item->id,
                    'contest_number' => $item->contest_number,
                    'draw_date' => $formattedDate,
                    'drawn_numbers' => $numbers,
                ];
            });

            return [
                'total_contests' => $normalizedResults->count(),
                'last_contest' => $normalizedResults->last(),
                'consecutive_repetition' => $this->calculateConsecutiveRepetitions($normalizedResults),
                'sum_analysis' => $this->calculateSumAnalysis($normalizedResults),
                'even_odd_analysis' => $this->calculateEvenOddAnalysis($normalizedResults),
                'frame_center_analysis' => $this->calculateFrameCenterAnalysis($normalizedResults),
                'consecutive_sequences' => app(LotofacilStatisticsService::class)->getConsecutiveSequencesAnalysis(),
            ];
        });

        $this->totalContests = $analysisData['total_contests'];
        $this->lastContest = $analysisData['last_contest'];
        $this->consecutiveRepetitionAnalysis = $analysisData['consecutive_repetition'];
        $this->sumAnalysis = $analysisData['sum_analysis'];
        $this->evenOddAnalysis = $analysisData['even_odd_analysis'];
        $this->frameCenterAnalysis = $analysisData['frame_center_analysis'];
        $this->consecutiveSequencesAnalysis = $analysisData['consecutive_sequences'];

        $statisticsService = app(LotofacilStatisticsService::class);
        $this->decadesCycleAnalysis = $statisticsService->getDecadesCycleAnalysis();
        $this->currentDelayAnalysis = $statisticsService->getCurrentDelayAnalysis();

        if ($this->lastContest && ! empty($this->lastContest['drawn_numbers'])) {
            try {
                $this->lastContestScoreDetails = app(BetScoringService::class)->calculateScore($this->lastContest['drawn_numbers'], $this->lastContest['contest_number']);
            } catch (\Throwable) {
                $this->lastContestScoreDetails = null;
            }
        } else {
            $this->lastContestScoreDetails = null;
        }
    }

    /**
     * Limpa o cache e força o recálculo das análises estatísticas.
     */
    public function recalculate(): void
    {
        $this->loadAnalysisData(true);
    }

    /**
     * 1. Análise de Repetição de Dezenas entre Sorteios Consecutivos.
     */
    private function calculateConsecutiveRepetitions(Collection $results): array
    {
        $count = $results->count();
        if ($count < 2) {
            return [
                'last_draw_repetitions_count' => 0,
                'last_draw_repeated_numbers' => [],
                'historical_average' => 0.0,
                'most_common_range' => '—',
                'most_common_range_percentage' => 0.0,
                'distribution' => [],
            ];
        }

        $repetitionCounts = [];
        $totalRepeatedSum = 0;
        $comparisonsCount = $count - 1;

        for ($i = 1; $i < $count; $i++) {
            $prevNumbers = $results[$i - 1]['drawn_numbers'];
            $currNumbers = $results[$i]['drawn_numbers'];

            $intersection = array_intersect($currNumbers, $prevNumbers);
            $repeatedQty = count($intersection);

            $repetitionCounts[$repeatedQty] = ($repetitionCounts[$repeatedQty] ?? 0) + 1;
            $totalRepeatedSum += $repeatedQty;
        }

        $penultimate = $results[$count - 2]['drawn_numbers'];
        $last = $results[$count - 1]['drawn_numbers'];
        $lastRepeatedNumbers = array_values(array_intersect($last, $penultimate));
        sort($lastRepeatedNumbers);

        $inRangeCount = ($repetitionCounts[8] ?? 0) + ($repetitionCounts[9] ?? 0) + ($repetitionCounts[10] ?? 0);
        $rangePercentage = $comparisonsCount > 0 ? round(($inRangeCount / $comparisonsCount) * 100, 2) : 0;

        ksort($repetitionCounts);

        $distribution = [];
        foreach ($repetitionCounts as $qty => $frequency) {
            $distribution[] = [
                'repeated_count' => $qty,
                'frequency' => $frequency,
                'percentage' => round(($frequency / $comparisonsCount) * 100, 2),
            ];
        }

        return [
            'last_draw_repetitions_count' => count($lastRepeatedNumbers),
            'last_draw_repeated_numbers' => $lastRepeatedNumbers,
            'historical_average' => round($totalRepeatedSum / $comparisonsCount, 2),
            'most_common_range' => '8 a 10 dezenas',
            'most_common_range_percentage' => $rangePercentage,
            'distribution' => $distribution,
        ];
    }

    /**
     * 2. Análise da Soma das Dezenas Sorteadas.
     */
    private function calculateSumAnalysis(Collection $results): array
    {
        $count = $results->count();
        if ($count === 0) {
            return [
                'last_draw_sum' => 0,
                'min_sum' => 0,
                'max_sum' => 0,
                'average_sum' => 0.0,
                'most_common_range' => '175 a 215',
                'most_common_range_percentage' => 0.0,
                'ranges_distribution' => [],
            ];
        }

        $ranges = [
            '< 160' => ['min' => 0, 'max' => 159, 'count' => 0],
            '160 a 179' => ['min' => 160, 'max' => 179, 'count' => 0],
            '180 a 199' => ['min' => 180, 'max' => 199, 'count' => 0],
            '200 a 219' => ['min' => 200, 'max' => 219, 'count' => 0],
            '220 a 239' => ['min' => 220, 'max' => 239, 'count' => 0],
            '>= 240' => ['min' => 240, 'max' => 999, 'count' => 0],
        ];

        $totalSum = 0;
        $minSum = PHP_INT_MAX;
        $maxSum = 0;
        $inMainRangeCount = 0;

        foreach ($results as $result) {
            $sum = array_sum($result['drawn_numbers']);
            $totalSum += $sum;

            if ($sum < $minSum) {
                $minSum = $sum;
            }
            if ($sum > $maxSum) {
                $maxSum = $sum;
            }

            if ($sum >= 175 && $sum <= 215) {
                $inMainRangeCount++;
            }

            $this->incrementSumRange($ranges, $sum);
        }

        $lastResult = $results->last();
        $lastDrawSum = array_sum($lastResult['drawn_numbers']);

        $rangesDistribution = [];
        foreach ($ranges as $label => $data) {
            $rangesDistribution[] = [
                'label' => $label,
                'count' => $data['count'],
                'percentage' => round(($data['count'] / $count) * 100, 2),
            ];
        }

        return [
            'last_draw_sum' => $lastDrawSum,
            'min_sum' => $minSum === PHP_INT_MAX ? 0 : $minSum,
            'max_sum' => $maxSum,
            'average_sum' => round($totalSum / $count, 1),
            'most_common_range' => '175 a 215',
            'most_common_range_percentage' => round(($inMainRangeCount / $count) * 100, 2),
            'ranges_distribution' => $rangesDistribution,
        ];
    }

    private function incrementSumRange(array &$ranges, int $sum): void
    {
        foreach ($ranges as &$rangeData) {
            if ($sum >= $rangeData['min'] && $sum <= $rangeData['max']) {
                $rangeData['count']++;
                break;
            }
        }
    }

    /**
     * 3. Análise da Proporção de Dezenas Pares e Ímpares.
     */
    private function calculateEvenOddAnalysis(Collection $results): array
    {
        $count = $results->count();
        if ($count === 0) {
            return [
                'last_draw_evens' => 0,
                'last_draw_odds' => 0,
                'last_draw_pattern' => '—',
                'patterns' => [],
            ];
        }

        $patternCounts = [];

        foreach ($results as $result) {
            $evens = 0;
            $odds = 0;

            foreach ($result['drawn_numbers'] as $number) {
                if ($number % 2 === 0) {
                    $evens++;
                } else {
                    $odds++;
                }
            }

            $pattern = "{$evens} pares / {$odds} ímpares";
            $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;
        }

        $lastResult = $results->last();
        $lastEvens = 0;
        $lastOdds = 0;
        foreach ($lastResult['drawn_numbers'] as $number) {
            if ($number % 2 === 0) {
                $lastEvens++;
            } else {
                $lastOdds++;
            }
        }

        arsort($patternCounts);

        $patterns = [];
        foreach ($patternCounts as $pattern => $freq) {
            $patterns[] = [
                'pattern' => $pattern,
                'frequency' => $freq,
                'percentage' => round(($freq / $count) * 100, 2),
            ];
        }

        return [
            'last_draw_evens' => $lastEvens,
            'last_draw_odds' => $lastOdds,
            'last_draw_pattern' => "{$lastEvens} pares / {$lastOdds} ímpares",
            'patterns' => $patterns,
        ];
    }

    /**
     * 4. Análise de Dezenas na Moldura e no Centro.
     */
    private function calculateFrameCenterAnalysis(Collection $results): array
    {
        $count = $results->count();
        if ($count === 0) {
            return [
                'last_draw_frame' => 0,
                'last_draw_center' => 0,
                'last_draw_pattern' => '—',
                'patterns' => [],
            ];
        }

        $patternCounts = [];
        $frameSet = array_flip(self::FRAME_NUMBERS);

        foreach ($results as $result) {
            $frameCount = 0;
            $centerCount = 0;

            foreach ($result['drawn_numbers'] as $number) {
                if (isset($frameSet[$number])) {
                    $frameCount++;
                } else {
                    $centerCount++;
                }
            }

            $pattern = "{$frameCount} moldura / {$centerCount} centro";
            $patternCounts[$pattern] = ($patternCounts[$pattern] ?? 0) + 1;
        }

        $lastResult = $results->last();
        $lastFrame = 0;
        $lastCenter = 0;
        foreach ($lastResult['drawn_numbers'] as $number) {
            if (isset($frameSet[$number])) {
                $lastFrame++;
            } else {
                $lastCenter++;
            }
        }

        arsort($patternCounts);

        $patterns = [];
        foreach ($patternCounts as $pattern => $freq) {
            $patterns[] = [
                'pattern' => $pattern,
                'frequency' => $freq,
                'percentage' => round(($freq / $count) * 100, 2),
            ];
        }

        return [
            'last_draw_frame' => $lastFrame,
            'last_draw_center' => $lastCenter,
            'last_draw_pattern' => "{$lastFrame} moldura / {$lastCenter} centro",
            'patterns' => $patterns,
        ];
    }

    public function render()
    {
        return view('livewire.lotofacil-analysis')
            ->layout('layouts.app', [
                'title' => 'Análises Estatísticas Avançadas — Lotofácil',
            ]);
    }
}
