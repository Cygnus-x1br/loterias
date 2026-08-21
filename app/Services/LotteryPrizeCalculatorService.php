<?php

namespace App\Services;

class LotteryPrizeCalculatorService
{
    /**
     * Retorna o custo em Reais (R$) para uma aposta com o tamanho dado de dezenas.
     * Valores referentes à tabela atualizada da Lotofácil (base: R$ 3,00 para 15 dezenas).
     */
    public function getBetCost(int $betSize): float
    {
        return match ($betSize) {
            15 => 3.00,
            16 => 48.00,
            17 => 408.00,
            18 => 2448.00,
            19 => 11628.00,
            20 => 46512.00,
            default => 0.00,
        };
    }

    /**
     * Retorna o valor fixo dos prêmios de 11, 12 e 13 acertos.
     */
    public function getFixedPrizeAmount(int $hits): float
    {
        return match ($hits) {
            11 => 6.00,
            12 => 12.00,
            13 => 30.00,
            default => 0.00,
        };
    }

    /**
     * Retorna a matriz de premiações múltiplas (tabela de equivalência).
     * Retorna o array de quantos prêmios de cada categoria o jogador leva
     * dado o $betSize (15 a 20 dezenas marcadas no bilhete) e $hits (dezenas acertadas).
     *
     * As chaves do array retornado correspondem à categoria de prêmio (15 a 11 pontos).
     * Ex: 16 dezenas marcadas e acerto de 15 -> 1 prêmio de 15, 15 de 14.
     */
    public function calculatePrizes(int $betSize, int $hits): array
    {
        if ($hits < 11 || $betSize < 15 || $betSize > 20) {
            return [];
        }

        // Matriz de prêmios: [betSize][hits] = [categoria => quantidade]
        $matrix = [
            15 => [
                15 => [15 => 1],
                14 => [14 => 1],
                13 => [13 => 1],
                12 => [12 => 1],
                11 => [11 => 1],
            ],
            16 => [
                15 => [15 => 1, 14 => 15],
                14 => [14 => 2, 13 => 14],
                13 => [13 => 3, 12 => 13],
                12 => [12 => 4, 11 => 12],
                11 => [11 => 5],
            ],
            17 => [
                15 => [15 => 1, 14 => 30, 13 => 105],
                14 => [14 => 3, 13 => 42, 12 => 91],
                13 => [13 => 6, 12 => 52, 11 => 78],
                12 => [12 => 10, 11 => 60],
                11 => [11 => 15],
            ],
            18 => [
                15 => [15 => 1, 14 => 45, 13 => 315, 12 => 455],
                14 => [14 => 4, 13 => 84, 12 => 364, 11 => 364],
                13 => [13 => 10, 12 => 130, 11 => 390],
                12 => [12 => 20, 11 => 200],
                11 => [11 => 35],
            ],
            19 => [
                15 => [15 => 1, 14 => 60, 13 => 630, 12 => 1820, 11 => 1365],
                14 => [14 => 5, 13 => 140, 12 => 910, 11 => 1820],
                13 => [13 => 15, 12 => 260, 11 => 1170],
                12 => [12 => 35, 11 => 460],
                11 => [11 => 70],
            ],
            20 => [
                15 => [15 => 1, 14 => 75, 13 => 1050, 12 => 4550, 11 => 6825],
                14 => [14 => 6, 13 => 210, 12 => 1820, 11 => 5460],
                13 => [13 => 21, 12 => 455, 11 => 2730],
                12 => [12 => 56, 11 => 910],
                11 => [11 => 126],
            ],
        ];

        return $matrix[$betSize][$hits] ?? [];
    }

    /**
     * Calcula o valor financeiro total de prêmios conquistados.
     * $payouts: um array associativo contendo os valores de rateio de 14 e 15 pontos do concurso específico.
     * Exemplo: ['payout_15_hits' => 1000000.00, 'payout_14_hits' => 1500.00]
     */
    public function calculateTotalPrizeAmount(int $betSize, int $hits, array $payouts = []): float
    {
        $prizes = $this->calculatePrizes($betSize, $hits);
        $totalAmount = 0.00;

        foreach ($prizes as $category => $quantity) {
            $amountPerCategory = match ($category) {
                15 => (float) ($payouts['payout_15_hits'] ?? 0),
                14 => (float) ($payouts['payout_14_hits'] ?? 0),
                13, 12, 11 => $this->getFixedPrizeAmount($category),
                default => 0.00,
            };

            $totalAmount += $amountPerCategory * $quantity;
        }

        return $totalAmount;
    }
}
