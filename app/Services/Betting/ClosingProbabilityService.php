<?php

namespace App\Services\Betting;

use App\Services\Betting\Math\Combinatorics;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Probabilidade exata de um conjunto de apostas premiar no próximo sorteio.
 *
 * Percorre todos os C(25, 15) = 3.268.760 sorteios possíveis (equiprováveis) e, para cada um,
 * calcula o maior número de acertos entre as apostas. O resultado é exato, não simulado,
 * e não depende da série histórica.
 */
class ClosingProbabilityService
{
    /**
     * Limite de apostas para o cálculo síncrono (≈ 0,1 s por aposta).
     */
    public const MAX_BETS = 150;

    public const TOTAL_DRAWS = 3268760;

    /**
     * @param  array<int, array<int, int|string>>  $bets
     * @return array{
     *     total_draws: int,
     *     bets_count: int,
     *     at_least: array<int, array{favorable: int, probability: float, one_in: float|null, independent_probability: float|null}>,
     *     expected_prized_bets: float,
     *     average_overlap: float|null,
     *     classification: string
     * }
     *
     * @throws InvalidArgumentException
     */
    public function calculate(array $bets): array
    {
        $normalizedBets = $this->normalize($bets);

        return Cache::rememberForever($this->cacheKey($normalizedBets), fn (): array => $this->enumerate($normalizedBets));
    }

    /**
     * Resultado já calculado para estas apostas, sem recalcular.
     *
     * @param  array<int, array<int, int|string>>  $bets
     */
    public function cached(array $bets): ?array
    {
        try {
            return Cache::get($this->cacheKey($this->normalize($bets)));
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     */
    protected function enumerate(array $bets): array
    {
        $popcount = [];
        for ($i = 0; $i < 8192; $i++) {
            $popcount[$i] = substr_count(decbin($i), '1');
        }

        $betMasks = array_map(fn (array $bet): int => $this->bitmask($bet), $bets);
        $drawsByBestHits = array_fill(0, 16, 0);
        $prizedBetsTotal = 0;

        // Percorre todos os inteiros de 25 bits com exatamente 15 bits ligados (truque de Gosper)
        $draw = (1 << 15) - 1;
        $limit = 1 << 25;

        while ($draw < $limit) {
            $bestHits = 0;

            foreach ($betMasks as $betMask) {
                $common = $draw & $betMask;
                $hits = $popcount[$common & 8191] + $popcount[$common >> 13];

                if ($hits >= 11) {
                    $prizedBetsTotal++;
                }

                if ($hits > $bestHits) {
                    $bestHits = $hits;
                }
            }

            $drawsByBestHits[$bestHits]++;

            $lowestBit = $draw & -$draw;
            $ripple = $draw + $lowestBit;
            $draw = intdiv(($ripple ^ $draw) >> 2, $lowestBit) | $ripple;
        }

        return [
            'total_draws' => self::TOTAL_DRAWS,
            'bets_count' => count($bets),
            'at_least' => $this->atLeastSummary($drawsByBestHits, $bets),
            'expected_prized_bets' => $prizedBetsTotal / self::TOTAL_DRAWS,
            'average_overlap' => $this->averageOverlap($bets),
            'classification' => 'Exato (enumeração dos 3.268.760 sorteios possíveis)',
        ];
    }

    /**
     * Probabilidade de pelo menos uma aposta atingir cada faixa (11 a 15 acertos).
     *
     * @param  array<int, int>  $drawsByBestHits  Quantidade de sorteios por maior número de acertos.
     * @param  array<int, array<int, int>>  $bets
     * @return array<int, array{favorable: int, probability: float, one_in: float|null, independent_probability: float|null}>
     */
    protected function atLeastSummary(array $drawsByBestHits, array $bets): array
    {
        $atLeast = [];
        $favorable = 0;
        $allSimpleBets = array_reduce($bets, fn (bool $carry, array $bet): bool => $carry && count($bet) === 15, true);

        for ($hits = 15; $hits >= 11; $hits--) {
            $favorable += $drawsByBestHits[$hits];

            $atLeast[$hits] = [
                'favorable' => $favorable,
                'probability' => $favorable / self::TOTAL_DRAWS,
                'one_in' => $favorable > 0 ? self::TOTAL_DRAWS / $favorable : null,
                // Referência: a mesma quantidade de apostas simples se elas não tivessem dezenas em comum
                'independent_probability' => $allSimpleBets
                    ? 1 - (1 - $this->singleBetProbability($hits)) ** count($bets)
                    : null,
            ];
        }

        ksort($atLeast);

        return $atLeast;
    }

    /**
     * Probabilidade de uma aposta simples (15 dezenas) acertar pelo menos $hits dezenas.
     */
    public function singleBetProbability(int $hits): float
    {
        $favorable = 0;

        for ($k = $hits; $k <= 15; $k++) {
            $favorable += Combinatorics::countCombinations(15, $k) * Combinatorics::countCombinations(10, 15 - $k);
        }

        return $favorable / self::TOTAL_DRAWS;
    }

    /**
     * Média de dezenas em comum entre cada par de apostas.
     *
     * @param  array<int, array<int, int>>  $bets
     */
    public function averageOverlap(array $bets): ?float
    {
        $pairs = 0;
        $common = 0;

        for ($i = 0; $i < count($bets); $i++) {
            for ($j = $i + 1; $j < count($bets); $j++) {
                $common += count(array_intersect($bets[$i], $bets[$j]));
                $pairs++;
            }
        }

        return $pairs > 0 ? round($common / $pairs, 2) : null;
    }

    /**
     * @param  array<int, array<int, int|string>>  $bets
     * @return array<int, array<int, int>>
     *
     * @throws InvalidArgumentException
     */
    protected function normalize(array $bets): array
    {
        if ($bets === []) {
            throw new InvalidArgumentException('O fechamento não possui apostas.');
        }

        if (count($bets) > self::MAX_BETS) {
            throw new InvalidArgumentException(sprintf('O cálculo exato está disponível para até %d apostas.', self::MAX_BETS));
        }

        return array_map(function (array $bet): array {
            $numbers = array_values(array_unique(array_map('intval', $bet)));
            sort($numbers);

            if (count($numbers) < 15 || count($numbers) > 20 || min($numbers) < 1 || max($numbers) > 25) {
                throw new InvalidArgumentException('Cada aposta deve ter de 15 a 20 dezenas distintas entre 1 e 25.');
            }

            return $numbers;
        }, array_values($bets));
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     */
    protected function cacheKey(array $bets): string
    {
        $keys = array_map(fn (array $bet): string => implode('-', $bet), $bets);
        sort($keys);

        return 'closing_probability_v1_'.sha1(implode('|', $keys));
    }

    /**
     * @param  array<int, int>  $numbers
     */
    protected function bitmask(array $numbers): int
    {
        $mask = 0;
        foreach ($numbers as $number) {
            $mask |= 1 << ($number - 1);
        }

        return $mask;
    }
}
