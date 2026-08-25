<?php

namespace App\Livewire;

use App\Models\HistoricalResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
            Cache::forget('lotofacil_advanced_analysis');
        }

        $analysisData = Cache::remember('lotofacil_advanced_analysis', now()->addHours(6), function () {
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
                'consecutive_sequences' => $this->calculateConsecutiveSequencesAnalysis($normalizedResults),
            ];
        });

        $this->totalContests = $analysisData['total_contests'];
        $this->lastContest = $analysisData['last_contest'];
        $this->consecutiveRepetitionAnalysis = $analysisData['consecutive_repetition'];
        $this->sumAnalysis = $analysisData['sum_analysis'];
        $this->evenOddAnalysis = $analysisData['even_odd_analysis'];
        $this->frameCenterAnalysis = $analysisData['frame_center_analysis'];
        $this->consecutiveSequencesAnalysis = $analysisData['consecutive_sequences'];
        
        $statisticsService = app(\App\Services\LotofacilStatisticsService::class);
        $this->decadesCycleAnalysis = $statisticsService->getDecadesCycleAnalysis();
        $this->currentDelayAnalysis = $statisticsService->getCurrentDelayAnalysis();
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
                'most_common_range' => '180 a 220',
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

            if ($sum >= 180 && $sum <= 220) {
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
            'most_common_range' => '180 a 220',
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

    /**
     * 5. Análise de Ocorrência de Sequências Consecutivas (tamanhos 2, 3 e 4+).
     */
    private function calculateConsecutiveSequencesAnalysis(Collection $results): array
    {
        $count = $results->count();
        if ($count === 0) {
            return [
                'summary' => [],
                'top_pairs_consecutive' => [],
                'top_trios_consecutive' => [],
                'top_quads_consecutive' => [],
            ];
        }

        $drawsWithSeq2 = 0;
        $drawsWithSeq3 = 0;
        $drawsWithSeq4 = 0;

        $pairSequencesCount = [];
        $trioSequencesCount = [];
        $quadSequencesCount = [];

        foreach ($results as $result) {
            $numbers = $result['drawn_numbers'];

            $seqFlags = $this->extractConsecutiveRunsFlags($numbers);
            if ($seqFlags['hasSeq2']) {
                $drawsWithSeq2++;
            }
            if ($seqFlags['hasSeq3']) {
                $drawsWithSeq3++;
            }
            if ($seqFlags['hasSeq4']) {
                $drawsWithSeq4++;
            }

            $this->tallySubSequences($numbers, $pairSequencesCount, $trioSequencesCount, $quadSequencesCount);
        }

        arsort($pairSequencesCount);
        arsort($trioSequencesCount);
        arsort($quadSequencesCount);

        return [
            'summary' => [
                'seq2_percentage' => round(($drawsWithSeq2 / $count) * 100, 1),
                'seq2_count' => $drawsWithSeq2,
                'seq3_percentage' => round(($drawsWithSeq3 / $count) * 100, 1),
                'seq3_count' => $drawsWithSeq3,
                'seq4_percentage' => round(($drawsWithSeq4 / $count) * 100, 1),
                'seq4_count' => $drawsWithSeq4,
            ],
            'top_pairs_consecutive' => array_slice($pairSequencesCount, 0, 8, true),
            'top_trios_consecutive' => array_slice($trioSequencesCount, 0, 6, true),
            'top_quads_consecutive' => array_slice($quadSequencesCount, 0, 4, true),
        ];
    }

    /**
     * Identifica a presença de sequências contínuas de 2, 3 e 4 números.
     */
    private function extractConsecutiveRunsFlags(array $numbers): array
    {
        $hasSeq2 = false;
        $hasSeq3 = false;
        $hasSeq4 = false;

        $currentRun = [$numbers[0]];
        $n = count($numbers);

        for ($i = 1; $i < $n; $i++) {
            if ($numbers[$i] === $numbers[$i - 1] + 1) {
                $currentRun[] = $numbers[$i];
            } else {
                $this->updateSeqFlags($currentRun, $hasSeq2, $hasSeq3, $hasSeq4);
                $currentRun = [$numbers[$i]];
            }
        }
        $this->updateSeqFlags($currentRun, $hasSeq2, $hasSeq3, $hasSeq4);

        return [
            'hasSeq2' => $hasSeq2,
            'hasSeq3' => $hasSeq3,
            'hasSeq4' => $hasSeq4,
        ];
    }

    private function updateSeqFlags(array $run, bool &$hasSeq2, bool &$hasSeq3, bool &$hasSeq4): void
    {
        $len = count($run);
        if ($len >= 2) {
            $hasSeq2 = true;
        }
        if ($len >= 3) {
            $hasSeq3 = true;
        }
        if ($len >= 4) {
            $hasSeq4 = true;
        }
    }

    /**
     * Contabiliza sequências específicas de 2, 3 e 4 números consecutivos.
     */
    private function tallySubSequences(
        array $numbers,
        array &$pairCounts,
        array &$trioCounts,
        array &$quadCounts
    ): void {
        $n = count($numbers);
        for ($i = 0; $i < $n - 1; $i++) {
            if ($numbers[$i + 1] === $numbers[$i] + 1) {
                $seq2 = sprintf('%02d-%02d', $numbers[$i], $numbers[$i + 1]);
                $pairCounts[$seq2] = ($pairCounts[$seq2] ?? 0) + 1;

                if ($i < $n - 2 && $numbers[$i + 2] === $numbers[$i] + 2) {
                    $seq3 = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2]);
                    $trioCounts[$seq3] = ($trioCounts[$seq3] ?? 0) + 1;

                    if ($i < $n - 3 && $numbers[$i + 3] === $numbers[$i] + 3) {
                        $seq4 = sprintf('%02d-%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2], $numbers[$i + 3]);
                        $quadCounts[$seq4] = ($quadCounts[$seq4] ?? 0) + 1;
                    }
                }
            }
        }
    }

    public function render()
    {
        return view('livewire.lotofacil-analysis')
            ->layout('layouts.app', [
                'title' => 'Análises Estatísticas Avançadas — Lotofácil',
            ]);
    }
}
