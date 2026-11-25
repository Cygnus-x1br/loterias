<?php

namespace Tests\Feature\Pages;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingsEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    public function test_user_can_view_edit_page_for_draft_closing(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Rascunho',
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 5,
            'status' => 'draft',
            'parameters' => [
                'even_odd_balance' => [7, 9],
                'sum_range' => [180, 210],
                'primes_count' => [4, 6],
                'fibonacci_count' => [3, 5],
                'repeated_last_draw' => [8, 10],
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('closings.edit', $closing));

        $response->assertOk();
        $response->assertSee('Editar fechamento');
        $response->assertSee('Fechamento Rascunho');
    }

    public function test_user_can_edit_parameters_of_draft_closing(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Original',
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 5,
            'status' => 'draft',
            'parameters' => [
                'even_odd_balance' => [6, 8],
                'repeated_last_draw' => [7, 9],
            ],
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->set('name', 'Fechamento Atualizado')
            ->set('base_numbers', range(1, 20))
            ->set('min_even', 7)
            ->set('max_even', 9)
            ->set('min_repeated_last_draw', 8)
            ->set('max_repeated_last_draw', 11)
            ->set('min_score', 650)
            ->set('max_score', 950)
            ->set('min_hot', 4)
            ->set('max_hot', 7)
            ->set('min_neutral', 4)
            ->set('max_neutral', 6)
            ->set('min_cold', 2)
            ->set('max_cold', 5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('closings.show', $closing));

        $closing->refresh();

        $this->assertEquals('Fechamento Atualizado', $closing->name);
        $this->assertCount(20, $closing->base_numbers);
        $this->assertEquals([7, 9], $closing->parameters['even_odd_balance']);
        $this->assertEquals([8, 11], $closing->parameters['repeated_last_draw']);
        $this->assertEquals([650, 950], $closing->parameters['score_range']);
        $this->assertEquals([
            'hot' => [4, 7],
            'neutral' => [4, 6],
            'cold' => [2, 5],
        ], $closing->parameters['temperature_distribution']);
    }

    public function test_can_edit_completed_closing_and_it_resets_to_draft(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Concluído',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'planned_bets' => 16,
            'status' => 'completed',
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta Antiga',
            'numbers' => range(1, 15),
            'status' => 'active',
        ]);

        $this->assertEquals(1, $closing->bets()->count());

        $response = $this->actingAs($this->user)
            ->get(route('closings.edit', $closing));

        $response->assertOk();
        $response->assertSee('Editar fechamento');

        // Testa salvar alterações
        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->assertSet('name', 'Fechamento Concluído')
            ->set('name', 'Fechamento Reeditado')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('closings.show', $closing));

        $closing->refresh();
        $this->assertEquals('Fechamento Reeditado', $closing->name);
        $this->assertEquals('draft', $closing->status);
        $this->assertEquals(0, $closing->bets()->count());
    }

    public function test_user_cannot_access_other_users_closing_edit(): void
    {
        $closing = Closing::create([
            'user_id' => $this->otherUser->id,
            'name' => 'Fechamento de Outro',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('closings.edit', $closing));

        $response->assertForbidden();
    }

    public function test_show_page_displays_edit_button_for_draft_failed_and_completed(): void
    {
        $draftClosing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Draft',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'draft',
        ]);

        $completedClosing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Completed',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'completed',
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $draftClosing])
            ->assertSee('Editar Fechamento')
            ->assertSee(route('closings.edit', $draftClosing));

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $completedClosing])
            ->assertSee('Editar Fechamento')
            ->assertSee(route('closings.edit', $completedClosing));
    }

    public function test_spread_bets_option_is_loaded_and_saved(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento distribuído',
            'method' => 'balanced',
            'base_numbers' => range(1, 21),
            'bet_size' => 15,
            'planned_bets' => 10,
            'status' => 'draft',
            'parameters' => ['spread_bets' => true],
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->assertSet('spread_bets', true)
            ->set('spread_bets', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertArrayNotHasKey('spread_bets', $closing->refresh()->parameters ?? []);

        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->assertSet('spread_bets', false)
            ->set('spread_bets', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($closing->refresh()->parameters['spread_bets']);
    }

    public function test_number_weights_are_loaded_kept_and_editable(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento ponderado',
            'method' => 'balanced',
            'base_numbers' => range(1, 21),
            'bet_size' => 15,
            'planned_bets' => 10,
            'status' => 'draft',
            'parameters' => ['number_weights' => [3 => 0.0, 7 => 4.0]],
        ]);

        // Salvar sem mexer preserva os pesos
        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->assertSet('number_weights', [3 => 0.0, 7 => 4.0])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals([3 => 0.0, 7 => 4.0], $closing->refresh()->parameters['number_weights']);

        // Voltar um peso para 1 (neutro) o remove
        Volt::actingAs($this->user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->set('number_weights.3', '1')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals([7 => 4.0], $closing->refresh()->parameters['number_weights']);
    }

    public function test_show_page_calculates_exact_probabilities(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento com uma aposta',
            'method' => 'random',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
            'status' => 'completed',
        ]);
        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Aposta 1',
            'numbers' => range(1, 15),
            'source' => 'closing',
            'method' => 'random',
            'status' => 'active',
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertSee('Chance real de premiação')
            ->assertSee('Calcular probabilidades')
            ->call('calculateProbabilities')
            ->assertSet('probabilityError', null)
            ->assertSee('10,59%')
            ->assertSee('Exato (enumeração dos 3.268.760 sorteios possíveis)')
            ->assertDontSee('Calcular probabilidades');
    }
}
