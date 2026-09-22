<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingRefactorAndCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_reset_completed_closing_to_draft(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste Reset',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'planned_bets' => 16,
            'status' => 'completed',
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        $this->assertEquals(1, $closing->bets()->count());

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('resetBets')
            ->assertHasNoErrors()
            ->assertSee('Todas as apostas foram apagadas');

        $closing->refresh();
        $this->assertEquals('draft', $closing->status);
        $this->assertEquals(0, $closing->bets()->count());
    }

    public function test_user_can_delete_individual_bet_and_trigger_coverage_update(): void
    {
        // Cria um fechamento com 16 dezenas na base
        $baseNumbers = range(1, 16);
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste Delete Individual',
            'method' => 'integral',
            'base_numbers' => $baseNumbers,
            'bet_size' => 15,
            'planned_bets' => 2,
            'status' => 'completed',
        ]);

        // Aposta 1: dezenas 1 a 15 (a dezena 16 NÃO está aqui)
        $bet1 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Fechamento Teste Delete Individual - Aposta 1',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        // Aposta 2: dezenas 2 a 16 (a dezena 16 está AQUI)
        $bet2 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Fechamento Teste Delete Individual - Aposta 2',
            'numbers' => range(2, 16),
            'status' => 'active',
        ]);

        $component = Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing]);

        // Inicialmente todas as 16 dezenas da base estão contempladas
        $this->assertTrue($component->get('baseNumbersCoverage')['all_used']);

        // Exclui a aposta 2 (que continha a dezena 16 exclusivamente)
        $component->call('deleteBet', $bet2->id)
            ->assertHasNoErrors()
            ->assertSee('Aposta excluída com sucesso');

        $this->assertDatabaseMissing('bets', ['id' => $bet2->id]);
        $this->assertEquals(1, $closing->bets()->count());

        // Gatilho de verificação: a dezena 16 agora deve constar como não utilizada!
        $coverage = $component->get('baseNumbersCoverage');
        $this->assertFalse($coverage['all_used']);
        $this->assertContains(16, $coverage['unused_numbers']);
    }

    public function test_user_can_delete_selected_bets_in_batch(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste Batch Delete',
            'method' => 'integral',
            'base_numbers' => range(1, 17),
            'bet_size' => 15,
            'planned_bets' => 3,
            'status' => 'completed',
        ]);

        $bet1 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        $bet2 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 2',
            'numbers' => range(2, 16),
            'status' => 'active',
        ]);

        $bet3 = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 3',
            'numbers' => range(3, 17),
            'status' => 'active',
        ]);

        $this->assertEquals(3, $closing->bets()->count());

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->set('selectedBets', [(string) $bet1->id, (string) $bet3->id])
            ->call('deleteSelectedBets')
            ->assertHasNoErrors()
            ->assertSee('2 aposta(s) excluída(s) com sucesso');

        $this->assertEquals(1, $closing->bets()->count());
        $this->assertDatabaseMissing('bets', ['id' => $bet1->id]);
        $this->assertDatabaseMissing('bets', ['id' => $bet3->id]);
        $this->assertDatabaseHas('bets', ['id' => $bet2->id]);
    }

    public function test_user_can_complete_missing_bets_up_to_planned_bets(): void
    {
        // Planejado: 5 apostas. Inicial: 2 apostas.
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste Completar',
            'method' => 'reduced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 5,
            'status' => 'completed',
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 2',
            'numbers' => range(2, 16),
            'status' => 'active',
        ]);

        $this->assertEquals(2, $closing->bets()->count());

        $component = Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertSee('Apostas Incompletas')
            ->assertSee('Completar apostas faltantes')
            ->call('completeBets')
            ->assertHasNoErrors()
            ->assertSee('3 aposta(s) adicionada(s) com sucesso');

        $closing->refresh();
        $this->assertEquals(5, $closing->bets()->count());

        // Verifica que não há apostas duplicadas
        $allBetsNumbers = $closing->bets()->get()->map(function ($bet) {
            $nums = is_array($bet->numbers) ? $bet->numbers : json_decode((string) $bet->numbers, true);
            sort($nums);

            return implode('-', $nums);
        });

        $this->assertEquals(5, $allBetsNumbers->unique()->count());

        // Verifica que todas as dezenas da base estão contempladas agora
        $coverage = $component->get('baseNumbersCoverage');
        $this->assertTrue($coverage['all_used']);
    }
}
