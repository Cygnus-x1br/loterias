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
        $this->assertEquals(3.00, $this->service->getBetCost(15));
        $this->assertEquals(48.00, $this->service->getBetCost(16));
        $this->assertEquals(408.00, $this->service->getBetCost(17));
        $this->assertEquals(2448.00, $this->service->getBetCost(18));
        $this->assertEquals(11628.00, $this->service->getBetCost(19));
        $this->assertEquals(46512.00, $this->service->getBetCost(20));
        $this->assertEquals(0.00, $this->service->getBetCost(21));
    }

    public function test_get_fixed_prize_amount_returns_correct_value(): void
    {
        $this->assertEquals(6.00, $this->service->getFixedPrizeAmount(11));
        $this->assertEquals(12.00, $this->service->getFixedPrizeAmount(12));
        $this->assertEquals(30.00, $this->service->getFixedPrizeAmount(13));
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
        ];

        // 1. Simples (15 dezenas, acerto de 15) => R$ 1.000.000,00
        $total = $this->service->calculateTotalPrizeAmount(15, 15, $payouts);
        $this->assertEquals(1000000.00, $total);

        // 2. Simples (15 dezenas, acerto de 13) => R$ 30,00
        $total = $this->service->calculateTotalPrizeAmount(15, 13, $payouts);
        $this->assertEquals(30.00, $total);

        // 3. Múltipla (16 dezenas, acerto de 14) => 2 prêmios de 14 e 14 prêmios de 13
        // 2 * 1500 + 14 * 30 = 3000 + 420 = R$ 3.420,00
        $total = $this->service->calculateTotalPrizeAmount(16, 14, $payouts);
        $this->assertEquals(3420.00, $total);

        // 4. Múltipla (18 dezenas, acerto de 12) => 20 de 12 (12 * 20 = 240) e 200 de 11 (200 * 6 = 1200) = R$ 1.440,00
        $total = $this->service->calculateTotalPrizeAmount(18, 12, $payouts);
        $this->assertEquals(1440.00, $total);
    }
}
