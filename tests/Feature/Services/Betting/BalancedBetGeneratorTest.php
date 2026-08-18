<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\Betting\ClosingGenerator;
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
}
