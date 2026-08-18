<?php

namespace Tests\Feature\Pages;

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
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('closings.show', $closing));

        $closing->refresh();

        $this->assertEquals('Fechamento Atualizado', $closing->name);
        $this->assertCount(20, $closing->base_numbers);
        $this->assertEquals([7, 9], $closing->parameters['even_odd_balance']);
        $this->assertEquals([8, 11], $closing->parameters['repeated_last_draw']);
    }

    public function test_cannot_edit_completed_closing(): void
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

        $response = $this->actingAs($this->user)
            ->get(route('closings.edit', $closing));

        $response->assertRedirect(route('closings.show', $closing));
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

    public function test_show_page_displays_edit_button_only_for_draft_or_failed(): void
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
            ->assertDontSee('Editar Fechamento');
    }
}
