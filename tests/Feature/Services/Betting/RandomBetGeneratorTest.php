<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use App\Services\Betting\ClosingGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RandomBetGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_random_bets_with_valid_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento aleatório',
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 5,
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(5, $createdBets);

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseCount('bets', 5);
    }

    public function test_each_bet_has_fifteen_unique_numbers_within_range(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 10,
        ]);

        app(ClosingGenerator::class)->generate($closing);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();

        foreach ($bets as $bet) {
            $this->assertCount(15, $bet->numbers);
            $this->assertCount(15, array_unique($bet->numbers));

            foreach ($bet->numbers as $number) {
                $this->assertGreaterThanOrEqual(1, $number);
                $this->assertLessThanOrEqual(25, $number);
            }
        }
    }

    public function test_generated_bets_are_distinct_among_themselves(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 17),
            'bet_size' => 15,
            'planned_bets' => 8,
        ]);

        app(ClosingGenerator::class)->generate($closing);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();

        $signatures = $bets->map(fn (Bet $bet) => implode('-', $bet->numbers))->all();

        $this->assertCount(count($signatures), array_unique($signatures));
    }

    public function test_bets_are_linked_to_the_correct_user_and_closing(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 3,
        ]);

        $otherClosing = Closing::factory()->create([
            'user_id' => $otherUser->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 3,
        ]);

        app(ClosingGenerator::class)->generate($closing);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();

        foreach ($bets as $bet) {
            $this->assertSame($user->id, $bet->user_id);
            $this->assertSame($closing->id, $bet->closing_id);
        }

        $this->assertDatabaseMissing('bets', [
            'closing_id' => $otherClosing->id,
        ]);
    }

    public function test_rejects_when_planned_bets_is_missing(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => null,
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_when_planned_bets_exceeds_possible_combinations(): void
    {
        $user = User::factory()->create();

        // Com 15 dezenas no grupo-base e apostas de 15 dezenas,
        // só existe 1 combinação possível.
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 2,
        ]);

        $this->expectException(InvalidArgumentException::class);

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);

            $this->assertDatabaseCount('bets', 0);
        }
    }
}
