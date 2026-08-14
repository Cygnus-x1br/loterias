<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingPlacedAndCheckTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_mark_closing_as_placed(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'completed',
        ]);

        $bet1 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('openMarkAsPlacedModal')
            ->assertSet('showMarkAsPlacedModal', true)
            ->set('placedContestNumber', 3250)
            ->set('placedDrawDate', '2026-08-20')
            ->call('markAsPlaced')
            ->assertHasNoErrors()
            ->assertSet('showMarkAsPlacedModal', false);

        $closing->refresh();
        $bet1->refresh();

        $this->assertEquals('placed', $closing->status);
        $this->assertEquals(3250, $closing->contest_number);
        $this->assertEquals('2026-08-20', $closing->draw_date->format('Y-m-d'));

        $this->assertEquals('placed', $bet1->status);
        $this->assertEquals(3250, $bet1->contest_number);
    }

    public function test_user_can_check_results_against_historical_result(): void
    {
        // 1. Cria o resultado histórico manual no sistema
        HistoricalResult::create([
            'contest_number' => 3250,
            'draw_date' => '2026-08-20',
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        // 2. Cria fechamento e apostas
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'placed',
            'contest_number' => 3250,
            'draw_date' => '2026-08-20',
        ]);

        // Aposta 1: 15 acertos
        $bet1 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'status' => 'placed',
            'contest_number' => 3250,
        ]);

        // Aposta 2: 14 acertos (troca 15 por 20)
        $bet2 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 2',
            'numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 20],
            'status' => 'placed',
            'contest_number' => 3250,
        ]);

        // Aposta 3: 10 acertos (troca 5 dezenas)
        $bet3 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 3',
            'numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 21, 22, 23, 24, 25],
            'status' => 'placed',
            'contest_number' => 3250,
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('checkResults')
            ->assertHasNoErrors()
            ->assertSee('Relatório Oficial de Conferência')
            ->assertSee('15 Acertos')
            ->assertSee('14 Acertos');

        $bet1->refresh();
        $bet2->refresh();
        $bet3->refresh();
        $closing->refresh();

        $this->assertEquals('checked', $closing->status);
        $this->assertEquals(15, $bet1->hits);
        $this->assertEquals('checked', $bet1->status);

        $this->assertEquals(14, $bet2->hits);
        $this->assertEquals('checked', $bet2->status);

        $this->assertEquals(10, $bet3->hits);
        $this->assertEquals('checked', $bet3->status);
    }

    public function test_check_shows_error_if_historical_result_does_not_exist(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'placed',
            'contest_number' => 9999, // Concurso inexistente
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('checkResults')
            ->assertSet('checkError', "O resultado do Concurso #9999 ainda não foi cadastrado no sistema em 'Resultados Anteriores'.")
            ->assertSee('O resultado do Concurso #9999 ainda não foi cadastrado no sistema');
    }

    public function test_individual_bet_creation_with_placed_status(): void
    {
        Volt::actingAs($this->user)
            ->test('pages.bets.create')
            ->set('numbers', range(1, 15))
            ->set('status', 'placed')
            ->set('contest_number', 3260)
            ->set('draw_date', '2026-08-22')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('bets.index'));

        $this->assertDatabaseHas('bets', [
            'user_id' => $this->user->id,
            'status' => 'placed',
            'contest_number' => 3260,
        ]);
    }

    public function test_individual_bet_mark_as_placed_and_check_in_index(): void
    {
        // 1. Cria resultado histórico
        HistoricalResult::create([
            'contest_number' => 3270,
            'draw_date' => '2026-08-23',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        // 2. Cria aposta ativa avulsa
        $bet = Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta Avulsa',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        // 3. Marca como apostada na listagem
        Volt::actingAs($this->user)
            ->test('pages.bets.index')
            ->call('openMarkAsPlacedModal', $bet->id)
            ->assertSet('showMarkAsPlacedModal', true)
            ->set('placedContestNumber', 3270)
            ->set('placedDrawDate', '2026-08-23')
            ->call('markBetAsPlaced')
            ->assertHasNoErrors()
            ->assertSet('showMarkAsPlacedModal', false);

        $bet->refresh();
        $this->assertEquals('placed', $bet->status);
        $this->assertEquals(3270, $bet->contest_number);

        // 4. Confere a aposta avulsa
        Volt::actingAs($this->user)
            ->test('pages.bets.index')
            ->call('checkBet', $bet->id)
            ->assertHasNoErrors();

        $bet->refresh();
        $this->assertEquals('checked', $bet->status);
        $this->assertEquals(15, $bet->hits);
    }
}
