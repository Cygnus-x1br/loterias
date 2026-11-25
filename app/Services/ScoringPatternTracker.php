<?php

namespace App\Services;

/**
 * Contagem incremental dos padrões históricos usados pelo score:
 * dezenas, pares, trios e sequências consecutivas (pares, trios e quadras).
 *
 * Os sorteios podem ser somados (add) ou subtraídos (remove), o que permite obter
 * os padrões "antes do concurso N" sem recalcular a série inteira:
 * - em ordem cronológica, somando concurso a concurso (comando de recálculo);
 * - a partir da série completa, subtraindo os concursos de N em diante.
 */
class ScoringPatternTracker
{
    public const TOP_NUMBERS = 10;

    public const TOP_PAIRS = 10;

    public const TOP_TRIOS = 10;

    public const TOP_CONSECUTIVE_PAIRS = 8;

    public const TOP_CONSECUTIVE_TRIOS = 6;

    public const TOP_CONSECUTIVE_QUADS = 4;

    /**
     * @param  array<int, int>  $numbers  Dezena => frequência.
     * @param  array<int, int>  $pairs  Código a·100+b => frequência.
     * @param  array<int, int>  $trios  Código a·10⁴+b·100+c => frequência.
     * @param  array<int, int>  $quads  Primeira dezena de a, a+1, a+2, a+3 => frequência.
     */
    public function __construct(
        protected array $numbers = [],
        protected array $pairs = [],
        protected array $trios = [],
        protected array $quads = [],
        protected int $draws = 0
    ) {}

    /**
     * @param  array{numbers: array<int, int>, pairs: array<int, int>, trios: array<int, int>, quads: array<int, int>, draws: int}  $counts
     */
    public static function fromArray(array $counts): self
    {
        return new self($counts['numbers'], $counts['pairs'], $counts['trios'], $counts['quads'], $counts['draws']);
    }

    /**
     * @return array{numbers: array<int, int>, pairs: array<int, int>, trios: array<int, int>, quads: array<int, int>, draws: int}
     */
    public function toArray(): array
    {
        return [
            'numbers' => $this->numbers,
            'pairs' => $this->pairs,
            'trios' => $this->trios,
            'quads' => $this->quads,
            'draws' => $this->draws,
        ];
    }

    /**
     * @param  array<int, int|string>  $drawnNumbers
     */
    public function add(array $drawnNumbers): void
    {
        $this->apply($drawnNumbers, 1);
    }

    /**
     * @param  array<int, int|string>  $drawnNumbers
     */
    public function remove(array $drawnNumbers): void
    {
        $this->apply($drawnNumbers, -1);
    }

    public function draws(): int
    {
        return $this->draws;
    }

    /**
     * Padrões mais frequentes no estado atual da contagem.
     * Empates são decididos pela menor dezena/combinação, para o resultado ser determinístico.
     *
     * @return array{
     *     top_numbers: array<int, int>,
     *     top_pairs: array<int, string>,
     *     top_trios: array<int, string>,
     *     top_consecutive_pairs: array<int, string>,
     *     top_consecutive_trios: array<int, string>,
     *     top_consecutive_quads: array<int, string>
     * }
     */
    public function snapshot(): array
    {
        $consecutivePairs = array_filter($this->pairs, fn (int $code): bool => intdiv($code, 100) + 1 === $code % 100, ARRAY_FILTER_USE_KEY);
        $consecutiveTrios = array_filter(
            $this->trios,
            fn (int $code): bool => intdiv($code, 10000) + 1 === intdiv($code, 100) % 100 && intdiv($code, 10000) + 2 === $code % 100,
            ARRAY_FILTER_USE_KEY
        );

        return [
            'top_numbers' => $this->top($this->numbers, self::TOP_NUMBERS),
            'top_pairs' => array_map(fn (int $code): string => $this->formatCode($code, 2), $this->top($this->pairs, self::TOP_PAIRS)),
            'top_trios' => array_map(fn (int $code): string => $this->formatCode($code, 3), $this->top($this->trios, self::TOP_TRIOS)),
            'top_consecutive_pairs' => array_map(fn (int $code): string => $this->formatCode($code, 2), $this->top($consecutivePairs, self::TOP_CONSECUTIVE_PAIRS)),
            'top_consecutive_trios' => array_map(fn (int $code): string => $this->formatCode($code, 3), $this->top($consecutiveTrios, self::TOP_CONSECUTIVE_TRIOS)),
            'top_consecutive_quads' => array_map(
                fn (int $first): string => sprintf('%02d-%02d-%02d-%02d', $first, $first + 1, $first + 2, $first + 3),
                $this->top($this->quads, self::TOP_CONSECUTIVE_QUADS)
            ),
        ];
    }

    /**
     * @param  array<int, int|string>  $drawnNumbers
     */
    protected function apply(array $drawnNumbers, int $sign): void
    {
        $numbers = array_values(array_unique(array_map('intval', $drawnNumbers)));
        sort($numbers);
        $count = count($numbers);
        $isDrawn = array_flip($numbers);

        for ($i = 0; $i < $count; $i++) {
            $a = $numbers[$i];
            $this->numbers[$a] = ($this->numbers[$a] ?? 0) + $sign;

            for ($j = $i + 1; $j < $count; $j++) {
                $pairCode = $a * 100 + $numbers[$j];
                $this->pairs[$pairCode] = ($this->pairs[$pairCode] ?? 0) + $sign;

                for ($k = $j + 1; $k < $count; $k++) {
                    $trioCode = $pairCode * 100 + $numbers[$k];
                    $this->trios[$trioCode] = ($this->trios[$trioCode] ?? 0) + $sign;
                }
            }

            if (isset($isDrawn[$a + 1], $isDrawn[$a + 2], $isDrawn[$a + 3])) {
                $this->quads[$a] = ($this->quads[$a] ?? 0) + $sign;
            }
        }

        $this->draws += $sign;
    }

    /**
     * Códigos das $limit maiores frequências (frequência desc, código asc).
     *
     * @param  array<int, int>  $counts
     * @return array<int, int>
     */
    protected function top(array $counts, int $limit): array
    {
        $counts = array_filter($counts, fn (int $count): bool => $count > 0);

        if ($counts === []) {
            return [];
        }

        $codes = array_keys($counts);
        $frequencies = array_values($counts);
        array_multisort($frequencies, SORT_DESC, $codes, SORT_ASC);

        return array_slice($codes, 0, $limit);
    }

    protected function formatCode(int $code, int $parts): string
    {
        $numbers = [];
        for ($i = 0; $i < $parts; $i++) {
            array_unshift($numbers, sprintf('%02d', $code % 100));
            $code = intdiv($code, 100);
        }

        return implode('-', $numbers);
    }
}
