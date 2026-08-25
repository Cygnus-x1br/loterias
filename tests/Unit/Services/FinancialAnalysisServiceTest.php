<?php

namespace Tests\Unit\Services;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\FinancialAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialAnalysisService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialAnalysisService::class);
        $this->user = User::factory()->create();
    }

    public function test_only_placed_and_checked_bets_are_counted_in_overall_summary(): void
    {
        // 1. Aposta active (não apostada) -> não deve ser contabilizada
        Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta Ativa',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        // 2. Aposta placed (apostada) -> R$ 3,50 gasto, 0 ganho
        Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta Apostada',
            'numbers' => range(1, 15),
            'status' => 'placed',
            'contest_number' => 3300,
        ]);

        // 3. Aposta checked com 11 acertos -> R$ 3,50 gasto, R$ 7,00 ganho
        Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta Conferida',
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => 3300,
            'hits' => 11,
        ]);

        $summary = $this->service->getOverallSummary($this->user->id);

        $this->assertEquals(2, $summary['total_bets']);
        $this->assertEquals(1, $summary['placed_bets']);
        $this->assertEquals(1, $summary['checked_bets']);
        $this->assertEquals(7.00, $summary['total_spent']); // 2 * 3.50
        $this->assertEquals(7.00, $summary['total_return']); // 1x 11 hits (7.00)
        $this->assertEquals(0.00, $summary['net_profit']);
        $this->assertEquals(0.00, $summary['roi']);
        $this->assertEquals(1, $summary['awarded_bets']);
        $this->assertEquals(50.0, $summary['win_rate']);
    }

    public function test_overall_summary_profit_and_roi_calculation(): void
    {
        // 1 aposta de 15 dezenas com 13 acertos (R$ 35,00 de prêmio)
        Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta 13 acertos',
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => 3301,
            'hits' => 13,
        ]);

        $summary = $this->service->getOverallSummary($this->user->id);

        $this->assertEquals(3.50, $summary['total_spent']);
        $this->assertEquals(35.00, $summary['total_return']);
        $this->assertEquals(31.50, $summary['net_profit']);
        $this->assertTrue($summary['is_profit']);
        // ROI = (35 - 3.5) / 3.5 * 100 = 900%
        $this->assertEquals(900.0, $summary['roi']);
    }

    public function test_contests_breakdown_aggregates_multiple_closings_and_individual_bets(): void
    {
        $contestNumber = 3302;

        HistoricalResult::create([
            'contest_number' => $contestNumber,
            'draw_date' => '2026-08-20',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
            'payout_11_hits' => 7.00,
            'payout_12_hits' => 14.00,
            'payout_13_hits' => 35.00,
            'payout_14_hits' => 1500.00,
            'payout_15_hits' => 1500000.00,
        ]);

        // Fechamento 1 com 2 apostas
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Especial',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'checked',
            'contest_number' => $contestNumber,
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => $contestNumber,
            'hits' => 12, // R$ 14,00
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => $contestNumber,
            'hits' => 11, // R$ 7,00
        ]);

        // Aposta avulsa individual no mesmo concurso
        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => null,
            'name' => 'Aposta Avulsa da Sorte',
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => $contestNumber,
            'hits' => 10, // 0 prêmio
        ]);

        $breakdown = $this->service->getContestsBreakdown($this->user->id);

        $this->assertCount(1, $breakdown);
        $contestItem = $breakdown[0];

        $this->assertEquals($contestNumber, $contestItem['contest_number']);
        $this->assertEquals('checked', $contestItem['status']);
        $this->assertEquals(3, $contestItem['total_bets']);
        $this->assertEquals(10.50, $contestItem['total_spent']); // 3 * 3.50
        $this->assertEquals(21.00, $contestItem['total_return']); // 14 + 7
        $this->assertEquals(10.50, $contestItem['net_profit']); // 21 - 10.50
        $this->assertEquals(100.0, $contestItem['roi']); // 100%

        // Sub-itens do Fechamento
        $this->assertEquals(1, $contestItem['closings_count']);
        $this->assertEquals('Fechamento Especial', $contestItem['closings'][0]['name']);
        $this->assertEquals(7.00, $contestItem['closings'][0]['total_spent']);
        $this->assertEquals(21.00, $contestItem['closings'][0]['total_return']);

        // Sub-itens das Apostas Avulsas
        $this->assertEquals(1, $contestItem['individual_bets_count']);
        $this->assertEquals('Aposta Avulsa da Sorte', $contestItem['individual_bets'][0]['name']);
        $this->assertEquals(3.50, $contestItem['individual_bets'][0]['cost']);
        $this->assertEquals(0.00, $contestItem['individual_bets'][0]['prize']);
    }
}
