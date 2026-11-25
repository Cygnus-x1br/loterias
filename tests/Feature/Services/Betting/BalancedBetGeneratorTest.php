<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\BetScoringService;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\ClosingProbabilityService;
use App\Services\Betting\Generators\BalancedBetGenerator;
use App\Services\Betting\HistoricalDrawFilter;
use App\Services\LotofacilStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class BalancedBetGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected const PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23];

    protected const FIBONACCI = [1, 2, 3, 5, 8, 13, 21];

    public function test_generates_balanced_bets_with_valid_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento equilibrado',
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 5,
            'parameters' => [
                'even_odd_balance' => [7, 8], // 7 ou 8 pares
                'sum_range' => [180, 220], // Soma entre 180 e 220
                'primes_count' => [4, 6], // 4 a 6 primos
                'fibonacci_count' => [3, 5], // 3 a 5 fibonacci
            ],
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(5, $createdBets);

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'completed',
        ]);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();
        $this->assertCount(5, $bets);

        foreach ($bets as $bet) {
            $this->assertCount(15, $bet->numbers);
            $this->assertCount(15, array_unique($bet->numbers));
            foreach ($bet->numbers as $number) {
                $this->assertGreaterThanOrEqual(1, $number);
                $this->assertLessThanOrEqual(25, $number);
            }

            // Verifica equilíbrio par/ímpar
            $evenCount = count(array_filter($bet->numbers, fn ($n) => $n % 2 === 0));
            $this->assertGreaterThanOrEqual(7, $evenCount);
            $this->assertLessThanOrEqual(8, $evenCount);

            // Verifica faixa de soma
            $sum = array_sum($bet->numbers);
            $this->assertGreaterThanOrEqual(180, $sum);
            $this->assertLessThanOrEqual(220, $sum);

            // Verifica contagem de primos
            $primesInBet = count(array_intersect($bet->numbers, self::PRIMES));
            $this->assertGreaterThanOrEqual(4, $primesInBet);
            $this->assertLessThanOrEqual(6, $primesInBet);

            // Verifica contagem de Fibonacci
            $fibonacciInBet = count(array_intersect($bet->numbers, self::FIBONACCI));
            $this->assertGreaterThanOrEqual(3, $fibonacciInBet);
            $this->assertLessThanOrEqual(5, $fibonacciInBet);
        }
    }

    public function test_rejects_when_planned_bets_is_missing(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => null, // Faltando
            'parameters' => [
                'even_odd_balance' => [7, 8],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A quantidade planejada de apostas é obrigatória para o método equilibrado.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_even_odd_balance_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'even_odd_balance' => [10, 5], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O equilíbrio par/ímpar deve ser um array [min_pares, max_pares] válido.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_sum_range_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'sum_range' => [250, 150], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A faixa de soma deve ser um array [soma_min, soma_max] válido.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_primes_count_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'primes_count' => [7, 3], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A contagem de primos deve ser um array [min_primos, max_primos] válido.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_fibonacci_count_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fibonacci_count' => [6, 2], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A contagem de Fibonacci deve ser um array [min_fibonacci, max_fibonacci] válido.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_generates_balanced_bets_with_repeated_last_draw_filter(): void
    {
        $user = User::factory()->create();

        // Cria resultado histórico do último concurso
        HistoricalResult::create([
            'contest_number' => 3000,
            'draw_date' => now()->toDateString(),
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento equilibrado com repetidas',
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 5,
            'parameters' => [
                'repeated_last_draw' => [8, 10], // Exatamente 8 a 10 repetidas do concurso anterior
            ],
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(5, $createdBets);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();
        $this->assertCount(5, $bets);

        $lastDrawn = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        foreach ($bets as $bet) {
            $repeatedCount = count(array_intersect($bet->numbers, $lastDrawn));
            $this->assertGreaterThanOrEqual(8, $repeatedCount);
            $this->assertLessThanOrEqual(10, $repeatedCount);
        }
    }

    public function test_rejects_invalid_repeated_last_draw_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'repeated_last_draw' => [12, 5], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A quantidade de dezenas repetidas do último concurso deve ser um array [min_repetidas, max_repetidas] válido.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_fails_if_cannot_generate_enough_unique_balanced_bets(): void
    {
        $user = User::factory()->create();

        // Parâmetros muito restritivos para 1 aposta
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], // Apenas 15 dezenas
            'bet_size' => 15,
            'planned_bets' => 2, // Pedindo 2 apostas, mas só 1 combinação possível
            'parameters' => [
                'even_odd_balance' => [7, 8],
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Não foi possível gerar \d+ apostas equilibradas únicas com os parâmetros fornecidos\. Foram geradas \d+\./');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
            $this->assertDatabaseCount('bets', 0); // Transação revertida em caso de falha
        }
    }

    public function test_generates_balanced_bets_with_score_range_and_temperature_distribution(): void
    {
        $user = User::factory()->create();

        // 1 Concurso para histórico
        HistoricalResult::create([
            'contest_number' => 100,
            'draw_date' => '2026-01-01',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento equilibrado com score e temperatura',
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 3,
            'parameters' => [
                'score_range' => [400, 1000],
                'temperature_distribution' => [
                    'hot' => [2, 10],
                    'neutral' => [1, 10],
                    'cold' => [0, 10],
                ],
            ],
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(3, $createdBets);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();
        $this->assertCount(3, $bets);

        $temperatures = app(LotofacilStatisticsService::class)->getNumberTemperatureClassification(20);
        $scoringService = app(BetScoringService::class);

        foreach ($bets as $bet) {
            $scoreData = $scoringService->calculateScore($bet->numbers);
            $this->assertGreaterThanOrEqual(400, $scoreData['total_score']);
            $this->assertLessThanOrEqual(1000, $scoreData['total_score']);

            $hot = 0;
            $neutral = 0;
            $cold = 0;
            foreach ($bet->numbers as $num) {
                $t = $temperatures[$num]['temperature'] ?? 'neutral';
                if ($t === 'hot') {
                    $hot++;
                } elseif ($t === 'cold') {
                    $cold++;
                } else {
                    $neutral++;
                }
            }

            $this->assertGreaterThanOrEqual(2, $hot);
            $this->assertLessThanOrEqual(10, $hot);
            $this->assertGreaterThanOrEqual(1, $neutral);
            $this->assertLessThanOrEqual(10, $neutral);
            $this->assertGreaterThanOrEqual(0, $cold);
            $this->assertLessThanOrEqual(10, $cold);
        }
    }

    public function test_rejects_invalid_score_range(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'score_range' => [900, 500], // min > max
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A faixa de score deve ser um array [min_score, max_score] válido entre 0 e 1000.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_temperature_distribution(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'temperature_distribution' => [
                    'hot' => [10, 4], // min > max
                ],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O intervalo de dezenas hot deve ser um array [min, max] válido entre 0 e 15.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_generates_multiple_balanced_closings_consecutively_without_cache_serialization_error(): void
    {
        $user = User::factory()->create();

        HistoricalResult::create([
            'contest_number' => 3000,
            'draw_date' => now()->toDateString(),
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        $generator = app(ClosingGenerator::class);

        // Primeiro fechamento (popula o cache de last_contest)
        $closing1 = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento 1',
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 2,
            'parameters' => [
                'repeated_last_draw' => [8, 10],
            ],
        ]);

        $createdBets1 = $generator->generate($closing1);
        $this->assertSame(2, $createdBets1);
        $this->assertDatabaseHas('closings', ['id' => $closing1->id, 'status' => 'completed']);

        // Segundo fechamento (lê do cache populado pelo primeiro)
        $closing2 = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento 2',
            'method' => 'balanced',
            'status' => 'draft',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 2,
            'parameters' => [
                'repeated_last_draw' => [8, 10],
            ],
        ]);

        $createdBets2 = $generator->generate($closing2);
        $this->assertSame(2, $createdBets2);
        $this->assertDatabaseHas('closings', ['id' => $closing2->id, 'status' => 'completed']);
    }

    public function test_records_bets_accepted_with_relaxed_filters(): void
    {
        $user = User::factory()->create();

        // Com a base 1..18 (9 pares e 9 ímpares), toda aposta de 15 tem pelo menos 6 pares.
        // Pedir exatamente 3 pares só é atendido quando a tolerância chega a ±3.
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 3,
            'parameters' => ['even_odd_balance' => [3, 3]],
        ]);

        app(ClosingGenerator::class)->generate($closing);
        $closing->refresh();

        $this->assertSame('completed', $closing->status);
        $this->assertNotEmpty($closing->generation_warnings);
        $this->assertStringContainsString('Equilíbrio Par/Ímpar', $closing->generation_warnings[0]);

        foreach (Bet::where('closing_id', $closing->id)->get() as $bet) {
            $this->assertStringContainsString('Filtro relaxado (tolerância de ±3)', (string) $bet->notes);
        }
    }

    public function test_records_bets_generated_without_filters(): void
    {
        $user = User::factory()->create();

        // Nenhuma aposta de 15 dezenas da base 1..18 pode ter 0 pares, nem com tolerância
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 2,
            'parameters' => ['even_odd_balance' => [0, 0]],
        ]);

        app(ClosingGenerator::class)->generate($closing);
        $closing->refresh();

        $this->assertCount(1, $closing->generation_warnings);
        $this->assertStringContainsString('sem os filtros do método equilibrado', $closing->generation_warnings[0]);

        foreach (Bet::where('closing_id', $closing->id)->get() as $bet) {
            $this->assertStringStartsWith('Gerada sem filtros', (string) $bet->notes);
        }
    }

    public function test_bets_within_filters_have_no_warnings(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 5,
            'parameters' => ['even_odd_balance' => [6, 9]],
        ]);

        app(ClosingGenerator::class)->generate($closing);
        $closing->refresh();

        $this->assertNull($closing->generation_warnings);
        $this->assertSame(0, Bet::where('closing_id', $closing->id)->whereNotNull('notes')->count());
    }

    public function test_complemented_bets_are_flagged_as_unfiltered(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 25),
            'bet_size' => 15,
            'planned_bets' => 4,
            'parameters' => ['even_odd_balance' => [6, 9]],
        ]);

        $generator = app(ClosingGenerator::class);
        $generator->generate($closing);
        Bet::where('closing_id', $closing->id)->orderByDesc('id')->first()->delete();

        $this->assertSame(1, $generator->completeBets($closing->refresh()));

        $closing->refresh();
        $this->assertStringContainsString('complementar', $closing->generation_warnings[0]);
        $this->assertSame(1, Bet::where('closing_id', $closing->id)->where('notes', 'like', 'Aposta complementar%')->count());
    }

    public function test_resetting_closing_clears_generation_warnings(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => ['even_odd_balance' => [3, 3]],
        ]);

        $generator = app(ClosingGenerator::class);
        $generator->generate($closing);
        $this->assertNotEmpty($closing->refresh()->generation_warnings);

        $generator->resetToDraft($closing);

        $this->assertNull($closing->refresh()->generation_warnings);
    }

    public function test_repetition_quotas_follow_hypergeometric_probability(): void
    {
        $service = app(LotofacilStatisticsService::class);

        // P(8) : P(9) : P(10) = 772.200 : 1.051.050 : 756.756 → 29,9 / 40,7 / 29,3 de 100
        $this->assertSame([8 => 30, 9 => 41, 10 => 29], $service->calculateRepetitionQuotas(8, 10, 100));

        $quotas = $service->calculateRepetitionQuotas(7, 11, 37);
        $this->assertSame(37, array_sum($quotas));
        $this->assertSame(9, array_search(max($quotas), $quotas, true));

        // Para apostas de 18 dezenas a faixa possível de repetidas muda (mínimo 8, pois só há 10 fora do último sorteio)
        $this->assertSame(0.0, $service->repetitionProbability(7, 18));
        $this->assertSame(20, array_sum($service->calculateRepetitionQuotas(8, 12, 20, 18)));

        // Faixa sem nenhuma quantidade possível: distribui igualmente
        $this->assertSame([0 => 2, 1 => 2, 2 => 2], $service->calculateRepetitionQuotas(0, 2, 6));
    }

    public function test_repetition_probabilities_sum_to_one(): void
    {
        $service = app(LotofacilStatisticsService::class);

        foreach ([15, 16, 18, 20] as $betSize) {
            $total = array_sum(array_map(fn (int $k): float => $service->repetitionProbability($k, $betSize), range(0, 15)));
            $this->assertEqualsWithDelta(1.0, $total, 1e-9, "Aposta de {$betSize} dezenas");
        }
    }

    public function test_spread_bets_picks_the_candidate_with_least_overlap(): void
    {
        $generator = new class(app(HistoricalDrawFilter::class)) extends BalancedBetGenerator
        {
            public function pick(array $candidates, array $selected): array
            {
                return $this->leastOverlappingCandidate($candidates, $selected);
            }
        };

        $selected = [range(1, 15)];
        $candidate = fn (array $numbers, ?string $relaxed = null): array => ['numbers' => $numbers, 'relaxed_filter' => $relaxed, 'tolerance' => $relaxed ? 1 : 0];

        $candidates = [
            'a' => $candidate(range(2, 16)),                                                  // 14 em comum
            'b' => $candidate([1, 2, 3, 4, 5, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25]),      // 5 em comum
            'c' => $candidate([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 21, 22, 23, 24, 25]),          // 10 em comum
        ];
        $this->assertSame($candidates['b']['numbers'], $generator->pick($candidates, $selected)['numbers']);

        // Uma candidata dentro da faixa original vence uma relaxada, mesmo com mais sobreposição
        $candidates['b']['relaxed_filter'] = 'Equilíbrio Par/Ímpar';
        $this->assertSame($candidates['c']['numbers'], $generator->pick($candidates, $selected)['numbers']);
    }

    public function test_spread_bets_respects_filters_and_spreads_bets(): void
    {
        $user = User::factory()->create();
        $probability = app(ClosingProbabilityService::class);
        $overlaps = [];

        foreach (range(1, 3) as $run) {
            $closing = Closing::factory()->create([
                'user_id' => $user->id,
                'method' => 'balanced',
                'base_numbers' => range(1, 21),
                'bet_size' => 15,
                'planned_bets' => 10,
                'parameters' => ['even_odd_balance' => [6, 9], 'spread_bets' => true],
            ]);

            app(ClosingGenerator::class)->generate($closing);
            $bets = Bet::where('closing_id', $closing->id)->pluck('numbers')->all();

            $this->assertCount(10, $bets);
            $this->assertNull($closing->refresh()->generation_warnings);
            foreach ($bets as $numbers) {
                $evens = count(array_filter($numbers, fn (int $n): bool => $n % 2 === 0));
                $this->assertTrue($evens >= 6 && $evens <= 9);
            }

            $overlaps[] = $probability->averageOverlap($bets);
        }

        // Apostas sorteadas ao acaso de uma base de 21 têm em média 15·15/21 ≈ 10,71 dezenas em comum
        $this->assertLessThan(15 * 15 / 21, array_sum($overlaps) / count($overlaps));
    }

    public function test_rejects_non_boolean_spread_bets(): void
    {
        $closing = Closing::factory()->create([
            'user_id' => User::factory()->create()->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 21),
            'bet_size' => 15,
            'planned_bets' => 5,
            'parameters' => ['spread_bets' => 'sim'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('spread_bets');

        app(BalancedBetGenerator::class)->validate($closing);
    }
}
