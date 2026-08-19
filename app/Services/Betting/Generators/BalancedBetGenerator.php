<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;
use App\Services\BetScoringService;
use App\Services\LotofacilStatisticsService;
use InvalidArgumentException;
use LogicException;

class BalancedBetGenerator implements BetGeneratorInterface
{
    /**
     * Dezenas primas da Lotofácil (1 a 25).
     *
     * @var array<int>
     */
    protected const PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23];

    /**
     * Dezenas de Fibonacci da Lotofácil (1 a 25).
     *
     * @var array<int>
     */
    protected const FIBONACCI = [1, 2, 3, 5, 8, 13, 21];

    /**
     * Valida os parâmetros específicos para a geração equilibrada.
     *
     * @throws InvalidArgumentException
     */
    public function validate(Closing $closing): void
    {
        $parameters = $closing->parameters ?? [];
        $betSize = $closing->bet_size;

        if (! isset($closing->planned_bets) || (int) $closing->planned_bets < 1) {
            throw new InvalidArgumentException(
                'A quantidade planejada de apostas é obrigatória para o método equilibrado.'
            );
        }

        // Validação de Equilíbrio Par/Ímpar
        if (isset($parameters['even_odd_balance'])) {
            $balance = $parameters['even_odd_balance'];
            if (! is_array($balance) || count($balance) !== 2 || $balance[0] < 0 || $balance[1] > $betSize || $balance[0] > $balance[1]) {
                throw new InvalidArgumentException(
                    'O equilíbrio par/ímpar deve ser um array [min_pares, max_pares] válido.'
                );
            }
        }

        // Validação de Faixa de Soma
        if (isset($parameters['sum_range'])) {
            $sumRange = $parameters['sum_range'];
            if (! is_array($sumRange) || count($sumRange) !== 2 || $sumRange[0] < 15 || $sumRange[1] > 300 || $sumRange[0] > $sumRange[1]) { // Min/Max soma teórica para 15 dezenas
                throw new InvalidArgumentException(
                    'A faixa de soma deve ser um array [soma_min, soma_max] válido.'
                );
            }
        }

        // Validação de Contagem de Primos
        if (isset($parameters['primes_count'])) {
            $primesCount = $parameters['primes_count'];
            if (! is_array($primesCount) || count($primesCount) !== 2 || $primesCount[0] < 0 || $primesCount[1] > count(self::PRIMES) || $primesCount[0] > $primesCount[1]) {
                throw new InvalidArgumentException(
                    'A contagem de primos deve ser um array [min_primos, max_primos] válido.'
                );
            }
        }

        // Validação de Contagem de Fibonacci
        if (isset($parameters['fibonacci_count'])) {
            $fibonacciCount = $parameters['fibonacci_count'];
            if (! is_array($fibonacciCount) || count($fibonacciCount) !== 2 || $fibonacciCount[0] < 0 || $fibonacciCount[1] > count(self::FIBONACCI) || $fibonacciCount[0] > $fibonacciCount[1]) {
                throw new InvalidArgumentException(
                    'A contagem de Fibonacci deve ser um array [min_fibonacci, max_fibonacci] válido.'
                );
            }
        }

        // Validação de Dezenas Repetidas do Último Sorteio
        if (isset($parameters['repeated_last_draw'])) {
            $repeated = $parameters['repeated_last_draw'];
            if (! is_array($repeated) || count($repeated) !== 2 || $repeated[0] < 0 || $repeated[1] > $betSize || $repeated[0] > $repeated[1]) {
                throw new InvalidArgumentException(
                    'A quantidade de dezenas repetidas do último concurso deve ser um array [min_repetidas, max_repetidas] válido.'
                );
            }
        }

        // Validação de Faixa de Score (0 a 1000)
        if (isset($parameters['score_range'])) {
            $scoreRange = $parameters['score_range'];
            if (! is_array($scoreRange) || count($scoreRange) !== 2 || $scoreRange[0] < 0 || $scoreRange[1] > 1000 || $scoreRange[0] > $scoreRange[1]) {
                throw new InvalidArgumentException(
                    'A faixa de score deve ser um array [min_score, max_score] válido entre 0 e 1000.'
                );
            }
        }

        // Validação de Distribuição de Temperatura (Quentes, Neutras, Frias)
        if (isset($parameters['temperature_distribution'])) {
            $temp = $parameters['temperature_distribution'];
            if (! is_array($temp)) {
                throw new InvalidArgumentException(
                    'A distribuição de temperatura deve ser um array de parâmetros válido.'
                );
            }

            foreach (['hot', 'neutral', 'cold'] as $type) {
                if (isset($temp[$type])) {
                    $range = $temp[$type];
                    if (! is_array($range) || count($range) !== 2 || $range[0] < 0 || $range[1] > $betSize || $range[0] > $range[1]) {
                        throw new InvalidArgumentException(
                            "O intervalo de dezenas {$type} deve ser um array [min, max] válido entre 0 e {$betSize}."
                        );
                    }
                }
            }
        }
    }

    /**
     * Gera apostas equilibradas com base nos parâmetros do fechamento.
     *
     * @return \Generator<int, array<int>>
     *
     * @throws LogicException
     */
    public function generate(Closing $closing): \Generator
    {
        $baseNumbers = $closing->base_numbers;
        $betSize = $closing->bet_size;
        $plannedBets = $closing->planned_bets;
        $parameters = $closing->parameters ?? [];

        if (empty($baseNumbers)) {
            throw new LogicException('O grupo-base não pode estar vazio.');
        }

        $lastDrawnNumbers = null;
        if (isset($parameters['repeated_last_draw'])) {
            if (isset($parameters['last_contest_numbers']) && is_array($parameters['last_contest_numbers'])) {
                $lastDrawnNumbers = $parameters['last_contest_numbers'];
            } else {
                $lastContest = app(LotofacilStatisticsService::class)->getLastContest();
                $lastDrawnNumbers = $lastContest ? $lastContest->drawn_numbers : null;
            }
        }

        $temperatures = null;
        if (isset($parameters['temperature_distribution'])) {
            $temperatures = app(LotofacilStatisticsService::class)->getNumberTemperatureClassification(20);
        }

        $generatedCount = 0;
        $attempts = 0;
        $maxAttemptsPerBet = 1000; // Limite de tentativas para encontrar uma aposta válida
        $uniqueBets = []; // Para garantir apostas únicas

        while ($generatedCount < $plannedBets && $attempts < ($plannedBets * $maxAttemptsPerBet)) {
            $currentBet = $this->generateRandomCombination($baseNumbers, $betSize);
            sort($currentBet); // Garante ordem para comparação de unicidade

            if (in_array($currentBet, $uniqueBets, true)) {
                $attempts++;

                continue;
            }

            if ($this->isBalanced($currentBet, $parameters, $betSize, $lastDrawnNumbers, $temperatures)) {
                yield $currentBet;
                $uniqueBets[] = $currentBet;
                $generatedCount++;
                $attempts = 0; // Reseta tentativas para a próxima aposta
            } else {
                $attempts++;
            }
        }

        if ($generatedCount < $plannedBets) {
            throw new LogicException(
                "Não foi possível gerar {$plannedBets} apostas equilibradas únicas com os parâmetros fornecidos. Foram geradas {$generatedCount}."
            );
        }
    }

    /**
     * Gera uma combinação aleatória de dezenas do grupo-base.
     *
     * @param  array<int>  $baseNumbers
     * @return array<int>
     */
    protected function generateRandomCombination(array $baseNumbers, int $betSize): array
    {
        shuffle($baseNumbers);

        return array_slice($baseNumbers, 0, $betSize);
    }

    /**
     * Verifica se uma aposta atende aos critérios de equilíbrio.
     *
     * @param  array<int>  $bet
     * @param  array<string, mixed>  $parameters
     * @param  array<int>|null  $lastDrawnNumbers
     * @param  array<int, array<string, mixed>>|null  $temperatures
     */
    protected function isBalanced(
        array $bet,
        array $parameters,
        int $betSize,
        ?array $lastDrawnNumbers = null,
        ?array $temperatures = null
    ): bool {
        // Equilíbrio Par/Ímpar
        if (isset($parameters['even_odd_balance'])) {
            $evenCount = count(array_filter($bet, fn ($n) => $n % 2 === 0));
            [$minEven, $maxEven] = $parameters['even_odd_balance'];
            if ($evenCount < $minEven || $evenCount > $maxEven) {
                return false;
            }
        }

        // Faixa de Soma
        if (isset($parameters['sum_range'])) {
            $sum = array_sum($bet);
            [$minSum, $maxSum] = $parameters['sum_range'];
            if ($sum < $minSum || $sum > $maxSum) {
                return false;
            }
        }

        // Contagem de Primos
        if (isset($parameters['primes_count'])) {
            $primesInBet = count(array_intersect($bet, self::PRIMES));
            [$minPrimes, $maxPrimes] = $parameters['primes_count'];
            if ($primesInBet < $minPrimes || $primesInBet > $maxPrimes) {
                return false;
            }
        }

        // Contagem de Fibonacci
        if (isset($parameters['fibonacci_count'])) {
            $fibonacciInBet = count(array_intersect($bet, self::FIBONACCI));
            [$minFibonacci, $maxFibonacci] = $parameters['fibonacci_count'];
            if ($fibonacciInBet < $minFibonacci || $fibonacciInBet > $maxFibonacci) {
                return false;
            }
        }

        // Repetidas do Último Sorteio
        if (isset($parameters['repeated_last_draw']) && is_array($lastDrawnNumbers)) {
            $repeatedCount = count(array_intersect($bet, $lastDrawnNumbers));
            [$minRepeated, $maxRepeated] = $parameters['repeated_last_draw'];
            if ($repeatedCount < $minRepeated || $repeatedCount > $maxRepeated) {
                return false;
            }
        }

        // Faixa de Score (0 a 1000)
        if (isset($parameters['score_range'])) {
            $scoreData = app(BetScoringService::class)->calculateScore($bet);
            $totalScore = $scoreData['total_score'] ?? 0;
            [$minScore, $maxScore] = $parameters['score_range'];
            if ($totalScore < $minScore || $totalScore > $maxScore) {
                return false;
            }
        }

        // Distribuição de Temperatura
        if (isset($parameters['temperature_distribution'])) {
            if ($temperatures === null) {
                $temperatures = app(LotofacilStatisticsService::class)->getNumberTemperatureClassification(20);
            }

            $hotCount = 0;
            $neutralCount = 0;
            $coldCount = 0;

            foreach ($bet as $number) {
                $type = $temperatures[$number]['temperature'] ?? 'neutral';
                if ($type === 'hot') {
                    $hotCount++;
                } elseif ($type === 'cold') {
                    $coldCount++;
                } else {
                    $neutralCount++;
                }
            }

            $tempRules = $parameters['temperature_distribution'];

            if (isset($tempRules['hot'])) {
                [$minHot, $maxHot] = $tempRules['hot'];
                if ($hotCount < $minHot || $hotCount > $maxHot) {
                    return false;
                }
            }

            if (isset($tempRules['neutral'])) {
                [$minNeutral, $maxNeutral] = $tempRules['neutral'];
                if ($neutralCount < $minNeutral || $neutralCount > $maxNeutral) {
                    return false;
                }
            }

            if (isset($tempRules['cold'])) {
                [$minCold, $maxCold] = $tempRules['cold'];
                if ($coldCount < $minCold || $coldCount > $maxCold) {
                    return false;
                }
            }
        }

        return true;
    }
}
