<?php

namespace App\Services;

use App\Services\Betting\Math\Combinatorics;

class LotteryPrizeCalculatorService
{
    public function __construct(
        private ?LotofacilSettingService $settingService = null
    ) {
        $this->settingService = $settingService ?? app(LotofacilSettingService::class);
    }

    /**
     * Retorna o custo em Reais (R$) para uma aposta com o tamanho dado de dezenas,
     * consultando a tabela de configurações persistida no sistema.
     */
    public function getBetCost(int $betSize): float
    {
        if ($betSize < 15 || $betSize > 20) {
            return 0.00;
        }

        try {
            return $this->settingService->getPriceFor($betSize);
        } catch (\Throwable) {
            return match ($betSize) {
                15 => 3.50,
                16 => 56.00,
                17 => 476.00,
                18 => 2856.00,
                19 => 13566.00,
                20 => 54264.00,
                default => 0.00,
            };
        }
    }

    /**
     * Retorna o valor fixo dos prêmios de 11, 12 e 13 acertos (base: aposta R$ 3,50).
     */
    public function getFixedPrizeAmount(int $hits): float
    {
        return match ($hits) {
            11 => 7.00,
            12 => 14.00,
            13 => 35.00,
            default => 0.00,
        };
    }

    /**
     * Retorna quantos prêmios de cada categoria uma aposta múltipla recebe.
     *
     * Uma aposta com $betSize dezenas equivale a C($betSize, 15) apostas simples.
     * Se $hits dezenas marcadas foram sorteadas, a quantidade de apostas simples
     * com exatamente k acertos é C($hits, k) · C($betSize − $hits, 15 − k).
     * Ex: 16 dezenas marcadas e acerto de 15 -> 1 prêmio de 15, 15 de 14.
     *
     * @return array<int, int> Chave: categoria (15 a 11 pontos), valor: quantidade de prêmios.
     */
    public function calculatePrizes(int $betSize, int $hits): array
    {
        if ($hits < 11 || $hits > 15 || $betSize < 15 || $betSize > 20 || $hits > $betSize) {
            return [];
        }

        $prizes = [];

        for ($category = 15; $category >= 11; $category--) {
            $quantity = Combinatorics::countCombinations($hits, $category)
                * Combinatorics::countCombinations($betSize - $hits, 15 - $category);

            if ($quantity > 0) {
                $prizes[$category] = $quantity;
            }
        }

        return $prizes;
    }

    /**
     * Calcula o valor financeiro total de prêmios conquistados.
     * $payouts: um array associativo contendo os valores de rateio do concurso específico.
     * Exemplo: ['payout_15_hits' => 1000000.00, 'payout_14_hits' => 1500.00, 'payout_13_hits' => 35.00, ...]
     */
    public function calculateTotalPrizeAmount(int $betSize, int $hits, array $payouts = []): float
    {
        $prizes = $this->calculatePrizes($betSize, $hits);
        $totalAmount = 0.00;

        foreach ($prizes as $category => $quantity) {
            $amountPerCategory = match ($category) {
                15 => ! empty($payouts['payout_15_hits']) && (float) $payouts['payout_15_hits'] > 0
                    ? (float) $payouts['payout_15_hits']
                    : 1500000.00,
                14 => ! empty($payouts['payout_14_hits']) && (float) $payouts['payout_14_hits'] > 0
                    ? (float) $payouts['payout_14_hits']
                    : 1500.00,
                13 => ! empty($payouts['payout_13_hits']) && (float) $payouts['payout_13_hits'] > 0
                    ? (float) $payouts['payout_13_hits']
                    : $this->getFixedPrizeAmount(13),
                12 => ! empty($payouts['payout_12_hits']) && (float) $payouts['payout_12_hits'] > 0
                    ? (float) $payouts['payout_12_hits']
                    : $this->getFixedPrizeAmount(12),
                11 => ! empty($payouts['payout_11_hits']) && (float) $payouts['payout_11_hits'] > 0
                    ? (float) $payouts['payout_11_hits']
                    : $this->getFixedPrizeAmount(11),
                default => 0.00,
            };

            $totalAmount += $amountPerCategory * $quantity;
        }

        return $totalAmount;
    }
}
