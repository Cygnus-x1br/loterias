<?php

namespace App\Services;

use App\Models\HistoricalResult;
use App\Models\NumberIndication;

/**
 * Registra, antes de cada concurso, as dezenas mais e menos indicadas pelo critério heurístico
 * da sugestão estatística e confere o resultado depois do sorteio.
 *
 * Como o registro é feito antes do sorteio, o acompanhamento mede o critério em concursos que
 * ainda não tinham ocorrido (fora da amostra), sem o risco de ajustar a regra aos dados.
 */
class NumberIndicationService
{
    /**
     * Chance de qualquer dezena ser sorteada (15 de 25).
     */
    public const EXPECTED_HIT_RATE = 0.6;

    /**
     * Concursos conferidos a partir dos quais o resultado começa a ser interpretado.
     */
    public const MIN_EVALUATED_CONTESTS = 100;

    public function __construct(
        protected BaseNumbersSuggestionService $suggestionService
    ) {}

    /**
     * Reage ao cadastro ou edição de um resultado: confere a indicação daquele concurso e,
     * se ele for o último da série, registra a indicação do próximo.
     */
    public function handleSavedResult(HistoricalResult $result): void
    {
        $this->evaluate($result);

        if ($result->contest_number >= (int) HistoricalResult::max('contest_number')) {
            $this->registerNext();
        }
    }

    /**
     * Registra a indicação para o concurso seguinte ao último cadastrado.
     * Não altera indicações já conferidas.
     */
    public function registerNext(): ?NumberIndication
    {
        $latestContest = (int) HistoricalResult::max('contest_number');

        if ($latestContest === 0) {
            return null;
        }

        $target = $latestContest + 1;
        $existing = NumberIndication::where('target_contest_number', $target)->first();

        if ($existing?->evaluated_at !== null) {
            return $existing;
        }

        $groups = $this->suggestionService->indicationGroups($this->suggestionService->heuristicScores());

        return NumberIndication::updateOrCreate(
            ['target_contest_number' => $target],
            [
                'based_on_contest_number' => $latestContest,
                'scores' => $groups['scores'],
                'most_indicated' => $groups['most_indicated'],
                'least_indicated' => $groups['least_indicated'],
            ]
        );
    }

    /**
     * Confere a indicação registrada para o concurso deste resultado, se houver.
     */
    public function evaluate(HistoricalResult $result): ?NumberIndication
    {
        $indication = NumberIndication::where('target_contest_number', $result->contest_number)->first();

        if ($indication === null) {
            return null;
        }

        $drawn = array_map('intval', is_array($result->drawn_numbers) ? $result->drawn_numbers : (json_decode((string) $result->drawn_numbers, true) ?? []));

        $indication->update([
            'most_hits' => count(array_intersect($indication->most_indicated, $drawn)),
            'least_hits' => count(array_intersect($indication->least_indicated, $drawn)),
            'evaluated_at' => now(),
        ]);

        return $indication;
    }

    /**
     * Confere todas as indicações pendentes cujo concurso já foi cadastrado e registra a próxima.
     *
     * @return array{evaluated: int, registered_for: int|null}
     */
    public function sync(): array
    {
        $evaluated = 0;

        NumberIndication::whereNull('evaluated_at')
            ->orderBy('target_contest_number')
            ->get()
            ->each(function (NumberIndication $indication) use (&$evaluated): void {
                $result = HistoricalResult::where('contest_number', $indication->target_contest_number)->first();

                if ($result !== null && $this->evaluate($result) !== null) {
                    $evaluated++;
                }
            });

        return [
            'evaluated' => $evaluated,
            'registered_for' => $this->registerNext()?->target_contest_number,
        ];
    }

    /**
     * Resultado acumulado do acompanhamento.
     *
     * @return array{
     *     evaluated_contests: int,
     *     first_contest: int|null,
     *     last_contest: int|null,
     *     least: array{hits: int, total: int, rate: float|null, margin: float|null},
     *     most: array{hits: int, total: int, rate: float|null, margin: float|null},
     *     others: array{hits: int, total: int, rate: float|null, margin: float|null},
     *     expected_rate: float,
     *     verdict: string,
     *     next: array{target_contest_number: int, most_indicated: array<int, int>, least_indicated: array<int, int>}|null
     * }
     */
    public function summary(): array
    {
        $evaluated = NumberIndication::whereNotNull('evaluated_at')->get();
        $count = $evaluated->count();

        $leastHits = (int) $evaluated->sum('least_hits');
        $mostHits = (int) $evaluated->sum('most_hits');
        $leastSize = BaseNumbersSuggestionService::LEAST_INDICATED;
        $mostSize = BaseNumbersSuggestionService::MOST_INDICATED;

        $least = $this->rate($leastHits, $count * $leastSize);
        $most = $this->rate($mostHits, $count * $mostSize);
        $others = $this->rate(15 * $count - $leastHits, $count * (25 - $leastSize));

        $next = NumberIndication::whereNull('evaluated_at')->orderByDesc('target_contest_number')->first();

        return [
            'evaluated_contests' => $count,
            'first_contest' => $evaluated->min('target_contest_number'),
            'last_contest' => $evaluated->max('target_contest_number'),
            'least' => $least,
            'most' => $most,
            'others' => $others,
            'expected_rate' => self::EXPECTED_HIT_RATE,
            'verdict' => $this->verdict($count, $least),
            'next' => $next ? [
                'target_contest_number' => $next->target_contest_number,
                'most_indicated' => $next->most_indicated,
                'least_indicated' => $next->least_indicated,
            ] : null,
        ];
    }

    /**
     * @return array{hits: int, total: int, rate: float|null, margin: float|null}
     */
    protected function rate(int $hits, int $total): array
    {
        if ($total === 0) {
            return ['hits' => 0, 'total' => 0, 'rate' => null, 'margin' => null];
        }

        $rate = $hits / $total;

        return [
            'hits' => $hits,
            'total' => $total,
            'rate' => $rate,
            // Margem de 95% (aproximação normal)
            'margin' => 1.96 * sqrt($rate * (1 - $rate) / $total),
        ];
    }

    /**
     * @param  array{hits: int, total: int, rate: float|null, margin: float|null}  $least
     */
    protected function verdict(int $evaluatedContests, array $least): string
    {
        if ($evaluatedContests < self::MIN_EVALUATED_CONTESTS) {
            return sprintf(
                'Amostra ainda pequena (%d de %d concursos conferidos). Qualquer diferença até aqui pode ser acaso.',
                $evaluatedContests,
                self::MIN_EVALUATED_CONTESTS
            );
        }

        $upperBound = $least['rate'] + $least['margin'];

        if ($upperBound < self::EXPECTED_HIT_RATE) {
            return 'As dezenas menos indicadas estão saindo abaixo dos 60% esperados, e a diferença já é maior que a margem de erro.';
        }

        return 'As dezenas menos indicadas estão saindo dentro do esperado para qualquer dezena (60%). Por enquanto, o critério não mostra efeito.';
    }
}
