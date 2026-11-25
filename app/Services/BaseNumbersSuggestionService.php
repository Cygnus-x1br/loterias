<?php

namespace App\Services;

use App\Models\Closing;
use InvalidArgumentException;
use Random\Engine\Mt19937;
use Random\Randomizer;

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

    /**
     * Bônus, por fechamento anterior do mesmo concurso, para a dezena que ficou fora dele.
     * É maior que a penalidade das menos indicadas: cobrir o que ficou de fora vem primeiro.
     */
    protected const PREVIOUS_EXCLUSION_BONUS = 2000;

    /**
     * Variação máxima (em pontos) aplicada a cada dezena quando uma semente é informada.
     */
    protected const SEED_VARIATION = 10;

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
     * Próximo concurso a ser sorteado (último cadastrado + 1), usado como concurso alvo padrão.
     */
    public function nextContestNumber(): ?int
    {
        $lastContest = $this->statisticsService->getLastContest();

        return $lastContest ? (int) $lastContest->contest_number + 1 : null;
    }

    /**
     * Grupos-base dos fechamentos do usuário para o mesmo concurso (alvo ou já apostado).
     *
     * @return array<int, array<int, int>>
     */
    public function previousBasesFor(int $userId, ?int $contestNumber, ?int $ignoreClosingId = null): array
    {
        if ($contestNumber === null) {
            return [];
        }

        return Closing::query()
            ->where('user_id', $userId)
            ->where(fn ($query) => $query->where('target_contest_number', $contestNumber)->orWhere('contest_number', $contestNumber))
            ->when($ignoreClosingId !== null, fn ($query) => $query->whereKeyNot($ignoreClosingId))
            ->orderBy('id')
            ->pluck('base_numbers')
            ->filter(fn ($base): bool => is_array($base) && $base !== [])
            ->values()
            ->all();
    }

    /**
     * Sugere um grupo-base combinando dezenas repetidas do último concurso com dezenas novas.
     *
     * Critérios heurísticos (não aumentam a probabilidade de acerto): frequência histórica,
     * atraso atual, dezenas faltantes do ciclo e equilíbrio de paridade e moldura/centro.
     *
     * O resultado é determinístico: os mesmos dados, parâmetros e semente geram sempre o mesmo grupo.
     * Sem semente não há variação; com semente, cada dezena recebe uma variação reproduzível.
     *
     * Com $previousBases (grupos de outros fechamentos para o mesmo concurso), as dezenas que
     * ficaram fora deles têm prioridade e o grupo sugerido nunca repete um deles.
     *
     * @param  array<int, array<int, int|string>>  $previousBases
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
     *         seed: int|null,
     *         previous_closings: int,
     *         prioritized_numbers: array<int, int>,
     *         distinct_from_previous: bool,
     *         criteria: array{repeated: string, new: string, cross: string, previous: string|null}
     *     }
     * }
     *
     * @throws InvalidArgumentException
     */
    public function suggest(int $totalBase, int $repetitions, ?int $seed = null, array $previousBases = []): array
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

        $previousBases = $this->normalizeBases($previousBases);
        $selectionScores = $this->selectionScores($indications['scores'], $leastIndicated, $previousBases, $seed);

        $selectedRepeated = $this->selectRepeatedNumbers($lastDrawn, $effectiveRepetitions, $selectionScores);

        $candidates = array_values(array_diff(range(1, 25), $lastDrawn));
        $selectedNew = $this->selectNewNumbers(
            $candidates,
            $totalBase - count($selectedRepeated),
            $selectedRepeated,
            array_intersect_key($selectionScores, array_flip($candidates))
        );

        [$selectedRepeated, $selectedNew, $distinct] = $this->ensureDistinct(
            $selectedRepeated,
            $selectedNew,
            $lastDrawn,
            $candidates,
            $selectionScores,
            $previousBases
        );

        $numbers = array_merge($selectedRepeated, $selectedNew);
        sort($numbers);
        sort($selectedRepeated);
        sort($selectedNew);

        $excludedBefore = $previousBases === []
            ? []
            : array_values(array_unique(array_merge(...array_map(fn (array $base): array => array_diff(range(1, 25), $base), $previousBases))));
        $prioritized = array_values(array_intersect($numbers, $excludedBefore));
        sort($prioritized);

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
                'seed' => $seed,
                'previous_closings' => count($previousBases),
                'prioritized_numbers' => $prioritized,
                'distinct_from_previous' => $distinct,
                'criteria' => [
                    'previous' => $this->previousCriteria(count($previousBases), $prioritized, $distinct),
                    'cross' => $leastIncluded === []
                        ? sprintf('As %d dezenas menos indicadas ficaram fora do grupo.', count($leastIndicated))
                        : sprintf(
                            '%d das %d dezenas menos indicadas ficaram fora do grupo; %d precisaram entrar para completar o tamanho e as repetições pedidas.',
                            count($leastAvoided),
                            count($leastIndicated),
                            count($leastIncluded)
                        ),
                    'repeated' => 'Foram selecionadas '.count($selectedRepeated).' dezenas do concurso anterior priorizando a maior frequência geral histórica (empates pela menor dezena).',
                    'new' => 'Foram selecionadas '.count($selectedNew).' dezenas baseando-se na frequência geral, no maior atraso atual e na projeção de fechamento do ciclo (peso maior para as '.count($missingNumbers).' dezenas faltantes), equilibradas a cada escolha com a proporção de pares/ímpares e moldura/centro do grupo.',
                ],
            ],
        ];
    }

    /**
     * Escolhe as repetidas do último concurso pela pontuação de seleção (maior primeiro,
     * empate pela menor dezena). Entre as dezenas do último concurso o atraso é zero, então a
     * ordem segue a frequência histórica; menos indicadas ficam por último e dezenas que ficaram
     * fora de fechamentos anteriores do mesmo concurso vêm primeiro.
     *
     * @param  array<int, int>  $lastDrawn
     * @param  array<int, float>  $selectionScores
     * @return array<int, int>
     */
    protected function selectRepeatedNumbers(array $lastDrawn, int $repetitions, array $selectionScores): array
    {
        if ($repetitions === 0 || $lastDrawn === []) {
            return [];
        }

        return array_slice($this->rankByScore($lastDrawn, $selectionScores), 0, $repetitions);
    }

    /**
     * Ordena dezenas pela pontuação (maior primeiro), com empate pela menor dezena.
     *
     * @param  array<int, int>  $numbers
     * @param  array<int, float>  $scores
     * @return array<int, int>
     */
    protected function rankByScore(array $numbers, array $scores): array
    {
        $numbers = array_values($numbers);
        usort($numbers, fn (int $a, int $b): int => [($scores[$b] ?? 0), $a] <=> [($scores[$a] ?? 0), $b]);

        return $numbers;
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
     * Pontuação usada para escolher as dezenas do grupo:
     * heurística + variação da semente (se houver) + prioridade para o que ficou fora de
     * fechamentos anteriores − penalidade das menos indicadas.
     *
     * @param  array<int, float>  $scores
     * @param  array<int, int>  $leastIndicated
     * @param  array<int, array<int, int>>  $previousBases
     * @return array<int, float>
     */
    protected function selectionScores(array $scores, array $leastIndicated, array $previousBases, ?int $seed): array
    {
        $randomizer = $seed !== null ? new Randomizer(new Mt19937($seed)) : null;

        $selectionScores = [];
        foreach (range(1, 25) as $number) {
            $timesExcluded = count(array_filter($previousBases, fn (array $base): bool => ! in_array($number, $base, true)));

            $selectionScores[$number] = ($scores[$number] ?? 0)
                + ($randomizer?->getFloat(0, self::SEED_VARIATION) ?? 0)
                + $timesExcluded * self::PREVIOUS_EXCLUSION_BONUS
                - (in_array($number, $leastIndicated, true) ? self::LEAST_INDICATED_PENALTY : 0);
        }

        return $selectionScores;
    }

    /**
     * Garante que o grupo não seja igual a nenhum grupo anterior do mesmo concurso.
     * Se for, troca a dezena de menor pontuação do grupo pela de maior pontuação fora dele,
     * no mesmo tipo (nova ou repetida), para manter a quantidade de repetidas pedida.
     *
     * @param  array<int, int>  $repeated
     * @param  array<int, int>  $new
     * @param  array<int, int>  $lastDrawn
     * @param  array<int, int>  $candidates
     * @param  array<int, float>  $selectionScores
     * @param  array<int, array<int, int>>  $previousBases
     * @return array{0: array<int, int>, 1: array<int, int>, 2: bool}
     */
    protected function ensureDistinct(array $repeated, array $new, array $lastDrawn, array $candidates, array $selectionScores, array $previousBases): array
    {
        $isPrevious = function (array $repeatedGroup, array $newGroup) use ($previousBases): bool {
            $group = array_merge($repeatedGroup, $newGroup);
            sort($group);

            return in_array($group, $previousBases, true);
        };

        if (! $isPrevious($repeated, $new)) {
            return [$repeated, $new, true];
        }

        foreach ([['new', $candidates], ['repeated', $lastDrawn]] as [$type, $pool]) {
            $current = $type === 'new' ? $new : $repeated;
            $outside = $this->rankByScore(array_diff($pool, $current), $selectionScores);
            $inside = array_reverse($this->rankByScore($current, $selectionScores));

            foreach ($inside as $out) {
                foreach ($outside as $in) {
                    $swapped = array_values(array_merge(array_diff($current, [$out]), [$in]));
                    [$candidateRepeated, $candidateNew] = $type === 'new' ? [$repeated, $swapped] : [$swapped, $new];

                    if (! $isPrevious($candidateRepeated, $candidateNew)) {
                        return [$candidateRepeated, $candidateNew, true];
                    }
                }
            }
        }

        return [$repeated, $new, false];
    }

    /**
     * @param  array<int, array<int, int|string>>  $bases
     * @return array<int, array<int, int>>
     */
    protected function normalizeBases(array $bases): array
    {
        return array_values(array_map(function (array $base): array {
            $numbers = array_values(array_unique(array_map('intval', $base)));
            sort($numbers);

            return $numbers;
        }, array_filter($bases, fn ($base): bool => is_array($base) && $base !== [])));
    }

    /**
     * @param  array<int, int>  $prioritized
     */
    protected function previousCriteria(int $previousClosings, array $prioritized, bool $distinct): ?string
    {
        if ($previousClosings === 0) {
            return null;
        }

        $text = sprintf(
            'Já existe(m) %d fechamento(s) para este concurso: %d dezena(s) que ficaram fora dele(s) entraram com prioridade.',
            $previousClosings,
            count($prioritized)
        );

        return $distinct
            ? $text.' O grupo é diferente de todos os anteriores.'
            : $text.' Não foi possível formar um grupo diferente com este tamanho e estas repetições.';
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
