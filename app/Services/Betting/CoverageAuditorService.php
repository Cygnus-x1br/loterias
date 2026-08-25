<?php

namespace App\Services\Betting;

use App\Services\Betting\Math\Combinatorics;

class CoverageAuditorService
{
    /**
     * Mede a cobertura combinatória de um conjunto de apostas em relação a um grupo-base.
     * Retorna o "ResultadoValidacao" conforme Item 4.5 e Item 20.
     *
     * @param  array  $baseNumbers  Grupo-base selecionado (ex: 18 dezenas).
     * @param  array  $bets  Lista de apostas (cada uma com 15 dezenas).
     * @param  int  $guaranteePoints  Pontos que se deseja garantir (ex: 14).
     * @param  int  $guaranteeHits  Condição de acerto no grupo-base (ex: 15).
     */
    public function auditCoverage(
        array $baseNumbers,
        array $bets,
        int $guaranteePoints = 14,
        int $guaranteeHits = 15
    ): array {
        sort($baseNumbers);

        $scenariosCount = Combinatorics::countCombinations(count($baseNumbers), $guaranteeHits);

        // Converter apostas para bitmask para cálculo rápido
        $betMasks = [];
        foreach ($bets as $bet) {
            $betMasks[] = $this->arrayToBitmask($bet);
        }

        $coveredCount11 = 0;
        $coveredCount12 = 0;
        $coveredCount13 = 0;
        $coveredCount14 = 0;
        $coveredCount15 = 0;

        // Iterar sobre todos os cenários possíveis do grupo base
        foreach (Combinatorics::combinations($baseNumbers, $guaranteeHits) as $scenario) {
            $scenarioMask = $this->arrayToBitmask($scenario);

            $maxHitsInScenario = 0;
            foreach ($betMasks as $betMask) {
                $hits = $this->countBits($betMask & $scenarioMask);
                if ($hits > $maxHitsInScenario) {
                    $maxHitsInScenario = $hits;
                }

                if ($maxHitsInScenario === 15) {
                    break; // Já atingiu o máximo possível, não precisa verificar outras apostas para esse cenário
                }
            }

            if ($maxHitsInScenario >= 11) {
                $coveredCount11++;
            }
            if ($maxHitsInScenario >= 12) {
                $coveredCount12++;
            }
            if ($maxHitsInScenario >= 13) {
                $coveredCount13++;
            }
            if ($maxHitsInScenario >= 14) {
                $coveredCount14++;
            }
            if ($maxHitsInScenario === 15) {
                $coveredCount15++;
            }
        }

        // Variável dinâmica com base na garantia solicitada
        $targetCoverage = match ($guaranteePoints) {
            11 => $coveredCount11,
            12 => $coveredCount12,
            13 => $coveredCount13,
            14 => $coveredCount14,
            15 => $coveredCount15,
            default => 0,
        };

        return [
            'quantidade_cenarios_analisados' => $scenariosCount,
            'cobertura_por_faixa' => [
                '11_acertos' => $this->percentage($coveredCount11, $scenariosCount),
                '12_acertos' => $this->percentage($coveredCount12, $scenariosCount),
                '13_acertos' => $this->percentage($coveredCount13, $scenariosCount),
                '14_acertos' => $this->percentage($coveredCount14, $scenariosCount),
                '15_acertos' => $this->percentage($coveredCount15, $scenariosCount),
            ],
            'garantia_confirmada' => $targetCoverage === $scenariosCount,
            'condicoes_da_garantia' => "Garante {$guaranteePoints} acertos se acertar {$guaranteeHits} dezenas no grupo-base de ".count($baseNumbers).' dezenas.',
        ];
    }

    private function percentage(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 2);
    }

    private function arrayToBitmask(array $numbers): int
    {
        $mask = 0;
        foreach ($numbers as $num) {
            $mask |= (1 << ($num - 1));
        }

        return $mask;
    }

    private function countBits(int $n): int
    {
        $count = 0;
        while ($n) {
            $n &= ($n - 1);
            $count++;
        }

        return $count;
    }
}
