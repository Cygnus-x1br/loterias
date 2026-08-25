<?php

namespace App\Services;

use App\Models\HistoricalResult;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BetScoringService
{
    private LotofacilStatisticsService $statisticsService;

    public function __construct(LotofacilStatisticsService $statisticsService)
    {
        $this->statisticsService = $statisticsService;
    }

    /**
     * Calcula o score de uma sequência de 15 dezenas (0 a 1000 pontos).
     */
    public function calculateScore(array $numbers): array
    {
        $numbers = array_map('intval', $numbers);
        sort($numbers);

        $details = [
            'sum' => ['points' => 0, 'value' => 0],
            'even_odd' => ['points' => 0, 'value' => ''],
            'frame_center' => ['points' => 0, 'value' => ''],
            'last_draw_repetition' => ['points' => 0, 'value' => 0],
            'never_drawn' => ['points' => 0, 'value' => false],
            'top_10_numbers' => ['points' => 0, 'value' => 0],
            'top_pairs' => ['points' => 0, 'value' => 0],
            'top_trios' => ['points' => 0, 'value' => 0],
            'top_consecutive_pairs' => ['points' => 0, 'value' => 0],
            'top_consecutive_trios' => ['points' => 0, 'value' => 0],
            'top_consecutive_quads' => ['points' => 0, 'value' => 0],
        ];

        $totalScore = 0;

        // 1. Soma das Dezenas (Max 100)
        $sum = array_sum($numbers);
        $details['sum']['value'] = $sum;
        if ($sum >= 180 && $sum <= 220) {
            $details['sum']['points'] = 100;
        } elseif (($sum >= 170 && $sum <= 179) || ($sum >= 221 && $sum <= 230)) {
            $details['sum']['points'] = 50;
        }
        $totalScore += $details['sum']['points'];

        // 2. Paridade (Max 100)
        $evens = 0;
        $odds = 0;
        foreach ($numbers as $num) {
            if ($num % 2 === 0) {
                $evens++;
            } else {
                $odds++;
            }
        }
        $details['even_odd']['value'] = "{$evens} pares / {$odds} ímpares";
        if (($evens === 7 && $odds === 8) || ($evens === 8 && $odds === 7)) {
            $details['even_odd']['points'] = 100;
        } elseif (($evens === 6 && $odds === 9) || ($evens === 9 && $odds === 6)) {
            $details['even_odd']['points'] = 50;
        }
        $totalScore += $details['even_odd']['points'];

        // 3. Moldura / Centro (Max 100)
        $frameNumbers = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];
        $frameSet = array_flip($frameNumbers);
        $frame = 0;
        $center = 0;
        foreach ($numbers as $num) {
            if (isset($frameSet[$num])) {
                $frame++;
            } else {
                $center++;
            }
        }
        $details['frame_center']['value'] = "{$frame} moldura / {$center} centro";
        if (($frame === 9 && $center === 6) || ($frame === 10 && $center === 5)) {
            $details['frame_center']['points'] = 100;
        } elseif (($frame === 8 && $center === 7) || ($frame === 11 && $center === 4)) {
            $details['frame_center']['points'] = 50;
        }
        $totalScore += $details['frame_center']['points'];

        // 4. Repetição Último Concurso (Max 100)
        $lastContestData = $this->statisticsService->getLastContestWithSum();
        if ($lastContestData && isset($lastContestData['result']['drawn_numbers'])) {
            $lastNumbers = $lastContestData['result']['drawn_numbers'];
            $intersect = count(array_intersect($numbers, $lastNumbers));
            $details['last_draw_repetition']['value'] = $intersect;
            if (in_array($intersect, [8, 9, 10])) {
                $details['last_draw_repetition']['points'] = 100;
            } elseif (in_array($intersect, [7, 11])) {
                $details['last_draw_repetition']['points'] = 50;
            }
        }
        $totalScore += $details['last_draw_repetition']['points'];

        // 5. Ineditismo (Max 50)
        $hasDrawn = false;
        if (DB::connection()->getDriverName() === 'sqlite') {
            $hasDrawn = HistoricalResult::get()->contains(function ($result) use ($numbers) {
                $drawn = is_array($result->drawn_numbers) ? $result->drawn_numbers : (json_decode((string) $result->drawn_numbers, true) ?? []);

                return count(array_intersect($drawn, $numbers)) === 15;
            });
        } else {
            $hasDrawn = HistoricalResult::whereJsonContains('drawn_numbers', $numbers)->exists();
        }

        $details['never_drawn']['value'] = ! $hasDrawn;
        if (! $hasDrawn) {
            $details['never_drawn']['points'] = 50;
        }
        $totalScore += $details['never_drawn']['points'];

        // 6. Dezenas Mais Sorteadas - Top 10 (Max 50)
        $top10Numbers = $this->statisticsService->getMostDrawnNumbers(10)->keys()->toArray();
        $top10Count = count(array_intersect($numbers, $top10Numbers));
        $details['top_10_numbers']['value'] = $top10Count;
        if ($top10Count >= 6 && $top10Count <= 8) {
            $details['top_10_numbers']['points'] = 50;
        } elseif (in_array($top10Count, [4, 5, 9, 10])) {
            $details['top_10_numbers']['points'] = 25;
        }
        $totalScore += $details['top_10_numbers']['points'];

        // 7. Pares Mais Frequentes Top 10 (Max 40)
        $top10Pairs = $this->statisticsService->getMostFrequentPairs(10)->keys()->toArray();
        $pairsCount = 0;
        $count = count($numbers);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairStr = sprintf('%02d-%02d', $numbers[$i], $numbers[$j]);
                if (in_array($pairStr, $top10Pairs)) {
                    $pairsCount++;
                }
            }
        }
        $details['top_pairs']['value'] = $pairsCount;
        if ($pairsCount >= 3) {
            $details['top_pairs']['points'] = 40;
        } elseif ($pairsCount >= 1) {
            $details['top_pairs']['points'] = 20;
        }
        $totalScore += $details['top_pairs']['points'];

        // 8. Trios Mais Frequentes Top 10 (Max 30)
        $top10Trios = $this->statisticsService->getMostFrequentTrios(10)->keys()->toArray();
        $triosCount = 0;
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                for ($k = $j + 1; $k < $count; $k++) {
                    $trioStr = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$j], $numbers[$k]);
                    if (in_array($trioStr, $top10Trios)) {
                        $triosCount++;
                    }
                }
            }
        }
        $details['top_trios']['value'] = $triosCount;
        if ($triosCount >= 2) {
            $details['top_trios']['points'] = 30;
        } elseif ($triosCount === 1) {
            $details['top_trios']['points'] = 15;
        }
        $totalScore += $details['top_trios']['points'];

        // 9. Pares, 10. Trios, 11. Quadras Consecutivos (Top) (Max 30)
        $analysisCache = Cache::get('lotofacil_advanced_analysis');
        if ($analysisCache && isset($analysisCache['consecutive_sequences'])) {
            $consecutiveTop = $analysisCache['consecutive_sequences'];
            $topPairsCons = array_keys($consecutiveTop['top_pairs_consecutive'] ?? []);
            $topTriosCons = array_keys($consecutiveTop['top_trios_consecutive'] ?? []);
            $topQuadsCons = array_keys($consecutiveTop['top_quads_consecutive'] ?? []);

            $myPairsCons = 0;
            $myTriosCons = 0;
            $myQuadsCons = 0;

            for ($i = 0; $i < $count - 1; $i++) {
                if ($numbers[$i + 1] === $numbers[$i] + 1) {
                    $pairStr = sprintf('%02d-%02d', $numbers[$i], $numbers[$i + 1]);
                    if (in_array($pairStr, $topPairsCons)) {
                        $myPairsCons++;
                    }
                    if ($i < $count - 2 && $numbers[$i + 2] === $numbers[$i] + 2) {
                        $trioStr = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2]);
                        if (in_array($trioStr, $topTriosCons)) {
                            $myTriosCons++;
                        }
                        if ($i < $count - 3 && $numbers[$i + 3] === $numbers[$i] + 3) {
                            $quadStr = sprintf('%02d-%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2], $numbers[$i + 3]);
                            if (in_array($quadStr, $topQuadsCons)) {
                                $myQuadsCons++;
                            }
                        }
                    }
                }
            }
            $details['top_consecutive_pairs']['value'] = $myPairsCons;
            if ($myPairsCons >= 3) {
                $details['top_consecutive_pairs']['points'] = 10;
            } elseif ($myPairsCons >= 1) {
                $details['top_consecutive_pairs']['points'] = 5;
            }
            $totalScore += $details['top_consecutive_pairs']['points'];

            $details['top_consecutive_trios']['value'] = $myTriosCons;
            if ($myTriosCons >= 2) {
                $details['top_consecutive_trios']['points'] = 10;
            } elseif ($myTriosCons === 1) {
                $details['top_consecutive_trios']['points'] = 5;
            }
            $totalScore += $details['top_consecutive_trios']['points'];

            $details['top_consecutive_quads']['value'] = $myQuadsCons;
            if ($myQuadsCons >= 1) {
                $details['top_consecutive_quads']['points'] = 10;
            }
            $totalScore += $details['top_consecutive_quads']['points'];
        }

        // 12. Ciclo das Dezenas (Max 150)
        $cycleData = $this->statisticsService->getDecadesCycleAnalysis();
        $missingNumbers = $cycleData['missing_numbers'] ?? [];
        $intersectCycle = count(array_intersect($numbers, $missingNumbers));
        $details['cycle'] = ['points' => 0, 'value' => $intersectCycle];

        // Se a aposta tem as dezenas que faltam (proporcionalmente), ganha pontos
        if (count($missingNumbers) > 0) {
            $ratio = $intersectCycle / count($missingNumbers);
            if ($ratio >= 0.8) {
                $details['cycle']['points'] = 150;
            } elseif ($ratio >= 0.5) {
                $details['cycle']['points'] = 75;
            }
        }
        $totalScore += $details['cycle']['points'];

        // 13. Atraso (Delay) (Max 150)
        $delayData = $this->statisticsService->getCurrentDelayAnalysis();
        $topDelayed = array_slice($delayData, 0, 8); // Top 8 mais atrasadas
        $delayedNumbers = array_column($topDelayed, 'number');
        $intersectDelay = count(array_intersect($numbers, $delayedNumbers));
        $details['delay'] = ['points' => 0, 'value' => $intersectDelay];

        if ($intersectDelay >= 5) {
            $details['delay']['points'] = 150;
        } elseif ($intersectDelay >= 3) {
            $details['delay']['points'] = 75;
        }
        $totalScore += $details['delay']['points'];

        // Contagem de Quentes, Neutras e Frias
        $temperatures = $this->statisticsService->getNumberTemperatureClassification(20);
        $hotCount = 0;
        $neutralCount = 0;
        $coldCount = 0;
        foreach ($numbers as $num) {
            $t = $temperatures[$num]['temperature'] ?? 'neutral';
            if ($t === 'hot') {
                $hotCount++;
            } elseif ($t === 'cold') {
                $coldCount++;
            } else {
                $neutralCount++;
            }
        }

        $classification = '🔴 Fora da Curva';
        $color = 'rose';
        if ($totalScore >= 800) {
            $classification = '🟢 Excelente';
            $color = 'emerald';
        } elseif ($totalScore >= 600) {
            $classification = '🟡 Boa';
            $color = 'amber';
        } elseif ($totalScore >= 400) {
            $classification = '🟠 Regular';
            $color = 'orange';
        }

        return [
            'total_score' => $totalScore,
            'max_score' => 1000,
            'classification' => $classification,
            'color' => $color,
            'sum' => $sum,
            'evens' => $evens,
            'odds' => $odds,
            'hot_count' => $hotCount,
            'neutral_count' => $neutralCount,
            'cold_count' => $coldCount,
            'details' => $details,
        ];
    }
}
