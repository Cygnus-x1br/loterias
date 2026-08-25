<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;
use App\Services\Betting\SetCoveringService;
use Generator;
use InvalidArgumentException;
use LogicException;

class ReducedBetGenerator implements BetGeneratorInterface
{
    /**
     * Valida os parâmetros específicos para o fechamento reduzido.
     *
     * @param  Closing  $closing  O objeto Closing contendo os parâmetros.
     *
     * @throws InvalidArgumentException
     */
    public function validate(Closing $closing): void
    {
        $parameters = $closing->parameters;
        $baseNumbers = $closing->base_numbers;
        $betSize = $closing->bet_size;

        // Validações gerais (algumas podem ser redundantes com StoreClosingRequest, mas é bom ter aqui também)
        if (! is_array($baseNumbers) || count($baseNumbers) < 15 || count($baseNumbers) > 25) {
            throw new InvalidArgumentException('O grupo-base deve conter entre 15 e 25 dezenas.');
        }
        if ($betSize < 15 || $betSize > 25) { // Lotofácil é 15 dezenas por aposta, mas o sistema permite até 25
            throw new InvalidArgumentException('O tamanho da aposta deve ser entre 15 e 25 dezenas.');
        }
        foreach ($baseNumbers as $number) {
            if ($number < 1 || $number > 25) {
                throw new InvalidArgumentException('Todas as dezenas do grupo-base devem estar entre 1 e 25.');
            }
        }
        if (count($baseNumbers) !== count(array_unique($baseNumbers))) {
            throw new InvalidArgumentException('O grupo-base não pode conter dezenas repetidas.');
        }

        // Validação dos parâmetros específicos do fechamento reduzido
        if (! isset($parameters['reduced_parameters'])) {
            throw new InvalidArgumentException('Parâmetros de fechamento reduzido ausentes.');
        }

        $reducedParams = $parameters['reduced_parameters'];

        $guaranteeHits = $reducedParams['guarantee_hits'] ?? null;
        $guaranteePoints = $reducedParams['guarantee_points'] ?? null;

        if (! is_int($guaranteeHits) || $guaranteeHits < $betSize || $guaranteeHits > count($baseNumbers)) {
            throw new InvalidArgumentException(
                'O número de acertos na base para garantia (guarantee_hits) deve ser um inteiro, '.
                'maior ou igual ao tamanho da aposta e menor ou igual ao número de dezenas no grupo-base.'
            );
        }

        if (! is_int($guaranteePoints) || $guaranteePoints < 11 || $guaranteePoints >= $betSize) {
            throw new InvalidArgumentException(
                'Os pontos garantidos (guarantee_points) devem ser um inteiro entre 11 e '.($betSize - 1).'.'
            );
        }

        // Validação adicional: a garantia não pode ser maior ou igual ao tamanho da aposta
        // pois isso implicaria em um fechamento integral ou impossível de reduzir.
        if ($guaranteePoints >= $betSize) {
            throw new InvalidArgumentException('Os pontos garantidos devem ser menores que o tamanho da aposta.');
        }
    }

    /**
     * Gera as apostas para o fechamento reduzido usando o SetCoveringService.
     *
     * @param  Closing  $closing  O objeto Closing contendo os parâmetros.
     * @return Generator Uma coleção de arrays, onde cada array representa uma aposta.
     *
     * @throws LogicException Se a lógica de geração falhar ou não for implementada.
     */
    public function generate(Closing $closing): Generator
    {
        $this->validate($closing);

        $baseNumbers = collect($closing->base_numbers)->sort()->values()->toArray();
        $betSize = $closing->bet_size;
        $reducedParams = $closing->parameters['reduced_parameters'];
        $guaranteeHits = $reducedParams['guarantee_hits'];
        $guaranteePoints = $reducedParams['guarantee_points'];

        $budget = $closing->planned_bets ?: null;

        $coveringService = app(SetCoveringService::class);

        $result = $coveringService->generateReducedWheel(
            baseNumbers: $baseNumbers,
            betSize: $betSize,
            guaranteePoints: $guaranteePoints,
            guaranteeHits: $guaranteeHits,
            budget: $budget
        );

        $count = 0;
        foreach ($result['bets'] as $bet) {
            yield $bet;
            $count++;
        }

        if ($count === 0) {
            throw new LogicException('Nenhuma aposta foi gerada com os parâmetros fornecidos ou com o tamanho de aposta correto.');
        }
    }
}
