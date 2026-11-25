<?php

namespace App\Services;

use InvalidArgumentException;

class BaseNumbersSuggestionService
{
    /**
     * Dezenas da moldura do volante 5x5 da Lotofácil.
     *
     * @var array<int, int>
     */
    public const FRAME_NUMBERS = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

    /**
     * Proporção-alvo de dezenas da moldura no grupo-base (10 de cada 15).
     */
    protected const FRAME_TARGET_RATIO = 10 / 15;

    protected const PARITY_BONUS = 15;

    /**
     * Quantidade de dezenas de maior pontuação heurística ("mais indicadas").
     */
    public const MOST_INDICATED = 10;

    /**
     * Quantidade de dezenas de menor pontuação heurística ("menos indicadas").
     */
    public const LEAST_INDICATED = 5;

    /**
     * Penalidade que faz uma dezena "menos indicada" só entrar no grupo quando não há alternativa.
     */
    protected const LEAST_INDICATED_PENALTY = 1000;

    protected const FRAME_BONUS = 12;

    public function __construct(
        protected LotofacilStatisticsService $statisticsService
    ) {}

    /**
     * Calcula a faixa de repetições do último concurso que permite montar um grupo-base
     * com exatamente $totalBase dezenas.
     *
     * As dezenas novas só podem vir das que não saíram no último concurso, então
     * faltam dezenas quando $totalBase − repetições é maior que esse conjunto.
     *
     * @return array{min: int, max: int}
     */
    public function repetitionRange(int $totalBase, int $lastDrawnCount = 15): array
    {
        $nonDrawnCount = 25 - $lastDrawnCount;

        return [
            'min' => max(0, $totalBase - $nonDrawnCount),
            'max' => min($lastDrawnCount, $totalBase),
        ];
    }

    /**
     * Sugere um grupo-base combinando dezenas repetidas do último concurso com dezenas novas.
     *
     * Critérios heurísticos (não aumentam a probabilidade de acerto): frequência histórica,
     * atraso atual, dezenas faltantes do ciclo e equilíbrio de paridade e moldura/centro.
     *
     * @return array{
     *     numbers: array<int, int>,
     *     report: array{
     *         total_base: int,
     *         repetitions: int,
     *         new_numbers: int,
     *         requested_total_base: int,
     *         requested_repetitions: int,
     *         adjustment: string|null,
     *         repeated_list: array<int, int>,
     *         new_list: array<int, int>,
     *         cycle_numbers: array<int, int>,
     *         delayed_numbers: array<int, int>,
     *         most_indicated: array<int, int>,
     *         least_indicated: array<int, int>,
     *         least_avoided: array<int, int>,
     *         least_included: array<int, int>,
     *         criteria: array{repeated: string, new: string, cross: string}
     *     }
     * }
     *
     * @throws InvalidArgumentException
     */
    public function suggest(int $totalBase, int $repetitions): array
    {
        if ($totalBase < 15 || $totalBase > 25) {
            throw new InvalidArgumentException('O grupo-base deve ter entre 15 e 25 dezenas.');
        }

        if ($repetitions < 0 || $repetitions > 15) {
            throw new InvalidArgumentException('As repetições do último concurso devem estar entre 0 e 15.');
        }

        $lastContestData = $this->statisticsService->getLastContestWithSum();
        $lastDrawn = array_values(array_map('intval', $lastContestData['result']['drawn_numbers'] ?? []));

        $range = $this->repetitionRange($totalBase, count($lastDrawn));
        $effectiveRepetitions = min(max($repetitions, $range['min']), $range['max']);

        $adjustment = null;
        if ($effectiveRepetitions !== $repetitions) {
            $adjustment = sprintf(
                'Repetições ajustadas de %d para %d: um grupo-base de %d dezenas precisa de %d a %d dezenas do último concurso, pois só existem %d dezenas fora dele.',
                $repetitions,
                $effectiveRepetitions,
                $totalBase,
                $range['min'],
                $range['max'],
                25 - count($lastDrawn)
            );
        }

        $frequencies = $this->statisticsService->getNumberFrequencies()->toArray();
        $cycleAnalysis = $this->statisticsService->getDecadesCycleAnalysis();
        $delayAnalysis = collect($this->statisticsService->getCurrentDelayAnalysis())->keyBy('number');

        $missingNumbers = $cycleAnalysis['missing_numbers'] ?? [];

        // Dupla avaliação: as dezenas de menor pontuação ficam de fora sempre que possível
        $indications = $this->indicationGroups($this->heuristicScores($frequencies, $delayAnalysis->all(), $cycleAnalysis));
        $leastIndicated = $indications['least_indicated'];

        $selectedRepeated = $this->selectRepeatedNumbers($lastDrawn, $effectiveRepetitions, $frequencies, $leastIndicated, $indications['scores']);

        $candidates = array_values(array_diff(range(1, 25), $lastDrawn));
        $selectedNew = $this->selectNewNumbers(
            $candidates,
            $totalBase - count($selectedRepeated),
            $selectedRepeated,
            $this->candidateScores($candidates, $indications['scores'], $leastIndicated)
        );

        $numbers = array_merge($selectedRepeated, $selectedNew);
        sort($numbers);
        sort($selectedRepeated);
        sort($selectedNew);

        $leastIncluded = array_values(array_intersect($leastIndicated, $numbers));
        $leastAvoided = array_values(array_diff($leastIndicated, $numbers));

        return [
            'numbers' => $numbers,
            'report' => [
                'total_base' => count($numbers),
                'repetitions' => count($selectedRepeated),
                'new_numbers' => count($selectedNew),
                'requested_total_base' => $totalBase,
                'requested_repetitions' => $repetitions,
                'adjustment' => $adjustment,
                'repeated_list' => $selectedRepeated,
                'new_list' => $selectedNew,
                'cycle_numbers' => $missingNumbers,
                'delayed_numbers' => $delayAnalysis->sortByDesc('delay')->take(8)->pluck('number')->all(),
                'most_indicated' => $indications['most_indicated'],
                'least_indicated' => $leastIndicated,
                'least_avoided' => $leastAvoided,
                'least_included' => $leastIncluded,
                'criteria' => [
                    'cross' => $leastIncluded === []
                        ? sprintf('As %d dezenas menos indicadas ficaram fora do grupo.', count($leastIndicated))
                        : sprintf(
                            '%d das %d dezenas menos indicadas ficaram fora do grupo; %d precisaram entrar para completar o tamanho e as repetições pedidas.',
                            count($leastAvoided),
                            count($leastIndicated),
                            count($leastIncluded)
                        ),
                    'repeated' => 'Foram selecionadas '.count($selectedRepeated).' dezenas do concurso anterior priorizando a maior frequência geral histórica.',
                    'new' => 'Foram selecionadas '.count($selectedNew).' dezenas baseando-se na frequência geral, no maior atraso atual e na projeção de fechamento do ciclo (peso maior para as '.count($missingNumbers).' dezenas faltantes), equilibradas a cada escolha com a proporção de pares/ímpares e moldura/centro do grupo.',
                ],
            ],
        ];
    }

    /**
     * Escolhe as repetidas entre as dezenas de maior frequência histórica do último concurso,
     * deixando as "menos indicadas" por último.
     *
     * @param  array<int, int>  $lastDrawn
     * @param  array<int, int>  $frequencies
     * @param  array<int, int>  $leastIndicated
     * @param  array<int, float>  $scores
     * @return array<int, int>
     */
    protected function selectRepeatedNumbers(array $lastDrawn, int $repetitions, array $frequencies, array $leastIndicated = [], array $scores = []): array
    {
        if ($repetitions === 0 || $lastDrawn === []) {
            return [];
        }

        $preferred = array_values(array_diff($lastDrawn, $leastIndicated));

        if ($repetitions > count($preferred)) {
            // Não há repetidas suficientes fora das menos indicadas: completa com as de maior pontuação entre elas
            $fallback = array_values(array_intersect($lastDrawn, $leastIndicated));
            usort($fallback, fn (int $a, int $b): int => ($scores[$b] ?? 0) <=> ($scores[$a] ?? 0));

            return array_merge($preferred, array_slice($fallback, 0, $repetitions - count($preferred)));
        }

        // Embaralha antes da ordenação estável para desempatar frequências iguais ao acaso
        $ranked = $preferred;
        shuffle($ranked);
        usort($ranked, fn (int $a, int $b): int => ($frequencies[$b] ?? 0) <=> ($frequencies[$a] ?? 0));

        $pool = array_slice($ranked, 0, min(count($ranked), $repetitions + 4));
        shuffle($pool);

        return array_slice($pool, 0, $repetitions);
    }

    /**
     * Pontuação heurística das 25 dezenas: frequência histórica ×0,1 + atraso ×2 + bônus do ciclo.
     *
     * É o mesmo critério da sugestão do grupo-base, sem o ruído aleatório, então o resultado é
     * reproduzível. Ele não altera a probabilidade: cada dezena tem 60% de chance em todo concurso.
     *
     * @param  array<int, int>|null  $frequencies
     * @param  array<int, array{number: int, delay: int}>|null  $delays  Atrasos indexados pela dezena.
     * @param  array<string, mixed>|null  $cycleAnalysis
     * @return array<int, float>
     */
    public function heuristicScores(?array $frequencies = null, ?array $delays = null, ?array $cycleAnalysis = null): array
    {
        $frequencies ??= $this->statisticsService->getNumberFrequencies()->toArray();
        $delays ??= collect($this->statisticsService->getCurrentDelayAnalysis())->keyBy('number')->all();
        $cycleAnalysis ??= $this->statisticsService->getDecadesCycleAnalysis();

        $missingNumbers = $cycleAnalysis['missing_numbers'] ?? [];
        $averageCycle = $cycleAnalysis['average_cycle_length'] ?: 4.7;
        $contestsInCycle = $cycleAnalysis['contests_in_current_cycle'] ?: 1;
        $estimatedRemaining = max(1.0, $averageCycle - $contestsInCycle);
        $cycleBoost = $missingNumbers !== [] ? count($missingNumbers) / $estimatedRemaining : 0;

        $scores = [];
        foreach (range(1, 25) as $number) {
            $score = ($frequencies[$number] ?? 0) * 0.1;
            $score += ($delays[$number]['delay'] ?? 1) * 2;

            if (in_array($number, $missingNumbers, true)) {
                $score += $cycleBoost * 3;
            }

            $scores[$number] = round($score, 4);
        }

        return $scores;
    }

    /**
     * Separa as dezenas mais e menos indicadas pela pontuação heurística.
     * Empates são decididos pela menor dezena.
     *
     * @param  array<int, float>  $scores
     * @return array{scores: array<int, float>, most_indicated: array<int, int>, least_indicated: array<int, int>}
     */
    public function indicationGroups(array $scores): array
    {
        $ranked = array_keys($scores);
        usort($ranked, fn (int $a, int $b): int => [$scores[$b], $a] <=> [$scores[$a], $b]);

        $most = array_slice($ranked, 0, self::MOST_INDICATED);
        $least = array_slice($ranked, -self::LEAST_INDICATED);
        sort($most);
        sort($least);

        return [
            'scores' => $scores,
            'most_indicated' => $most,
            'least_indicated' => $least,
        ];
    }

    /**
     * Pontuação usada na escolha das dezenas novas: a heurística, um ruído para variar as sugestões
     * e uma penalidade que deixa as "menos indicadas" por último.
     *
     * @param  array<int, int>  $candidates
     * @param  array<int, float>  $scores
     * @param  array<int, int>  $leastIndicated
     * @return array<int, float>
     */
    protected function candidateScores(array $candidates, array $scores, array $leastIndicated): array
    {
        $candidateScores = [];
        foreach ($candidates as $number) {
            $penalty = in_array($number, $leastIndicated, true) ? self::LEAST_INDICATED_PENALTY : 0;
            $candidateScores[$number] = ($scores[$number] ?? 0) + mt_rand(0, 5) - $penalty;
        }

        return $candidateScores;
    }

    /**
     * Seleciona as dezenas novas uma a uma, recalculando os bônus de paridade e moldura
     * sobre o grupo já formado (repetidas + novas escolhidas até o momento).
     *
     * @param  array<int, int>  $candidates
     * @param  array<int, int>  $selectedRepeated
     * @param  array<int, float>  $baseScores
     * @return array<int, int>
     */
    protected function selectNewNumbers(array $candidates, int $neededCount, array $selectedRepeated, array $baseScores): array
    {
        $frameSet = array_flip(self::FRAME_NUMBERS);
        $group = $selectedRepeated;
        $selected = [];

        while (count($selected) < $neededCount && $candidates !== []) {
            $evens = count(array_filter($group, fn (int $n): bool => $n % 2 === 0));
            $odds = count($group) - $evens;
            $frameRatio = $group !== []
                ? count(array_filter($group, fn (int $n): bool => isset($frameSet[$n]))) / count($group)
                : self::FRAME_TARGET_RATIO;

            $bestNumber = null;
            $bestScore = null;

            foreach ($candidates as $number) {
                $score = $baseScores[$number] + $this->balanceBonus($number, $evens, $odds, $frameRatio, $frameSet);

                if ($bestScore === null || $score > $bestScore) {
                    $bestScore = $score;
                    $bestNumber = $number;
                }
            }

            $selected[] = $bestNumber;
            $group[] = $bestNumber;
            $candidates = array_values(array_diff($candidates, [$bestNumber]));
        }

        return $selected;
    }

    /**
     * Bônus para a dezena que aproxima o grupo do equilíbrio de paridade e da proporção de moldura.
     *
     * @param  array<int, int>  $frameSet
     */
    protected function balanceBonus(int $number, int $evens, int $odds, float $frameRatio, array $frameSet): int
    {
        $bonus = 0;
        $isEven = $number % 2 === 0;
        $isFrame = isset($frameSet[$number]);

        if ($isEven ? $evens < $odds : $odds <= $evens) {
            $bonus += self::PARITY_BONUS;
        }

        if ($isFrame ? $frameRatio < self::FRAME_TARGET_RATIO : $frameRatio >= self::FRAME_TARGET_RATIO) {
            $bonus += self::FRAME_BONUS;
        }

        return $bonus;
    }
}
