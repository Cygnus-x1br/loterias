<?php

namespace App\Services;

use App\Models\Closing;

class MonteCarloSimulationService
{
    public function __construct(
        private LotteryPrizeCalculatorService $prizeCalculator
    ) {}

    /**
     * Executa o método de Monte Carlo gerando $numberOfSimulations concursos fictícios 
     * e testando contra os volantes do Fechamento passado.
     */
    public function runSimulation(Closing $closing, int $numberOfSimulations = 10000): array
    {
        $bets = $closing->bets;
        $betSize = $closing->bet_size;

        $totalCost = 0.0;
        $totalPrizesAmount = 0.0;
        
        $hitsDistribution = [
            15 => 0, 14 => 0, 13 => 0, 12 => 0, 11 => 0, 'less' => 0
        ];

        // Calcular custo das apostas
        $costPerBet = $this->prizeCalculator->getBetCost($betSize);
        $totalCost = $costPerBet * $bets->count() * $numberOfSimulations;

        // Extrai as matrizes de números do fechamento
        $betNumbersArray = [];
        foreach ($bets as $bet) {
            $nums = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $betNumbersArray[] = array_map('intval', $nums);
        }

        // Payouts estimativos fixos para Monte Carlo
        $simulatedPayouts = [
            'payout_15_hits' => 1200000.00, // 1.2 Milhão estimado para 15 pontos
            'payout_14_hits' => 1500.00,    // 1500 Reais estimado para 14 pontos
        ];

        // Gera os resultados (Sorteios aleatórios)
        for ($i = 0; $i < $numberOfSimulations; $i++) {
            $drawnNumbers = $this->generateRandomDraw();

            foreach ($betNumbersArray as $betNums) {
                $hits = count(array_intersect($betNums, $drawnNumbers));
                
                if ($hits >= 11) {
                    $hitsDistribution[$hits]++;
                    
                    $prize = $this->prizeCalculator->calculateTotalPrizeAmount($betSize, $hits, $simulatedPayouts);
                    $totalPrizesAmount += $prize;
                } else {
                    $hitsDistribution['less']++;
                }
            }
        }

        return [
            'simulations_run' => $numberOfSimulations,
            'total_bets_played' => $bets->count() * $numberOfSimulations,
            'total_cost' => $totalCost,
            'total_prizes' => $totalPrizesAmount,
            'profit' => $totalPrizesAmount - $totalCost,
            'roi_percentage' => $totalCost > 0 ? (($totalPrizesAmount - $totalCost) / $totalCost) * 100 : 0,
            'distribution' => $hitsDistribution,
            'closing_name' => $closing->name,
            'bet_size' => $betSize,
            'assumed_15_payout' => $simulatedPayouts['payout_15_hits'],
            'assumed_14_payout' => $simulatedPayouts['payout_14_hits'],
        ];
    }

    /**
     * Gera um volante vencedor hipotético (15 dezenas entre 1 e 25, sem repetição)
     */
    private function generateRandomDraw(): array
    {
        $pool = range(1, 25);
        shuffle($pool);
        $drawn = array_slice($pool, 0, 15);
        sort($drawn);
        
        return $drawn;
    }
}
