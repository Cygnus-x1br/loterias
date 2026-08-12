<?php

namespace App\Services\Betting\Generators;

use App\Models\Closing;

interface BetGeneratorInterface
{
    /**
     * Valida regras específicas do método, além das validações
     * genéricas já realizadas pelo ClosingGenerator.
     */
    public function validate(Closing $closing): void;

    /**
     * Gera as combinações de dezenas para o fechamento.
     *
     * @return \Generator<int, array<int, int>>
     */
    public function generate(Closing $closing): \Generator;
}
