<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;
use App\Services\Betting\Generators\BetGeneratorInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

class ReducedBetGenerator implements BetGeneratorInterface
{
    // O construtor não precisa mais receber o Closing, pois ele será passado nos métodos.
    // public function __construct(Closing $closing)
    // {
    //     $this->closing = $closing;
    // }

    /**
     * Valida os parâmetros específicos para o fechamento reduzido.
     *
     * @param Closing $closing O objeto Closing contendo os parâmetros.
     * @throws InvalidArgumentException
     */
    public function validate(Closing $closing): void
    {
        $parameters = $closing->parameters;
        $baseNumbers = $closing->base_numbers;
        $betSize = $closing->bet_size;

        // Validação básica do grupo-base e tamanho da aposta
        if (empty($baseNumbers) || !is_array($baseNumbers) || count($baseNumbers) < $betSize) {
            throw new InvalidArgumentException('O grupo-base deve conter pelo menos o número de dezenas da aposta.');
        }
        if ($betSize < 15 || $betSize > 25) { // Considerando Lotofácil padrão de 15 a 20 dezenas por aposta, mas o sistema permite até 25
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
        if (!isset($parameters['reduced_parameters'])) {
            throw new InvalidArgumentException('Parâmetros de fechamento reduzido ausentes.');
        }

        $reducedParams = $parameters['reduced_parameters'];

        $guaranteeHits = $reducedParams['guarantee_hits'] ?? null;
        $guaranteePoints = $reducedParams['guarantee_points'] ?? null;

        if (!is_int($guaranteeHits) || $guaranteeHits < $betSize || $guaranteeHits > count($baseNumbers)) {
            throw new InvalidArgumentException(
                'O número de acertos na base para garantia (guarantee_hits) deve ser um inteiro, ' .
                'maior ou igual ao tamanho da aposta e menor ou igual ao número de dezenas no grupo-base.'
            );
        }

        if (!is_int($guaranteePoints) || $guaranteePoints < 11 || $guaranteePoints >= $betSize) {
            throw new InvalidArgumentException(
                'Os pontos garantidos (guarantee_points) devem ser um inteiro entre 11 e ' . ($betSize - 1) . '.'
            );
        }

        // Validação adicional: a garantia não pode ser maior ou igual ao tamanho da aposta
        // pois isso implicaria em um fechamento integral ou impossível de reduzir.
        if ($guaranteePoints >= $betSize) {
            throw new InvalidArgumentException('Os pontos garantidos devem ser menores que o tamanho da aposta.');
        }
    }

    /**
     * Gera as apostas para o fechamento reduzido.
     *
     * @param Closing $closing O objeto Closing contendo os parâmetros.
     * @return Collection<array<int>> Uma coleção de arrays, onde cada array representa uma aposta.
     * @throws LogicException Se a lógica de geração falhar ou não for implementada.
     */
    public function generate(Closing $closing): Collection
    {
        $this->validate($closing); // Garante que os parâmetros são válidos antes de gerar

        $baseNumbers = collect($closing->base_numbers)->sort()->values()->toArray();
        $betSize = $closing->bet_size;
        $reducedParams = $closing->parameters['reduced_parameters'];
        $guaranteeHits = $reducedParams['guarantee_hits'];
        $guaranteePoints = $reducedParams['guarantee_points'];

        // --- Lógica de Geração do Fechamento Reduzido ---
        // Esta é a parte mais complexa e central do algoritmo.
        // Por enquanto, vamos retornar um placeholder para que o sistema possa ser integrado.
        // A implementação real do algoritmo combinatório será feita na próxima etapa.

        $generatedBets = new Collection();

        // Exemplo de geração de uma aposta simples para demonstração (NÃO É O ALGORITMO REDUZIDO REAL)
        // A lógica real aqui será muito mais complexa e combinatória.
        if (count($baseNumbers) >= $betSize) {
            $generatedBets->push(array_slice($baseNumbers, 0, $betSize));
        } else {
            throw new LogicException('Não foi possível gerar apostas com o grupo-base fornecido.');
        }

        // TODO: Implementar o algoritmo combinatório real para fechamento reduzido aqui.
        // Isso pode envolver bibliotecas de combinação ou uma implementação manual complexa.
        // A complexidade reside em garantir a condição de "guarantee_points"
        // quando "guarantee_hits" dezenas do grupo-base são acertadas,
        // minimizando o número total de apostas.

        return $generatedBets;
    }
}
