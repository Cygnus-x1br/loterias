<?php

namespace App\Services\Betting;

use App\Services\BetScoringService;

class OptimizerService
{
    private const PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23];

    private const FIBONACCIS = [1, 2, 3, 5, 8, 13, 21];

    public function __construct(
        protected BetScoringService $scoringService,
    ) {}

    /**
     * Otimiza um conjunto de apostas aplicando filtros restritivos e ordenação por Score.
     *
     * @param  array  $bets  Array de apostas originais (cada aposta é um array de inteiros).
     * @param  array  $params  Parâmetros de otimização (target_bets, min_even, max_score, etc).
     * @return array Array otimizado contendo apenas as melhores apostas que passaram no filtro.
     */
    public function optimize(array $bets, array $params = []): array
    {
        $filtered = $this->applyHardFilters($bets, $params);

        if (empty($filtered)) {
            return [];
        }

        $scoredBets = $this->applyScoring($filtered, $params);

        usort($scoredBets, fn ($a, $b) => $b['score'] <=> $a['score']);

        $scoredBets = $this->trimBets($scoredBets, $params);

        return array_map(fn ($item) => [
            'numbers' => $item['numbers'],
            'score' => $item['score'],
        ], $scoredBets);
    }

    private function applyHardFilters(array $bets, array $params): array
    {
        $filtered = [];
        $primesMap = array_flip(self::PRIMES);
        $fibonaccisMap = array_flip(self::FIBONACCIS);
        $lastDrawnMap = array_flip($params['last_drawn_numbers'] ?? []);

        foreach ($bets as $bet) {
            if (! $this->passesFilter($bet, $params, $primesMap, $fibonaccisMap, $lastDrawnMap)) {
                continue;
            }
            $filtered[] = $bet;
        }

        return $filtered;
    }

    private function passesFilter(array $bet, array $params, array $primesMap, array $fibonaccisMap, array $lastDrawnMap): bool
    {
        if (! $this->checkRange(count(array_filter($bet, fn ($n) => $n % 2 === 0)), $params, 'min_even', 'max_even')) {
            return false;
        }

        if (! $this->checkRange(array_sum($bet), $params, 'min_sum', 'max_sum')) {
            return false;
        }

        if (! $this->checkRange(count(array_filter($bet, fn ($n) => isset($primesMap[$n]))), $params, 'min_primes', 'max_primes')) {
            return false;
        }

        if (! $this->checkRange(count(array_filter($bet, fn ($n) => isset($fibonaccisMap[$n]))), $params, 'min_fibonacci', 'max_fibonacci')) {
            return false;
        }

        if (! empty($lastDrawnMap)) {
            if (! $this->checkRange(count(array_filter($bet, fn ($n) => isset($lastDrawnMap[$n]))), $params, 'min_repeated_last_draw', 'max_repeated_last_draw')) {
                return false;
            }
        }

        return true;
    }

    private function checkRange(int $value, array $params, string $minKey, string $maxKey): bool
    {
        if (isset($params[$minKey]) && $params[$minKey] !== '' && $value < (int) $params[$minKey]) {
            return false;
        }
        if (isset($params[$maxKey]) && $params[$maxKey] !== '' && $value > (int) $params[$maxKey]) {
            return false;
        }

        return true;
    }

    private function applyScoring(array $filtered, array $params): array
    {
        $scoredBets = [];

        foreach ($filtered as $bet) {
            $scoreDetails = $this->scoringService->calculateScore($bet);
            $score = $scoreDetails['total_score'];

            if (! $this->checkRange($score, $params, 'min_score', 'max_score')) {
                continue;
            }

            $scoredBets[] = [
                'numbers' => $bet,
                'score' => $score,
            ];
        }

        return $scoredBets;
    }

    private function trimBets(array $scoredBets, array $params): array
    {
        $targetBetsCount = isset($params['target_bets']) && $params['target_bets'] !== '' ? (int) $params['target_bets'] : count($scoredBets);
        $forceDiversity = isset($params['force_diversity']) ? filter_var($params['force_diversity'], FILTER_VALIDATE_BOOLEAN) : true;

        if ($targetBetsCount <= 0 || $targetBetsCount >= count($scoredBets)) {
            return $scoredBets;
        }

        if ($forceDiversity) {
            return $this->selectDiverseBets($scoredBets, $targetBetsCount);
        }

        return array_slice($scoredBets, 0, $targetBetsCount);
    }

    private function selectDiverseBets(array $scoredBets, int $targetBetsCount): array
    {
        $selectedBets = [];
        $maxIntersection = 10;
        $maxAllowedIntersection = 14;

        while (count($selectedBets) < $targetBetsCount && $maxIntersection <= $maxAllowedIntersection) {
            foreach ($scoredBets as $index => $betData) {
                if (isset($scoredBets[$index]['selected'])) {
                    continue;
                }

                if ($this->canSelectBet($betData['numbers'], $selectedBets, $maxIntersection)) {
                    $scoredBets[$index]['selected'] = true;
                    $selectedBets[] = $betData;

                    if (count($selectedBets) >= $targetBetsCount) {
                        break 2;
                    }
                }
            }
            $maxIntersection++;
        }

        if (count($selectedBets) < $targetBetsCount) {
            foreach ($scoredBets as $betData) {
                if (! isset($betData['selected'])) {
                    $selectedBets[] = $betData;
                    if (count($selectedBets) >= $targetBetsCount) {
                        break;
                    }
                }
            }
        }

        return $selectedBets;
    }

    private function canSelectBet(array $betNumbers, array $selectedBets, int $maxIntersection): bool
    {
        foreach ($selectedBets as $selectedBet) {
            $intersection = count(array_intersect($betNumbers, $selectedBet['numbers']));
            if ($intersection > $maxIntersection) {
                return false;
            }
        }

        return true;
    }
}
