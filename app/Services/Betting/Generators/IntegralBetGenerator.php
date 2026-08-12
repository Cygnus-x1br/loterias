<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;

class IntegralBetGenerator implements BetGeneratorInterface
{
    public function validate(Closing $closing): void
    {
        // As validações genéricas do ClosingGenerator já são
        // suficientes para o método integral.
    }

    /**
     * @return \Generator<int, array<int, int>>
     */
    public function generate(Closing $closing): \Generator
    {
        yield from $this->combinations(
            $closing->base_numbers,
            (int) $closing->bet_size
        );
    }

    /**
     * @param array<int, int> $numbers
     *
     * @return \Generator<int, array<int, int>>
     */
    protected function combinations(
        array $numbers,
        int $size,
        int $offset = 0,
        array $current = []
    ): \Generator {
        if (count($current) === $size) {
            yield array_values($current);

            return;
        }

        $remainingNeeded = $size - count($current);
        $lastStart = count($numbers) - $remainingNeeded;

        for ($index = $offset; $index <= $lastStart; $index++) {
            $next = $current;
            $next[] = (int) $numbers[$index];

            yield from $this->combinations($numbers, $size, $index + 1, $next);
        }
    }
}
