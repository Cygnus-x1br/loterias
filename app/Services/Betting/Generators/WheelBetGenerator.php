<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;
use InvalidArgumentException;
use LogicException;

class WheelBetGenerator implements BetGeneratorInterface
{
    /**
     * Valida os parâmetros específicos para o sistema de roda.
     *
     * @throws InvalidArgumentException
     */
    public function validate(Closing $closing): void
    {
        $parameters = $closing->parameters ?? [];
        $betSize = $closing->bet_size;
        $baseNumbers = $closing->base_numbers;

        if (! isset($closing->planned_bets) || (int) $closing->planned_bets < 1) {
            throw new InvalidArgumentException(
                'A quantidade planejada de apostas é obrigatória para o sistema de roda.'
            );
        }

        // Validação de dezenas fixas
        $fixedNumbers = $parameters['fixed_numbers'] ?? [];
        if (! is_array($fixedNumbers)) {
            throw new InvalidArgumentException('As dezenas fixas devem ser uma lista de números.');
        }
        foreach ($fixedNumbers as $number) {
            if (! in_array($number, $baseNumbers, true)) {
                throw new InvalidArgumentException("A dezena fixa {$number} não está no grupo-base.");
            }
        }
        if (count($fixedNumbers) !== count(array_unique($fixedNumbers))) {
            throw new InvalidArgumentException('As dezenas fixas não podem conter números repetidos.');
        }

        // Validação de dezenas variáveis
        $variableNumbers = $parameters['variable_numbers'] ?? [];
        if (! is_array($variableNumbers)) {
            throw new InvalidArgumentException('As dezenas variáveis devem ser uma lista de números.');
        }
        foreach ($variableNumbers as $number) {
            if (! in_array($number, $baseNumbers, true)) {
                throw new InvalidArgumentException("A dezena variável {$number} não está no grupo-base.");
            }
            if (in_array($number, $fixedNumbers, true)) {
                throw new InvalidArgumentException("A dezena variável {$number} também está nas dezenas fixas.");
            }
        }
        if (count($variableNumbers) !== count(array_unique($variableNumbers))) {
            throw new InvalidArgumentException('As dezenas variáveis não podem conter números repetidos.');
        }

        // Validação de wheel_size
        $wheelSize = $parameters['wheel_size'] ?? null;
        if (! is_int($wheelSize) || $wheelSize < 1 || $wheelSize > count($variableNumbers)) {
            throw new InvalidArgumentException('O tamanho da roda (wheel_size) deve ser um número válido entre 1 e o total de dezenas variáveis.');
        }

        // Validação da soma dos tamanhos
        if (count($fixedNumbers) + $wheelSize !== $betSize) {
            throw new InvalidArgumentException(
                "A soma das dezenas fixas (".count($fixedNumbers).") e o tamanho da roda ({$wheelSize}) deve ser igual ao tamanho da aposta ({$betSize})."
            );
        }
    }

    /**
     * Gera apostas usando o sistema de roda.
     *
     * @return \Generator<int, array<int>>
     * @throws LogicException
     */
    public function generate(Closing $closing): \Generator
    {
        $parameters = $closing->parameters ?? [];
        $fixedNumbers = $parameters['fixed_numbers'] ?? [];
        $variableNumbers = $parameters['variable_numbers'] ?? [];
        $wheelSize = $parameters['wheel_size'] ?? 0;
        $plannedBets = $closing->planned_bets;

        $generatedCount = 0;
        $uniqueBets = [];

        // Gerar combinações das dezenas variáveis
        $variableCombinations = $this->combinations($variableNumbers, $wheelSize);

        foreach ($variableCombinations as $variableCombination) {
            if ($generatedCount >= $plannedBets) {
                break;
            }

            $currentBet = array_merge($fixedNumbers, $variableCombination);
            sort($currentBet); // Garante ordem para comparação de unicidade

            if (in_array($currentBet, $uniqueBets, true)) {
                continue;
            }

            yield $currentBet;
            $uniqueBets[] = $currentBet;
            $generatedCount++;
        }

        if ($generatedCount < $plannedBets) {
            throw new LogicException(
                "Não foi possível gerar {$plannedBets} apostas únicas com os parâmetros fornecidos. Foram geradas {$generatedCount}."
            );
        }
    }

    /**
     * Gera combinações de forma incremental.
     *
     * @param array<int> $numbers
     * @return \Generator<int, array<int>>
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

        for ($i = $offset; $i <= $lastStart; $i++) {
            $current[] = $numbers[$i];
            yield from $this->combinations($numbers, $size, $i + 1, $current);
            array_pop($current);
        }
    }
}
