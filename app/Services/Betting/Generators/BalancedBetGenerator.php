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
        $repeatedQuotas = [];
        $repeatedPool = [];
        $nonRepeatedPool = $baseNumbers;

        if (isset($parameters['repeated_last_draw'])) {
            if (isset($parameters['last_contest_numbers']) && is_array($parameters['last_contest_numbers'])) {
                $lastDrawnNumbers = $parameters['last_contest_numbers'];
            } else {
                $lastContest = app(LotofacilStatisticsService::class)->getLastContest();
                $lastDrawnNumbers = $lastContest ? $lastContest->drawn_numbers : null;
                if (is_string($lastDrawnNumbers)) {
                    $lastDrawnNumbers = json_decode($lastDrawnNumbers, true);
                }
            }

            if ($lastDrawnNumbers) {
                $repeatedPool = array_values(array_intersect($baseNumbers, $lastDrawnNumbers));
                $nonRepeatedPool = array_values(array_diff($baseNumbers, $lastDrawnNumbers));

                $minRepParam = max($parameters['repeated_last_draw'][0], 0);
                $maxRepParam = min($parameters['repeated_last_draw'][1], count($repeatedPool));

                // Validação Matemática Prévia (Fail Fast)
                if ($minRepParam > count($repeatedPool)) {
                    throw new LogicException(
                        sprintf('Não é possível gerar apostas com no mínimo %d repetidas pois a base possui apenas %d dezenas do último sorteio.', $parameters['repeated_last_draw'][0], count($repeatedPool))
                    );
                }

                $requiredRepeated = $betSize - count($nonRepeatedPool);
                if ($parameters['repeated_last_draw'][1] < $requiredRepeated) {
                    throw new LogicException(
                        sprintf('Não é possível gerar apostas com no máximo %d repetidas pois a base exige no mínimo %d repetidas.', $parameters['repeated_last_draw'][1], max(0, $requiredRepeated))
                    );
                }

                $minRep = max($minRepParam, $requiredRepeated);
                $maxRep = $maxRepParam;

                if ($minRep <= $maxRep) {
                    $repeatedQuotas = app(LotofacilStatisticsService::class)->calculateRepetitionQuotas(
                        $minRep, $maxRep, $plannedBets
                    );
                }
            }
        }

        $temperatures = null;
        if (isset($parameters['temperature_distribution'])) {
            $temperatures = app(LotofacilStatisticsService::class)->getNumberTemperatureClassification(20);
        }

        $generatedCount = 0;
        $attempts = 0;
        $maxAttemptsPerBet = 2000;
        $uniqueBets = [];
        $rejectionStats = [];

        // Expande as cotas em um array de "alvos" de repetição
        $repetitionTargets = [];
        foreach ($repeatedQuotas as $rep => $quota) {
            for ($i = 0; $i < $quota; $i++) {
                $repetitionTargets[] = $rep;
            }
        }

        // Preenche com random se faltar alvos
        while (count($repetitionTargets) < $plannedBets) {
            $repetitionTargets[] = null;
        }

        // Embaralha os alvos para distribuir as repetições
        shuffle($repetitionTargets);

        // Tracking de uso das dezenas para balancear a base
        $usageCount = array_fill_keys($baseNumbers, 0);

        foreach ($repetitionTargets as $targetRepetitions) {
            $betFound = false;
            $attempts = 0;

            // Tolerância dinâmica: a cada 500 tentativas, relaxamos um pouco as regras
            while ($attempts < $maxAttemptsPerBet) {
                $tolerance = floor($attempts / 500);

                if ($targetRepetitions !== null && ! empty($lastDrawnNumbers)) {
                    $currentBet = $this->generateStratifiedCombination($repeatedPool, $nonRepeatedPool, $targetRepetitions, $betSize, $usageCount);
                } else {
                    $currentBet = $this->generateRandomCombination($baseNumbers, $betSize, $usageCount);
                }

                sort($currentBet);

                if (! in_array($currentBet, $uniqueBets, true)) {
                    $failedReason = null;
                    if ($this->isBalanced($currentBet, $parameters, $betSize, $lastDrawnNumbers, $temperatures, $tolerance, $failedReason)) {
                        yield $currentBet;
                        $uniqueBets[] = $currentBet;
                        $generatedCount++;

                        // Atualiza o uso para balancear próximas apostas
                        foreach ($currentBet as $num) {
                            $usageCount[$num]++;
                        }

                        $betFound = true;
                        break;
                    } else {
                        if ($failedReason) {
                            $rejectionStats[$failedReason] = ($rejectionStats[$failedReason] ?? 0) + 1;
                        }
                    }
                }
                $attempts++;
            }

            if (! $betFound) {
                // Se não achou com tolerância, tenta sem os parâmetros rigorosos mas respeitando a cota
                while ($attempts < $maxAttemptsPerBet + 500) {
                    // Se nas últimas 250 tentativas não achou, significa que a cota esgotou matematicamente (ex: só existe 1 combinação possível com 9 repetidas, mas a cota pediu 2 jogos)
                    // Solução: relaxa a cota e gera randomicamente do grupo base para garantir o preenchimento.
                    if ($attempts > $maxAttemptsPerBet + 250) {
                        $currentBet = $this->generateRandomCombination($baseNumbers, $betSize, $usageCount);
                    } elseif ($targetRepetitions !== null && ! empty($lastDrawnNumbers)) {
                        $currentBet = $this->generateStratifiedCombination($repeatedPool, $nonRepeatedPool, $targetRepetitions, $betSize, $usageCount);
                    } else {
                        $currentBet = $this->generateRandomCombination($baseNumbers, $betSize, $usageCount);
                    }
                    sort($currentBet);
                    if (! in_array($currentBet, $uniqueBets, true)) {
                        yield $currentBet;
                        $uniqueBets[] = $currentBet;
                        $generatedCount++;

                        foreach ($currentBet as $num) {
                            $usageCount[$num]++;
                        }
                        break;
                    }
                    $attempts++;
                }
            }
        }

        if ($generatedCount < $plannedBets) {
            $reasonMsg = '';
            if (! empty($rejectionStats)) {
                arsort($rejectionStats);
                $topReason = array_key_first($rejectionStats);
                $reasonMsg = " O principal fator de bloqueio durante a geração foi: '{$topReason}'. Tente flexibilizar este filtro.";
            }

            throw new LogicException(
                "Não foi possível gerar {$plannedBets} apostas equilibradas únicas com os parâmetros fornecidos. Foram geradas {$generatedCount}.".$reasonMsg
            );
        }
    }

    /**
     * Gera uma combinação aleatória de dezenas priorizando as menos utilizadas.
     *
     * @param  array<int>  $baseNumbers
     * @param  array<int, int>  $usageCount
     * @return array<int>
     */
    protected function generateRandomCombination(array $baseNumbers, int $betSize, array $usageCount = []): array
    {
        if (empty($usageCount)) {
            shuffle($baseNumbers);

            return array_slice($baseNumbers, 0, $betSize);
        }

        // Pondera a seleção para favorecer dezenas menos usadas (objetivo: usar todo o fechamento)
        $weightedPool = [];
        $maxUsage = max($usageCount) ?: 1;
        foreach ($baseNumbers as $num) {
            $weight = max(1, $maxUsage - $usageCount[$num] + 1);
            for ($i = 0; $i < $weight; $i++) {
                $weightedPool[] = $num;
            }
        }

        $selected = [];
        while (count($selected) < $betSize) {
            $idx = array_rand($weightedPool);
            $num = $weightedPool[$idx];
            if (! in_array($num, $selected, true)) {
                $selected[] = $num;
            }
        }

        return $selected;
    }

    /**
     * Gera uma combinação respeitando cotas exatas de repetição.
     */
    protected function generateStratifiedCombination(array $repeatedPool, array $nonRepeatedPool, int $targetRepetitions, int $betSize, array $usageCount): array
    {
        $targetRepetitions = min($targetRepetitions, count($repeatedPool));
        $targetNonRepetitions = $betSize - $targetRepetitions;

        // Ajusta se não houver dezenas não-repetidas suficientes
        if ($targetNonRepetitions > count($nonRepeatedPool)) {
            $targetNonRepetitions = count($nonRepeatedPool);
            $targetRepetitions = $betSize - $targetNonRepetitions;
        }

        $repSelection = $this->generateRandomCombination($repeatedPool, $targetRepetitions, $usageCount);
        $nonRepSelection = $this->generateRandomCombination($nonRepeatedPool, $targetNonRepetitions, $usageCount);

        return array_merge($repSelection, $nonRepSelection);
    }

    /**
     * Verifica se uma aposta atende aos critérios de equilíbrio com tolerância.
     *
     * @param  array<int>  $bet
     * @param  array<string, mixed>  $parameters
     * @param  array<int>|null  $lastDrawnNumbers
     * @param  array<int, array<string, mixed>>|null  $temperatures
     * @param  int  $tolerance  Margem de flexibilidade
     */
    protected function isBalanced(
        array $bet,
        array $parameters,
        int $betSize,
        ?array $lastDrawnNumbers = null,
        ?array $temperatures = null,
        int $tolerance = 0,
        &$failedReason = null
    ): bool {
        // Equilíbrio Par/Ímpar com Tolerância
        if (isset($parameters['even_odd_balance'])) {
            $evenCount = count(array_filter($bet, fn ($n) => $n % 2 === 0));
            [$minEven, $maxEven] = $parameters['even_odd_balance'];
            if ($evenCount < ($minEven - $tolerance) || $evenCount > ($maxEven + $tolerance)) {
                $failedReason = 'Equilíbrio Par/Ímpar';

                return false;
            }
        }

        // Faixa de Soma
        if (isset($parameters['sum_range'])) {
            $sum = array_sum($bet);
            [$minSum, $maxSum] = $parameters['sum_range'];
            if ($sum < $minSum || $sum > $maxSum) {
                $failedReason = 'Faixa de Soma';

                return false;
            }
        }

        // Contagem de Primos
        if (isset($parameters['primes_count'])) {
            $primesInBet = count(array_intersect($bet, self::PRIMES));
            [$minPrimes, $maxPrimes] = $parameters['primes_count'];
            if ($primesInBet < $minPrimes || $primesInBet > $maxPrimes) {
                $failedReason = 'Contagem de Primos';

                return false;
            }
        }

        // Contagem de Fibonacci
        if (isset($parameters['fibonacci_count'])) {
            $fibonacciInBet = count(array_intersect($bet, self::FIBONACCI));
            [$minFibonacci, $maxFibonacci] = $parameters['fibonacci_count'];
            if ($fibonacciInBet < $minFibonacci || $fibonacciInBet > $maxFibonacci) {
                $failedReason = 'Contagem de Fibonacci';

                return false;
            }
        }

        // Repetidas do Último Sorteio
        if (isset($parameters['repeated_last_draw']) && is_array($lastDrawnNumbers)) {
            $repeatedCount = count(array_intersect($bet, $lastDrawnNumbers));
            [$minRepeated, $maxRepeated] = $parameters['repeated_last_draw'];
            if ($repeatedCount < $minRepeated || $repeatedCount > $maxRepeated) {
                $failedReason = 'Repetidas do Último Sorteio';

                return false;
            }
        }

        // Faixa de Score (0 a 1000)
        if (isset($parameters['score_range'])) {
            $scoreData = app(BetScoringService::class)->calculateScore($bet);
            $totalScore = $scoreData['total_score'] ?? 0;
            [$minScore, $maxScore] = $parameters['score_range'];
            if ($totalScore < $minScore || $totalScore > $maxScore) {
                $failedReason = 'Faixa de Score';

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
                if ($hotCount < ($minHot - $tolerance) || $hotCount > ($maxHot + $tolerance)) {
                    $failedReason = 'Dezenas Quentes';

                    return false;
                }
            }

            if (isset($tempRules['neutral'])) {
                [$minNeutral, $maxNeutral] = $tempRules['neutral'];
                if ($neutralCount < ($minNeutral - $tolerance) || $neutralCount > ($maxNeutral + $tolerance)) {
                    $failedReason = 'Dezenas Neutras';

                    return false;
                }
            }

            if (isset($tempRules['cold'])) {
                [$minCold, $maxCold] = $tempRules['cold'];
                if ($coldCount < ($minCold - $tolerance) || $coldCount > ($maxCold + $tolerance)) {
                    $failedReason = 'Dezenas Frias';

                    return false;
                }
            }
        }

        return true;
    }
}
