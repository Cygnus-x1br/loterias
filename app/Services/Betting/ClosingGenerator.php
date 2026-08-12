<?php

namespace App\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

class ClosingGenerator
{
    /**
     * Executa a geração de apostas de um fechamento.
     *
     * Retorna a quantidade de apostas criadas.
     *
     * @throws Throwable
     */
    public function generate(Closing $closing): int
    {
        try {
            $this->validateClosing($closing);

            if ($closing->method !== 'integral') {
                throw new LogicException(
                    "O método '{$closing->method}' ainda não possui um gerador implementado."
                );
            }

            return DB::transaction(function () use ($closing): int {
                $closing->update([
                    'status' => 'processing',
                ]);

                $createdBets = 0;

                foreach (
                    $this->combinations(
                        $closing->base_numbers,
                        $closing->bet_size
                    ) as $combination
                ) {
                    if (
                        $closing->planned_bets !== null
                        && $createdBets >= $closing->planned_bets
                    ) {
                        break;
                    }

                    Bet::create([
                        'user_id' => $closing->user_id,
                        'closing_id' => $closing->id,
                        'name' => sprintf(
                            '%s - Aposta %d',
                            $closing->name,
                            $createdBets + 1
                        ),
                        'numbers' => $combination,
                        'source' => 'closing',
                        'method' => $closing->method,
                        'status' => 'active',
                        'notes' => null,
                    ]);

                    $createdBets++;
                }

                if ($createdBets === 0) {
                    throw new LogicException(
                        'Nenhuma aposta foi gerada para este fechamento.'
                    );
                }

                $closing->update([
                    'status' => 'completed',
                ]);

                return $createdBets;
            });
        } catch (Throwable $exception) {
            $closing->update([
                'status' => 'failed',
            ]);

            throw $exception;
        }
    }

    /**
     * Valida os parâmetros necessários para gerar o fechamento.
     */
    protected function validateClosing(Closing $closing): void
    {
        $baseNumbers = $closing->base_numbers ?? [];
        $betSize = (int) $closing->bet_size;

        if (! is_array($baseNumbers)) {
            throw new InvalidArgumentException(
                'O grupo-base do fechamento deve ser uma lista de dezenas.'
            );
        }

        if (count($baseNumbers) < 15 || count($baseNumbers) > 25) {
            throw new InvalidArgumentException(
                'O grupo-base deve conter entre 15 e 25 dezenas.'
            );
        }

        $normalizedNumbers = array_map(
            static fn ($number): int => (int) $number,
            $baseNumbers
        );

        if (count($normalizedNumbers) !== count(array_unique($normalizedNumbers))) {
            throw new InvalidArgumentException(
                'O grupo-base não pode conter dezenas repetidas.'
            );
        }

        foreach ($normalizedNumbers as $number) {
            if ($number < 1 || $number > 25) {
                throw new InvalidArgumentException(
                    'As dezenas do grupo-base devem estar entre 1 e 25.'
                );
            }
        }

        if ($betSize < 15 || $betSize > 25) {
            throw new InvalidArgumentException(
                'O tamanho da aposta deve estar entre 15 e 25 dezenas.'
            );
        }

        if ($betSize > count($normalizedNumbers)) {
            throw new InvalidArgumentException(
                'O tamanho da aposta não pode ser maior que o grupo-base.'
            );
        }

        if (
            $closing->planned_bets !== null
            && (int) $closing->planned_bets < 1
        ) {
            throw new InvalidArgumentException(
                'A quantidade planejada de apostas deve ser maior que zero.'
            );
        }

        sort($normalizedNumbers);

        $closing->base_numbers = $normalizedNumbers;
    }

    /**
     * Gera combinações de forma incremental para evitar carregar
     * todas as combinações na memória ao mesmo tempo.
     *
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

            yield from $this->combinations(
                $numbers,
                $size,
                $index + 1,
                $next
            );
        }
    }
}
