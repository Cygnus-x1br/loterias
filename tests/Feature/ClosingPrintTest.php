<?php

namespace Tests\Feature;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingPrintTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_user_can_access_print_page_for_own_closing(): void
    {
        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Lotofácil Teste',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'completed',
            'contest_number' => 3300,
            'draw_date' => '2026-08-25',
        ]);

        $bet = Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'name' => 'Jogo #1',
            'numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'status' => 'active',
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.print', ['closing' => $closing])
            ->assertSee('Fechamento Lotofácil Teste')
            ->assertSee('Combinação integral')
            ->assertSee('Concurso #3300')
            ->assertSee('Simulação do Volante (Marque com X)')
            ->assertSee('[ X ]')
            ->assertSee('Jogo #01');
    }

    public function test_user_cannot_access_print_page_of_another_user(): void
    {
        $anotherUser = User::factory()->create();

        $closing = Closing::create([
            'user_id' => $anotherUser->id,
            'name' => 'Fechamento de Outro Usuário',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'completed',
        ]);

        Volt::actingAs($this->user)
            ->test('pages.closings.print', ['closing' => $closing])
            ->assertStatus(403);
    }
}
