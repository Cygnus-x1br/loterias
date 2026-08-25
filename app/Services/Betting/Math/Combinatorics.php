<?php

namespace App\Services\Betting\Math;

use Generator;

class Combinatorics
{
    /**
     * Gera todas as combinações (subconjuntos) de tamanho $k do array $set.
     * Retorna um Generator para não sobrecarregar a memória.
     *
     * @param  array  $set  O conjunto de elementos.
     * @param  int  $k  O tamanho de cada combinação.
     * @return Generator<array>
     */
    public static function combinations(array $set, int $k): Generator
    {
        $n = count($set);

        if ($k === 0) {
            yield [];

            return;
        }

        if ($n < $k) {
            return;
        }

        if ($n === $k) {
            yield $set;

            return;
        }

        // Algoritmo iterativo lexicográfico para combinações

        $indices = range(0, $k - 1);

        while (true) {
            $combination = [];
            foreach ($indices as $i) {
                $combination[] = $set[$i];
            }
            yield $combination;

            // Encontrar o índice a incrementar
            $i = $k - 1;
            while ($i >= 0 && $indices[$i] === $n - $k + $i) {
                $i--;
            }

            if ($i < 0) {
                break; // Todas as combinações geradas
            }

            $indices[$i]++;
            for ($j = $i + 1; $j < $k; $j++) {
                $indices[$j] = $indices[$j - 1] + 1;
            }
        }
    }

    /**
     * Calcula o número total de combinações (C(n, k)).
     *
     * @param  int  $n  Tamanho do conjunto.
     * @param  int  $k  Tamanho do subconjunto.
     */
    public static function countCombinations(int $n, int $k): int
    {
        if ($k < 0 || $k > $n) {
            return 0;
        }
        if ($k === 0 || $k === $n) {
            return 1;
        }

        // Optimization: C(n, k) == C(n, n - k)
        if ($k > $n / 2) {
            $k = $n - $k;
        }

        $c = 1;
        for ($i = 1; $i <= $k; $i++) {
            $c = $c * ($n - $i + 1) / $i;
        }

        return (int) round($c);
    }
}
