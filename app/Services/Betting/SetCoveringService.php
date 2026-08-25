<?php

namespace App\Services\Betting;

use App\Services\Betting\Math\Combinatorics;

class SetCoveringService
{
    /**
     * Gera um fechamento reduzido usando Heurística Gulosa (Set Covering).
     *
     * @param  array  $baseNumbers  As dezenas selecionadas pelo usuário (grupo-base).
     * @param  int  $betSize  O tamanho de cada aposta (ex: 15).
     * @param  int  $guaranteePoints  Quantidade de acertos que queremos garantir (ex: 14).
     * @param  int  $guaranteeHits  Condição: "se acertar X no grupo-base" (ex: 15).
     * @param  int|null  $budget  Limite máximo de apostas (se nulo, vai até 100% de cobertura ou memória estourar).
     * @return array{
     *     bets: array,
     *     scenarios_covered: int,
     *     total_scenarios: int,
     *     coverage_percentage: float
     * }
     */
    public function generateReducedWheel(
        array $baseNumbers,
        int $betSize = 15,
        int $guaranteePoints = 14,
        int $guaranteeHits = 15,
        ?int $budget = null
    ): array {
        // Trava de segurança solicitada (limite 23)
        if (count($baseNumbers) > 23) {
            throw new \InvalidArgumentException('O número máximo suportado de dezenas para o fechamento síncrono é 23.');
        }

        if (count($baseNumbers) < $betSize) {
            throw new \InvalidArgumentException('O grupo base deve ser maior ou igual ao tamanho da aposta.');
        }

        sort($baseNumbers);

        // 1. Gerar Universo de Cenários (U)
        // O universo são todas as combinações de tamanho $guaranteeHits do $baseNumbers
        // Para cada cenário, nós transformamos os números em uma string ou mantemos o array.
        // Como performance é crítica, vamos usar bitmasks para representar conjuntos, pois base <= 25.
        // Um inteiro de 32 bits (PHP usa 64 bits na maioria) pode armazenar 25 bits perfeitamente.

        $scenarios = [];
        foreach (Combinatorics::combinations($baseNumbers, $guaranteeHits) as $scenario) {
            $scenarios[] = $this->arrayToBitmask($scenario);
        }
        $totalScenarios = count($scenarios);

        // Mantém controle de quais cenários já foram cobertos.
        // True = coberto, false = não coberto
        $coveredStatus = array_fill(0, $totalScenarios, false);
        $coveredCount = 0;

        // 2. Gerar Apostas Candidatas (A)
        // Todas as combinações de tamanho $betSize do $baseNumbers
        $candidates = [];
        foreach (Combinatorics::combinations($baseNumbers, $betSize) as $candidate) {
            $candidates[] = [
                'numbers' => $candidate,
                'mask' => $this->arrayToBitmask($candidate),
            ];
        }

        $selectedBets = [];

        // 3. Heurística Gulosa
        // Enquanto não cobrir todos e não atingir o orçamento
        $loopCount = 0;
        while ($coveredCount < $totalScenarios && ($budget === null || count($selectedBets) < $budget)) {
            $bestCandidate = null;
            $bestCandidateScore = -1;
            $bestCandidateCovers = []; // Índices dos cenários que o melhor candidato cobre

            // Avaliar cada candidato
            foreach ($candidates as $candidateIndex => $candidate) {
                // Quantos cenários NÃO COBERTOS este candidato cobre?
                $currentScore = 0;
                $covers = [];

                foreach ($scenarios as $sIndex => $scenarioMask) {
                    if (! $coveredStatus[$sIndex]) {
                        // Uma aposta cobre um cenário se a interseção entre eles for >= guaranteePoints
                        // Usamos AND bit a bit para pegar a interseção, depois contamos os bits.
                        $intersection = $candidate['mask'] & $scenarioMask;
                        // Em PHP não tem popcount nativo rápido até PHP 8.1+ talvez? Vamos fazer manual mas otimizado
                        $intersectionCount = $this->countBits($intersection);

                        if ($intersectionCount >= $guaranteePoints) {
                            $currentScore++;
                            $covers[] = $sIndex;
                        }
                    }
                }

                if ($currentScore > $bestCandidateScore) {
                    $bestCandidateScore = $currentScore;
                    $bestCandidate = $candidate;
                    $bestCandidateCovers = $covers;
                }
            }

            // Se o score for 0, nenhuma aposta a mais pode cobrir cenários novos. Parar.
            if ($bestCandidateScore === 0) {
                break;
            }

            // Adiciona a aposta
            $selectedBets[] = $bestCandidate['numbers'];

            // Marca os cenários como cobertos
            foreach ($bestCandidateCovers as $cIndex) {
                $coveredStatus[$cIndex] = true;
            }

            $coveredCount += $bestCandidateScore;

            // Remove o candidato escolhido para não testá-lo novamente? (Neste loop guloso ingênuo ele já pontuaria 0 de qualquer forma,
            // mas remover da array poupa loops futuros, embora array_splice no meio da array de PHP não seja o mais rápido, vamos deixar sem).
            $loopCount++;
        }

        return [
            'bets' => $selectedBets,
            'scenarios_covered' => $coveredCount,
            'total_scenarios' => $totalScenarios,
            'coverage_percentage' => $totalScenarios > 0 ? round(($coveredCount / $totalScenarios) * 100, 2) : 100,
        ];
    }

    /**
     * Converte um array de inteiros (1 a 25) para uma bitmask.
     */
    private function arrayToBitmask(array $numbers): int
    {
        $mask = 0;
        foreach ($numbers as $num) {
            $mask |= (1 << ($num - 1));
        }

        return $mask;
    }

    /**
     * Conta quantos bits '1' tem no número inteiro. (Popcount)
     */
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
