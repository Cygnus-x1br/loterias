<?php

namespace Tests\Unit\Services;

use App\Services\LotteryPrizeCalculatorService;
use PHPUnit\Framework\TestCase;

class LotteryPrizeCalculatorServiceTest extends TestCase
{
    private LotteryPrizeCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LotteryPrizeCalculatorService;
    }

    public function test_get_bet_cost_returns_correct_value(): void
    {
        $this->assertEquals(3.50, $this->service->getBetCost(15));
        $this->assertEquals(56.00, $this->service->getBetCost(16));
        $this->assertEquals(476.00, $this->service->getBetCost(17));
        $this->assertEquals(2856.00, $this->service->getBetCost(18));
        $this->assertEquals(13566.00, $this->service->getBetCost(19));
        $this->assertEquals(54264.00, $this->service->getBetCost(20));
        $this->assertEquals(0.00, $this->service->getBetCost(21));
    }

    public function test_get_fixed_prize_amount_returns_correct_value(): void
    {
        $this->assertEquals(7.00, $this->service->getFixedPrizeAmount(11));
        $this->assertEquals(14.00, $this->service->getFixedPrizeAmount(12));
        $this->assertEquals(35.00, $this->service->getFixedPrizeAmount(13));
        $this->assertEquals(0.00, $this->service->getFixedPrizeAmount(14));
    }

    public function test_calculate_prizes_for_simple_bet(): void
    {
        // Jogo de 15 dezenas marcadas, acerto de 15 pontos. (Deve retornar 1 prêmio de 15)
        $prizes = $this->service->calculatePrizes(15, 15);
        $this->assertCount(1, $prizes);
        $this->assertEquals(1, $prizes[15]);

        // Jogo de 15 dezenas marcadas, acerto de 12 pontos.
        $prizes = $this->service->calculatePrizes(15, 12);
        $this->assertCount(1, $prizes);
        $this->assertEquals(1, $prizes[12]);
    }

    public function test_calculate_prizes_for_multiple_bets(): void
    {
        // Jogo de 16 dezenas, acerto de 15 pontos: (1 prêmio 15, 15 prêmios de 14)
        $prizes = $this->service->calculatePrizes(16, 15);
        $this->assertEquals(1, $prizes[15]);
        $this->assertEquals(15, $prizes[14]);

        // Jogo de 18 dezenas, acerto de 14 pontos: (4 de 14, 84 de 13, 364 de 12, 364 de 11)
        $prizes = $this->service->calculatePrizes(18, 14);
        $this->assertEquals(4, $prizes[14]);
        $this->assertEquals(84, $prizes[13]);
        $this->assertEquals(364, $prizes[12]);
        $this->assertEquals(364, $prizes[11]);
    }

    public function test_calculate_total_prize_amount(): void
    {
        $payouts = [
            'payout_15_hits' => 1000000.00,
            'payout_14_hits' => 1500.00,
            'payout_13_hits' => 35.00,
            'payout_12_hits' => 14.00,
            'payout_11_hits' => 7.00,
        ];

        // 1. Simples (15 dezenas, acerto de 15) => R$ 1.000.000,00
        $total = $this->service->calculateTotalPrizeAmount(15, 15, $payouts);
        $this->assertEquals(1000000.00, $total);

        // 2. Simples (15 dezenas, acerto de 13) => R$ 35,00
        $total = $this->service->calculateTotalPrizeAmount(15, 13, $payouts);
        $this->assertEquals(35.00, $total);

        // 3. Múltipla (16 dezenas, acerto de 14) => 2 prêmios de 14 e 14 prêmios de 13
        // 2 * 1500 + 14 * 35 = 3000 + 490 = R$ 3.490,00
        $total = $this->service->calculateTotalPrizeAmount(16, 14, $payouts);
        $this->assertEquals(3490.00, $total);

        // 4. Múltipla (18 dezenas, acerto de 12) => 20 de 12 (14 * 20 = 280) e 180 de 11 (180 * 7 = 1260) = R$ 1.540,00
        $total = $this->service->calculateTotalPrizeAmount(18, 12, $payouts);
        $this->assertEquals(1540.00, $total);
    }

    public function test_calculate_prizes_for_twelve_hits_on_large_bets(): void
    {
        // C(12,11) · C(m − 12, 4): 18 → 12·15 = 180, 19 → 12·35 = 420, 20 → 12·70 = 840
        $this->assertSame([12 => 20, 11 => 180], $this->service->calculatePrizes(18, 12));
        $this->assertSame([12 => 35, 11 => 420], $this->service->calculatePrizes(19, 12));
        $this->assertSame([12 => 56, 11 => 840], $this->service->calculatePrizes(20, 12));
    }

    public function test_prizes_add_up_to_simple_bets_with_eleven_or_more_hits(): void
    {
        foreach (range(15, 20) as $betSize) {
            foreach (range(11, 15) as $hits) {
                $simpleBetsWithPrize = 0;

                // Enumera todas as apostas simples de 15 contidas na aposta e conta as premiadas
                $marked = range(1, $betSize);
                $drawnInsideBet = array_flip(range(1, $hits));
                foreach ($this->combinations($marked, 15) as $simpleBet) {
                    $matches = count(array_filter($simpleBet, fn (int $n): bool => isset($drawnInsideBet[$n])));
                    if ($matches >= 11) {
                        $simpleBetsWithPrize++;
                    }
                }

                $this->assertSame(
                    $simpleBetsWithPrize,
                    array_sum($this->service->calculatePrizes($betSize, $hits)),
                    "Aposta de {$betSize} dezenas com {$hits} acertos"
                );
            }
        }
    }

    public function test_calculate_prizes_returns_empty_for_invalid_input(): void
    {
        $this->assertSame([], $this->service->calculatePrizes(15, 10));
        $this->assertSame([], $this->service->calculatePrizes(21, 15));
        $this->assertSame([], $this->service->calculatePrizes(15, 16));
    }

    /**
     * @param  array<int, int>  $set
     * @return \Generator<int, array<int, int>>
     */
    private function combinations(array $set, int $size, int $offset = 0, array $current = []): \Generator
    {
        if (count($current) === $size) {
            yield $current;

            return;
        }

        for ($i = $offset; $i <= count($set) - ($size - count($current)); $i++) {
            yield from $this->combinations($set, $size, $i + 1, [...$current, $set[$i]]);
        }
    }
}
