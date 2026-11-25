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
     * Ao terminar, o gerador pode retornar (via `return`) um relatório da geração:
     * `bet_notes` (observação por aposta, indexada pelas dezenas ordenadas unidas por "-")
     * e `warnings` (avisos exibidos no fechamento).
     *
     * @return \Generator<int, array<int, int>, mixed, array{bet_notes?: array<string, string>, warnings?: array<int, string>}|null>
     */
    public function generate(Closing $closing): \Generator;
}
