<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\CoverageAuditorService;
use App\Services\Betting\HistoricalDrawFilter;
use App\Services\Betting\Math\Combinatorics;
use App\Services\SmartRandomBetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HistoricalDrawFilteringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Combinações (chave "1-2-3...") cadastradas como sorteios passados.
     *
     * @var array<string, bool>
     */
    private array $pastDraws = [];

    /**
     * Cadastra como sorteios passados as primeiras $quantity combinações de 15 dezenas dentro de 1..17.
     */
    private function seedPastDrawsInsideOneToSeventeen(int $quantity): void
    {
        $rows = [];

        foreach (Combinatorics::combinations(range(1, 17), 15) as $combination) {
            if (count($rows) >= $quantity) {
                break;
            }

            $this->pastDraws[implode('-', $combination)] = true;
            $rows[] = [
                'contest_number' => count($rows) + 1,
                'draw_date' => now()->subDays(200 - count($rows))->toDateString(),
                'drawn_numbers' => json_encode($combination),
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($combination),
                'cycle_number' => 1,
                'score' => 500,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // insert() não dispara os eventos do model, então o cache precisa ser limpo manualmente
        HistoricalResult::insert($rows);
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function generateClosing(string $method, ?int $plannedBets, array $parameters = []): Closing
    {
        $closing = Closing::factory()->create([
            'user_id' => User::factory()->create()->id,
            'method' => $method,
            'base_numbers' => range(1, 17),
            'bet_size' => 15,
            'planned_bets' => $plannedBets,
            'parameters' => $parameters,
        ]);

        app(ClosingGenerator::class)->generate($closing);

        return $closing->fresh();
    }

    /**
     * @return array<int, array<int, int>>
     */
    private function betsOf(Closing $closing): array
    {
        return Bet::where('closing_id', $closing->id)
            ->pluck('numbers')
            ->map(function (array $numbers): array {
                $numbers = array_map('intval', $numbers);
                sort($numbers);

                return $numbers;
            })
            ->all();
    }

    private function assertNoPastDraws(Closing $closing): void
    {
        foreach ($this->betsOf($closing) as $numbers) {
            $this->assertArrayNotHasKey(implode('-', $numbers), $this->pastDraws, 'Aposta igual a um sorteio passado: '.implode('-', $numbers));
        }
    }

    public function test_filter_recognizes_past_draw_regardless_of_order_and_type(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(1);
        $filter = app(HistoricalDrawFilter::class);

        $this->assertTrue($filter->isPastDraw(range(1, 15)));
        $this->assertTrue($filter->isPastDraw(array_reverse(range(1, 15))));
        $this->assertTrue($filter->isPastDraw(array_map('strval', range(1, 15))));
        $this->assertFalse($filter->isPastDraw(range(2, 16)));
        $this->assertFalse($filter->isPastDraw(range(1, 16)));
    }

    public function test_filter_sees_contests_registered_after_first_use(): void
    {
        $this->assertFalse(app(HistoricalDrawFilter::class)->isPastDraw(range(3, 17)));

        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => now()->toDateString(),
            'drawn_numbers' => range(3, 17),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(3, 17)),
            'score' => 500,
        ]);

        $this->assertTrue(app(HistoricalDrawFilter::class)->isPastDraw(range(3, 17)));
    }

    public function test_random_method_replaces_past_draws_and_keeps_planned_quantity(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(100);

        $closing = $this->generateClosing('random', 30);

        $this->assertSame('completed', $closing->status);
        $this->assertCount(30, $this->betsOf($closing));
        $this->assertNoPastDraws($closing);
    }

    public function test_balanced_method_replaces_past_draws_and_keeps_planned_quantity(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(100);

        $closing = $this->generateClosing('balanced', 20);

        $this->assertSame('completed', $closing->status);
        $this->assertCount(20, $this->betsOf($closing));
        $this->assertNoPastDraws($closing);
    }

    public function test_wheel_method_skips_past_draws_and_keeps_planned_quantity(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(100);

        // As combinações livres de sorteio são as que deixam de fora 1, 2 ou 3, por isso elas ficam nas variáveis
        $fixedNumbers = [4, 5, 6, 7, 8, 9, 10, 15, 16, 17];
        $variableNumbers = [1, 2, 3, 11, 12, 13, 14];

        $available = 0;
        foreach (Combinatorics::combinations($variableNumbers, 5) as $variable) {
            $bet = array_merge($fixedNumbers, $variable);
            sort($bet);
            if (! isset($this->pastDraws[implode('-', $bet)])) {
                $available++;
            }
        }

        // Parte das 21 combinações da roda já saiu: a roda precisa pular essas e entregar as demais
        $this->assertGreaterThan(0, $available);
        $this->assertLessThan(21, $available);

        $closing = $this->generateClosing('wheel', $available, [
            'fixed_numbers' => $fixedNumbers,
            'variable_numbers' => $variableNumbers,
            'wheel_size' => 5,
        ]);

        $this->assertSame('completed', $closing->status);
        $this->assertCount($available, $this->betsOf($closing));
        $this->assertNoPastDraws($closing);
    }

    public function test_reduced_method_keeps_its_guarantee_without_using_past_draws(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(100);

        $closing = $this->generateClosing('reduced', null, [
            'reduced_parameters' => ['guarantee_hits' => 15, 'guarantee_points' => 14],
        ]);

        $this->assertSame('completed', $closing->status);
        $this->assertNoPastDraws($closing);

        $audit = app(CoverageAuditorService::class)->auditCoverage(range(1, 17), $this->betsOf($closing), 14, 15);

        $this->assertTrue($audit['garantia_confirmada']);
        $this->assertEquals(100, $audit['cobertura_por_faixa']['14_acertos']);
    }

    public function test_smart_random_bet_never_returns_a_past_draw(): void
    {
        $this->seedPastDrawsInsideOneToSeventeen(100);
        $service = app(SmartRandomBetService::class);

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $bet = $service->generateSmartBet();

            $this->assertCount(15, $bet);
            $this->assertArrayNotHasKey(implode('-', $bet), $this->pastDraws);
        }
    }
}
