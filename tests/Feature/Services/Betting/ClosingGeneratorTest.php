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

class ClosingGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_integral_bets_successfully(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento integral',
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(1, $createdBets);

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('bets', [
            'user_id' => $user->id,
            'closing_id' => $closing->id,
            'source' => 'closing',
            'method' => 'integral',
            'status' => 'active',
        ]);

        $bet = Bet::query()
            ->where('closing_id', $closing->id)
            ->firstOrFail();

        $this->assertSame(range(1, 15), $bet->numbers);
    }

    public function test_respects_planned_bets_limit(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento limitado',
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'planned_bets' => 3,
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(3, $createdBets);

        $this->assertDatabaseCount('bets', 3);

        $this->assertSame(
            3,
            Bet::query()
                ->where('closing_id', $closing->id)
                ->count()
        );

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'completed',
        ]);
    }

    public function test_creates_bets_linked_to_the_correct_closing_and_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
        ]);

        $otherClosing = Closing::factory()->create([
            'user_id' => $otherUser->id,
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
        ]);

        app(ClosingGenerator::class)->generate($closing);

        $bet = Bet::query()
            ->where('closing_id', $closing->id)
            ->firstOrFail();

        $this->assertSame($user->id, $bet->user_id);
        $this->assertSame($closing->id, $bet->closing_id);

        $this->assertDatabaseMissing('bets', [
            'closing_id' => $otherClosing->id,
        ]);
    }

    public function test_marks_closing_as_failed_when_base_group_is_invalid(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => [1, 2, 3],
            'bet_size' => 15,
            'planned_bets' => 1,
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

    public function test_rejects_unimplemented_methods_and_marks_closing_as_failed(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
        ]);

        $this->expectException(LogicException::class);

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_does_not_create_bets_when_generation_fails(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 16,
            'planned_bets' => 1,
        ]);

        try {
            app(ClosingGenerator::class)->generate($closing);
        } catch (InvalidArgumentException) {
            // Exceção esperada para confirmar o rollback lógico da operação.
        }

        $this->assertDatabaseCount('bets', 0);

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'failed',
        ]);
    }

    public function test_filters_out_combinations_matching_historical_draws(): void
    {
        $user = User::factory()->create();

        // Cria um sorteio histórico para a combinação 1 a 15
        HistoricalResult::create([
            'contest_number' => 9999,
            'draw_date' => now(),
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        // Fechamento com base de 1 a 16 pedindo 2 apostas de 15
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento sem repetidos históricos',
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'planned_bets' => 2,
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        // Deve criar 2 apostas, mas nenhuma delas pode ser exatamente 1 a 15
        $this->assertSame(2, $createdBets);

        $bets = Bet::where('closing_id', $closing->id)->get();
        foreach ($bets as $bet) {
            $this->assertNotSame(range(1, 15), $bet->numbers);
        }
    }
}
