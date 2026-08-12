<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;
use InvalidArgumentException;
use LogicException;

class RandomBetGenerator implements BetGeneratorInterface
{
    /**
     * Multiplicador usado para limitar as tentativas de geração
     * e evitar loops infinitos em cenários extremos.
     */
    protected const MAX_ATTEMPTS_MULTIPLIER = 30;

    public function validate(Closing $closing): void
    {
        if ($closing->planned_bets === null || (int) $closing->planned_bets < 1) {
            throw new InvalidArgumentException(
                'A quantidade planejada de apostas é obrigatória para o método aleatório.'
            );
        }

        $baseNumbers = $closing->base_numbers ?? [];
        $betSize = (int) $closing->bet_size;
        $plannedBets = (int) $closing->planned_bets;

        $maxCombinations = $this->combinationsCount(count($baseNumbers), $betSize);

        if ($plannedBets > $maxCombinations) {
            throw new InvalidArgumentException(sprintf(
                'Não é possível gerar %d apostas distintas com %d dezenas escolhendo %d por aposta. O máximo possível é %d.',
                $plannedBets,
                count($baseNumbers),
                $betSize,
                $maxCombinations
            ));
        }
    }

    /**
     * @return \Generator<int, array<int, int>>
     */
    public function generate(Closing $closing): \Generator
    {
        $baseNumbers = array_values($closing->base_numbers);
        $betSize = (int) $closing->bet_size;
        $plannedBets = (int) $closing->planned_bets;

        $generated = [];
        $attempts = 0;
        $maxAttempts = ($plannedBets * self::MAX_ATTEMPTS_MULTIPLIER) + 100;
        $count = 0;

        while ($count < $plannedBets) {
            $attempts++;

            if ($attempts > $maxAttempts) {
                throw new LogicException(
                    'Não foi possível gerar a quantidade solicitada de apostas distintas para este fechamento.'
                );
            }

            $combination = $baseNumbers;
            shuffle($combination);
            $combination = array_slice($combination, 0, $betSize);
            sort($combination);

            $key = implode('-', $combination);

            if (isset($generated[$key])) {
                continue;
            }

            $generated[$key] = true;
            $count++;

            yield $combination;
        }
    }

    protected function combinationsCount(int $n, int $k): int
    {
        if ($k < 0 || $k > $n || $n < 0) {
            return 0;
        }

        $k = min($k, $n - $k);
        $result = 1;

        for ($i = 1; $i <= $k; $i++) {
            $result = intdiv($result * ($n - $k + $i), $i);
        }

        return $result;
    }
}
