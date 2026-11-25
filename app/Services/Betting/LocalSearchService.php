<?php

namespace App\Services\Betting;

use App\Services\Betting\Math\Combinatorics;
use InvalidArgumentException;

/**
 * Busca local (item 12): melhora um conjunto de apostas já existente, sem gerar um novo do zero.
 *
 * Universo de cenários: todos os sorteios de 15 dezenas contidos no grupo-base. Para cada cenário
 * guarda-se o melhor número de acertos entre as apostas, e a função de avaliação é
 *
 *   F = w11·C11 + w12·C12 + w13·C13 + w14·C14 + w15·C15 − λ·|B| − μ·R
 *
 * em que C_k é a fração de cenários em que alguma aposta faz pelo menos k pontos e R é a
 * redundância (média de dezenas em comum entre pares de apostas, dividida por 15).
 * Cada passo propõe um movimento e só o aceita se F aumentar (subida de encosta). Por padrão o
 * número de apostas é mantido (orçamento fixo); com min_bets/max_bets e cost_penalty o conjunto pode encolher ou crescer.
 * Resultado heurístico: não há prova de que o conjunto final seja o melhor possível.
 */
class LocalSearchService
{
    public const MAX_BASE = 21;

    public const MAX_BETS = 60;

    public const MOVES = [
        'swap_number' => 'Trocar uma dezena de uma aposta',
        'replace_uncovered' => 'Substituir uma aposta por outra que cubra cenários mal cobertos',
        'exchange' => 'Trocar dezenas entre duas apostas',
        'remove' => 'Remover uma aposta redundante',
        'insert' => 'Inserir uma aposta que cubra cenários não cobertos',
        'replace_marginal' => 'Substituir a aposta de menor ganho marginal',
    ];

    public const STOP_REASONS = [
        'max_iterations' => 'Limite de iterações atingido',
        'stale' => 'Sem melhoria após vários movimentos seguidos',
        'target' => 'Cobertura desejada atingida',
        'time_limit' => 'Limite de tempo atingido',
    ];

    /**
     * @var array<int, int>
     */
    private array $pop = [];

    /**
     * Máscaras dos cenários (sorteios de 15 dezenas dentro do grupo-base).
     *
     * @var array<int, int>
     */
    private array $scenarios = [];

    /**
     * @var array<int, int>
     */
    private array $baseNumbers = [];

    private int $baseMask = 0;

    public function __construct(private HistoricalDrawFilter $historicalDrawFilter) {}

    /**
     * @param  array<int, int>  $baseNumbers
     * @param  array<int, array<int, int>>  $bets  Apostas de 15 dezenas, todas dentro do grupo-base.
     * @param  array<string, mixed>  $options  weights, cost_penalty, redundancy_penalty, max_iterations,
     *                                         max_stale, time_limit (segundos), target {hits, coverage},
     *                                         seed, min_bets, max_bets
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException
     */
    public function improve(array $baseNumbers, array $bets, array $options = []): array
    {
        $options = $this->options($options, count($bets));
        $this->prepare($baseNumbers, $bets);

        if ($options['seed'] !== null) {
            mt_srand((int) $options['seed']);
        }

        $startedAt = hrtime(true);

        // Estado: máscaras, acertos por cenário de cada aposta e os dois melhores valores por cenário
        $masks = array_map(fn (array $bet): int => $this->mask($bet), $bets);
        $hits = array_map(fn (int $mask): array => $this->hitsFor($mask), $masks);
        [$best1, $best2, $arg] = $this->topTwo($hits, count($this->scenarios));
        $overlapSum = $this->overlapSum($masks);

        $before = $this->summary($best1, count($masks), $overlapSum, $options);

        $accepted = array_fill_keys(array_keys(self::MOVES), 0);
        $attempted = array_fill_keys(array_keys(self::MOVES), 0);
        $currentF = $before['f'];
        $iterations = 0;
        $stale = 0;
        $stopReason = 'max_iterations';

        while (true) {
            if ($this->targetReached($best1, $options['target'])) {
                $stopReason = 'target';
                break;
            }
            if ($iterations >= $options['max_iterations']) {
                $stopReason = 'max_iterations';
                break;
            }
            if ($stale >= $options['max_stale']) {
                $stopReason = 'stale';
                break;
            }
            if ((hrtime(true) - $startedAt) / 1e9 >= $options['time_limit']) {
                $stopReason = 'time_limit';
                break;
            }

            $iterations++;
            $moveKeys = $this->availableMoves(count($masks), $options);
            $move = $moveKeys[mt_rand(0, count($moveKeys) - 1)];
            $attempted[$move]++;

            $proposal = $this->propose($move, $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);

            if ($proposal === null || $proposal['f'] <= $currentF + 1e-12) {
                $stale++;

                continue;
            }

            // Aceita o movimento
            $masks = $proposal['masks'];
            $hits = $proposal['hits'];
            [$best1, $best2, $arg] = $this->topTwo($hits, count($this->scenarios));
            $overlapSum = $proposal['overlap_sum'];
            $currentF = $proposal['f'];
            $accepted[$move]++;
            $stale = 0;
        }

        mt_srand();

        $finalBets = array_map(fn (int $mask): array => $this->numbers($mask), $masks);
        $after = $this->summary($best1, count($masks), $overlapSum, $options);

        return [
            'bets' => $finalBets,
            'before' => $before,
            'after' => $after,
            'improved' => $after['f'] > $before['f'] + 1e-12,
            'iterations' => $iterations,
            'accepted' => $accepted,
            'attempted' => $attempted,
            'stop_reason' => $stopReason,
            'stop_reason_label' => self::STOP_REASONS[$stopReason],
            'elapsed_ms' => round((hrtime(true) - $startedAt) / 1e6, 1),
            'scenarios' => count($this->scenarios),
            'seed' => $options['seed'],
            'options' => $options,
            'classification' => ResultClassification::Heuristic,
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function options(array $options, int $betsCount): array
    {
        $merged = array_merge([
            'weights' => [11 => 1.0, 12 => 2.0, 13 => 4.0, 14 => 8.0, 15 => 16.0],
            'cost_penalty' => 0.0,
            'redundancy_penalty' => 1.0,
            'max_iterations' => 2000,
            'max_stale' => 300,
            'time_limit' => 10.0,
            'target' => null,
            'seed' => null,
            'min_bets' => $betsCount,
            'max_bets' => $betsCount,
        ], $options);

        $merged['weights'] = array_replace([11 => 1.0, 12 => 2.0, 13 => 4.0, 14 => 8.0, 15 => 16.0], $merged['weights']);
        $merged['max_bets'] = min(self::MAX_BETS, max($betsCount, (int) $merged['max_bets']));
        $merged['min_bets'] = max(1, min($betsCount, (int) $merged['min_bets']));

        return $merged;
    }

    /**
     * @param  array<int, int>  $baseNumbers
     * @param  array<int, array<int, int>>  $bets
     *
     * @throws InvalidArgumentException
     */
    private function prepare(array $baseNumbers, array $bets): void
    {
        $baseNumbers = array_values(array_unique(array_map('intval', $baseNumbers)));
        sort($baseNumbers);

        if (count($baseNumbers) < 16 || count($baseNumbers) > self::MAX_BASE) {
            throw new InvalidArgumentException(sprintf('A busca local aceita grupo-base de 16 a %d dezenas.', self::MAX_BASE));
        }

        foreach ($baseNumbers as $number) {
            if ($number < 1 || $number > 25) {
                throw new InvalidArgumentException('As dezenas do grupo-base devem estar entre 1 e 25.');
            }
        }

        if ($bets === [] || count($bets) > self::MAX_BETS) {
            throw new InvalidArgumentException(sprintf('Informe de 1 a %d apostas para melhorar.', self::MAX_BETS));
        }

        $this->baseNumbers = $baseNumbers;
        $this->baseMask = $this->mask($baseNumbers);

        $seen = [];
        foreach ($bets as $bet) {
            $bet = array_values(array_unique(array_map('intval', $bet)));
            if (count($bet) !== 15) {
                throw new InvalidArgumentException('A busca local trabalha com apostas de 15 dezenas.');
            }
            if (($this->mask($bet) & ~$this->baseMask) !== 0) {
                throw new InvalidArgumentException('Todas as dezenas das apostas devem estar no grupo-base.');
            }
            $key = $this->mask($bet);
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('As apostas não podem ter combinações repetidas.');
            }
            $seen[$key] = true;
        }

        if ($this->pop === []) {
            for ($i = 0; $i < 8192; $i++) {
                $this->pop[$i] = substr_count(decbin($i), '1');
            }
        }

        $this->scenarios = [];
        foreach (Combinatorics::combinations($baseNumbers, 15) as $scenario) {
            $this->scenarios[] = $this->mask($scenario);
        }
    }

    /**
     * Movimentos aplicáveis agora: remover e inserir só valem quando o tamanho do conjunto pode variar.
     *
     * @param  array<string, mixed>  $options
     * @return array<int, string>
     */
    private function availableMoves(int $count, array $options): array
    {
        return array_values(array_filter(
            array_keys(self::MOVES),
            fn (string $move): bool => match ($move) {
                'remove' => $count > $options['min_bets'],
                'insert' => $count < $options['max_bets'],
                'exchange' => $count >= 2,
                default => true,
            }
        ));
    }

    /**
     * Propõe um movimento. Devolve o novo estado (se válido) com o valor de F, ou null.
     *
     * @param  array<int, int>  $masks
     * @param  array<int, array<int, int>>  $hits
     * @param  array<int, int>  $best1
     * @param  array<int, int>  $best2
     * @param  array<int, int>  $arg
     * @param  array<string, mixed>  $options
     * @return array{masks: array<int, int>, hits: array<int, array<int, int>>, overlap_sum: int, f: float}|null
     */
    private function propose(string $move, array $masks, array $hits, array $best1, array $best2, array $arg, int $overlapSum, array $options): ?array
    {
        $count = count($masks);

        switch ($move) {
            case 'swap_number':
                $i = mt_rand(0, $count - 1);
                $candidate = $this->swapOneNumber($masks[$i]);

                return $candidate === null ? null : $this->replace($i, $candidate, $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);

            case 'replace_uncovered':
                $i = mt_rand(0, $count - 1);
                $candidate = $this->poorlyCoveredScenario($best1);

                return $this->replace($i, $candidate, $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);

            case 'exchange':
                return $count < 2 ? null : $this->exchange($masks, $hits, $overlapSum, $options);

            case 'remove':
                if ($count <= $options['min_bets']) {
                    return null;
                }
                $i = $this->lowestMarginal($count, $best1, $best2, $arg);

                return $this->remove($i, $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);

            case 'insert':
                if ($count >= $options['max_bets']) {
                    return null;
                }

                return $this->insert($this->poorlyCoveredScenario($best1), $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);

            case 'replace_marginal':
                $i = $this->lowestMarginal($count, $best1, $best2, $arg);
                $bestProposal = null;
                for ($try = 0; $try < 6; $try++) {
                    $candidate = $try % 2 === 0 ? $this->poorlyCoveredScenario($best1) : $this->swapOneNumber($masks[$i]);
                    if ($candidate === null) {
                        continue;
                    }
                    $proposal = $this->replace($i, $candidate, $masks, $hits, $best1, $best2, $arg, $overlapSum, $options);
                    if ($proposal !== null && ($bestProposal === null || $proposal['f'] > $bestProposal['f'])) {
                        $bestProposal = $proposal;
                    }
                }

                return $bestProposal;
        }

        return null;
    }

    /**
     * @param  array<int, int>  $masks
     * @param  array<int, array<int, int>>  $hits
     * @param  array<int, int>  $best1
     * @param  array<int, int>  $best2
     * @param  array<int, int>  $arg
     * @param  array<string, mixed>  $options
     * @return array{masks: array<int, int>, hits: array<int, array<int, int>>, overlap_sum: int, f: float}|null
     */
    private function replace(int $i, int $candidate, array $masks, array $hits, array $best1, array $best2, array $arg, int $overlapSum, array $options): ?array
    {
        if (! $this->isUsable($candidate, $masks, $i)) {
            return null;
        }

        $candidateHits = $this->hitsFor($candidate);
        $histogram = array_fill(0, 16, 0);
        foreach ($candidateHits as $s => $h) {
            $base = $arg[$s] === $i ? $best2[$s] : $best1[$s];
            $histogram[$h > $base ? $h : $base]++;
        }

        $newOverlap = $overlapSum - $this->overlapOf($masks[$i], $masks, $i) + $this->overlapOf($candidate, $masks, $i);
        $newMasks = $masks;
        $newMasks[$i] = $candidate;
        $newHits = $hits;
        $newHits[$i] = $candidateHits;

        return [
            'masks' => $newMasks,
            'hits' => $newHits,
            'overlap_sum' => $newOverlap,
            'f' => $this->objective($histogram, count($masks), $newOverlap, $options),
        ];
    }

    /**
     * @param  array<int, int>  $masks
     * @param  array<int, array<int, int>>  $hits
     * @param  array<int, int>  $best1
     * @param  array<int, int>  $best2
     * @param  array<int, int>  $arg
     * @param  array<string, mixed>  $options
     * @return array{masks: array<int, int>, hits: array<int, array<int, int>>, overlap_sum: int, f: float}|null
     */
    private function insert(int $candidate, array $masks, array $hits, array $best1, array $best2, array $arg, int $overlapSum, array $options): ?array
    {
        if (! $this->isUsable($candidate, $masks, null)) {
            return null;
        }

        $candidateHits = $this->hitsFor($candidate);
        $histogram = array_fill(0, 16, 0);
        foreach ($candidateHits as $s => $h) {
            $histogram[$h > $best1[$s] ? $h : $best1[$s]]++;
        }

        $newOverlap = $overlapSum + $this->overlapOf($candidate, $masks, null);

        return [
            'masks' => [...$masks, $candidate],
            'hits' => [...$hits, $candidateHits],
            'overlap_sum' => $newOverlap,
            'f' => $this->objective($histogram, count($masks) + 1, $newOverlap, $options),
        ];
    }

    /**
     * @param  array<int, int>  $masks
     * @param  array<int, array<int, int>>  $hits
     * @param  array<int, int>  $best1
     * @param  array<int, int>  $best2
     * @param  array<int, int>  $arg
     * @param  array<string, mixed>  $options
     * @return array{masks: array<int, int>, hits: array<int, array<int, int>>, overlap_sum: int, f: float}
     */
    private function remove(int $i, array $masks, array $hits, array $best1, array $best2, array $arg, int $overlapSum, array $options): array
    {
        $histogram = array_fill(0, 16, 0);
        foreach ($best1 as $s => $value) {
            $histogram[$arg[$s] === $i ? $best2[$s] : $value]++;
        }

        $newOverlap = $overlapSum - $this->overlapOf($masks[$i], $masks, $i);
        $newMasks = $masks;
        $newHits = $hits;
        unset($newMasks[$i], $newHits[$i]);

        return [
            'masks' => array_values($newMasks),
            'hits' => array_values($newHits),
            'overlap_sum' => $newOverlap,
            'f' => $this->objective($histogram, count($masks) - 1, $newOverlap, $options),
        ];
    }

    /**
     * Troca uma dezena entre duas apostas (a dezena que só uma tem vai para a outra).
     *
     * @param  array<int, int>  $masks
     * @param  array<int, array<int, int>>  $hits
     * @param  array<string, mixed>  $options
     * @return array{masks: array<int, int>, hits: array<int, array<int, int>>, overlap_sum: int, f: float}|null
     */
    private function exchange(array $masks, array $hits, int $overlapSum, array $options): ?array
    {
        $count = count($masks);
        $i = mt_rand(0, $count - 1);
        $j = mt_rand(0, $count - 2);
        if ($j >= $i) {
            $j++;
        }

        $onlyI = $this->numbers($masks[$i] & ~$masks[$j]);
        $onlyJ = $this->numbers($masks[$j] & ~$masks[$i]);
        if ($onlyI === [] || $onlyJ === []) {
            return null;
        }

        $a = $onlyI[mt_rand(0, count($onlyI) - 1)];
        $b = $onlyJ[mt_rand(0, count($onlyJ) - 1)];
        $newI = ($masks[$i] & ~(1 << ($a - 1))) | (1 << ($b - 1));
        $newJ = ($masks[$j] & ~(1 << ($b - 1))) | (1 << ($a - 1));

        $others = $masks;
        unset($others[$i], $others[$j]);
        if (! $this->isUsable($newI, $others, null) || ! $this->isUsable($newJ, [...$others, $newI], null) || $newI === $newJ) {
            return null;
        }

        $newMasks = $masks;
        $newMasks[$i] = $newI;
        $newMasks[$j] = $newJ;
        $newHits = $hits;
        $newHits[$i] = $this->hitsFor($newI);
        $newHits[$j] = $this->hitsFor($newJ);

        $histogram = array_fill(0, 16, 0);
        foreach (array_keys($this->scenarios) as $s) {
            $best = 0;
            foreach ($newHits as $list) {
                if ($list[$s] > $best) {
                    $best = $list[$s];
                }
            }
            $histogram[$best]++;
        }

        $newOverlap = $this->overlapSum($newMasks);

        return [
            'masks' => $newMasks,
            'hits' => $newHits,
            'overlap_sum' => $newOverlap,
            'f' => $this->objective($histogram, $count, $newOverlap, $options),
        ];
    }

    /**
     * @param  array<int, int>  $histogram  Quantidade de cenários por melhor número de acertos (0 a 15).
     * @param  array<string, mixed>  $options
     */
    private function objective(array $histogram, int $betsCount, int $overlapSum, array $options): float
    {
        $total = count($this->scenarios);
        $coverage = $this->coverageFromHistogram($histogram, $total);

        $value = 0.0;
        foreach ($options['weights'] as $hits => $weight) {
            $value += $weight * $coverage[$hits];
        }

        return $value
            - $options['cost_penalty'] * $betsCount
            - $options['redundancy_penalty'] * $this->redundancy($overlapSum, $betsCount);
    }

    /**
     * @param  array<int, int>  $histogram
     * @return array<int, float>
     */
    private function coverageFromHistogram(array $histogram, int $total): array
    {
        $coverage = [];
        $running = 0;
        for ($k = 15; $k >= 11; $k--) {
            $running += $histogram[$k];
            $coverage[$k] = $total > 0 ? $running / $total : 0.0;
        }
        ksort($coverage);

        return $coverage;
    }

    /**
     * @param  array<int, int>  $best1
     * @param  array<string, mixed>  $options
     * @return array{count: int, coverage: array<int, float>, redundancy: float, f: float}
     */
    private function summary(array $best1, int $betsCount, int $overlapSum, array $options): array
    {
        $histogram = array_fill(0, 16, 0);
        foreach ($best1 as $value) {
            $histogram[$value]++;
        }

        return [
            'count' => $betsCount,
            'coverage' => $this->coverageFromHistogram($histogram, count($this->scenarios)),
            'redundancy' => $this->redundancy($overlapSum, $betsCount),
            'f' => $this->objective($histogram, $betsCount, $overlapSum, $options),
        ];
    }

    private function redundancy(int $overlapSum, int $betsCount): float
    {
        $pairs = $betsCount * ($betsCount - 1) / 2;

        return $pairs > 0 ? ($overlapSum / $pairs) / 15 : 0.0;
    }

    /**
     * @param  array<int, int>  $best1
     * @param  array{hits: int, coverage: float}|null  $target
     */
    private function targetReached(array $best1, ?array $target): bool
    {
        if ($target === null) {
            return false;
        }

        $reached = 0;
        foreach ($best1 as $value) {
            if ($value >= $target['hits']) {
                $reached++;
            }
        }

        return $reached / max(1, count($best1)) >= $target['coverage'] - 1e-12;
    }

    /**
     * Candidata vinda de um cenário pouco coberto (entre algumas amostras, o de menor melhor acerto).
     *
     * @param  array<int, int>  $best1
     */
    private function poorlyCoveredScenario(array $best1): int
    {
        $lastIndex = count($this->scenarios) - 1;
        $chosen = mt_rand(0, $lastIndex);
        for ($sample = 0; $sample < 40; $sample++) {
            $index = mt_rand(0, $lastIndex);
            if ($best1[$index] < $best1[$chosen]) {
                $chosen = $index;
            }
        }

        return $this->scenarios[$chosen];
    }

    private function swapOneNumber(int $mask): ?int
    {
        $inside = $this->numbers($mask);
        $outside = $this->numbers($this->baseMask & ~$mask);
        if ($outside === []) {
            return null;
        }

        $a = $inside[mt_rand(0, count($inside) - 1)];
        $b = $outside[mt_rand(0, count($outside) - 1)];

        return ($mask & ~(1 << ($a - 1))) | (1 << ($b - 1));
    }

    /**
     * Aposta que menos contribui: soma, nos cenários em que ela é a melhor, da diferença para a segunda melhor.
     *
     * @param  array<int, int>  $best1
     * @param  array<int, int>  $best2
     * @param  array<int, int>  $arg
     */
    private function lowestMarginal(int $count, array $best1, array $best2, array $arg): int
    {
        $marginal = array_fill(0, $count, 0);
        foreach ($arg as $s => $owner) {
            $marginal[$owner] += $best1[$s] - $best2[$s];
        }

        $lowest = min($marginal);
        $ties = array_keys(array_filter($marginal, fn (int $value): bool => $value === $lowest));

        return $ties[mt_rand(0, count($ties) - 1)];
    }

    /**
     * @param  array<int, int>  $others
     */
    private function isUsable(int $candidate, array $others, ?int $ignoreIndex): bool
    {
        if ($this->popcount($candidate) !== 15 || ($candidate & ~$this->baseMask) !== 0) {
            return false;
        }

        foreach ($others as $index => $mask) {
            if ($index !== $ignoreIndex && $mask === $candidate) {
                return false;
            }
        }

        return ! $this->historicalDrawFilter->isPastDraw($this->numbers($candidate));
    }

    /**
     * @param  array<int, int>  $masks
     */
    private function overlapOf(int $mask, array $masks, ?int $ignoreIndex): int
    {
        $sum = 0;
        foreach ($masks as $index => $other) {
            if ($index !== $ignoreIndex) {
                $sum += $this->popcount($mask & $other);
            }
        }

        return $sum;
    }

    /**
     * @param  array<int, int>  $masks
     */
    private function overlapSum(array $masks): int
    {
        $masks = array_values($masks);
        $sum = 0;
        for ($i = 0; $i < count($masks); $i++) {
            for ($j = $i + 1; $j < count($masks); $j++) {
                $sum += $this->popcount($masks[$i] & $masks[$j]);
            }
        }

        return $sum;
    }

    /**
     * Para cada cenário: o melhor e o segundo melhor número de acertos entre as apostas, e quem faz o melhor.
     *
     * @param  array<int, array<int, int>>  $hits
     * @return array{0: array<int, int>, 1: array<int, int>, 2: array<int, int>}
     */
    private function topTwo(array $hits, int $scenarioCount): array
    {
        $best1 = array_fill(0, $scenarioCount, 0);
        $best2 = array_fill(0, $scenarioCount, 0);
        $arg = array_fill(0, $scenarioCount, 0);

        foreach ($hits as $index => $list) {
            foreach ($list as $s => $h) {
                if ($h > $best1[$s]) {
                    $best2[$s] = $best1[$s];
                    $best1[$s] = $h;
                    $arg[$s] = $index;
                } elseif ($h > $best2[$s]) {
                    $best2[$s] = $h;
                }
            }
        }

        return [$best1, $best2, $arg];
    }

    /**
     * @return array<int, int>
     */
    private function hitsFor(int $mask): array
    {
        $hits = [];
        foreach ($this->scenarios as $s => $scenario) {
            $common = $mask & $scenario;
            $hits[$s] = $this->pop[$common & 8191] + $this->pop[$common >> 13];
        }

        return $hits;
    }

    private function popcount(int $value): int
    {
        return $this->pop[$value & 8191] + $this->pop[$value >> 13];
    }

    /**
     * @param  array<int, int>  $numbers
     */
    private function mask(array $numbers): int
    {
        $mask = 0;
        foreach ($numbers as $number) {
            $mask |= 1 << ($number - 1);
        }

        return $mask;
    }

    /**
     * @return array<int, int>
     */
    private function numbers(int $mask): array
    {
        $numbers = [];
        for ($bit = 0; $bit < 25; $bit++) {
            if ($mask & (1 << $bit)) {
                $numbers[] = $bit + 1;
            }
        }

        return $numbers;
    }
}
