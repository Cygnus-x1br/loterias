<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class FinancialAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('financial.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_financial_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('financial.index'));

        $response->assertOk();
        $response->assertSee('Gastos e Retornos');
        $response->assertSee('Total Investido');
        $response->assertSee('Prêmios Conquistados');
        $response->assertSee('Retorno sobre Investimento');
    }

    public function test_financial_page_displays_contest_data_and_filters(): void
    {
        $contestNumber = 3310;

        HistoricalResult::create([
            'contest_number' => $contestNumber,
            'draw_date' => '2026-08-25',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
            'payout_11_hits' => 7.00,
        ]);

        $closing = Closing::create([
            'user_id' => $this->user->id,
            'name' => 'Fechamento Teste Financeiro',
            'method' => 'integral',
            'base_numbers' => range(1, 16),
            'bet_size' => 15,
            'status' => 'checked',
            'contest_number' => $contestNumber,
        ]);

        Bet::create([
            'user_id' => $this->user->id,
            'closing_id' => $closing->id,
            'numbers' => range(1, 15),
            'status' => 'checked',
            'contest_number' => $contestNumber,
            'hits' => 11,
        ]);

        Volt::actingAs($this->user)
            ->test('pages.financial.index')
            ->assertSee('Concurso #'.$contestNumber)
            ->assertSee('Fechamento Teste Financeiro')
            ->assertSee('R$ 3,50')
            ->assertSee('R$ 7,00')
            ->set('contestFilter', 9999)
            ->assertSee('Nenhum concurso apostado encontrado')
            ->call('clearFilters')
            ->assertSee('Concurso #'.$contestNumber);
    }

    public function test_dashboard_displays_financial_accumulated_card(): void
    {
        Bet::create([
            'user_id' => $this->user->id,
            'name' => 'Aposta Apostada',
            'numbers' => range(1, 15),
            'status' => 'placed',
            'contest_number' => 3315,
        ]);

        Livewire::actingAs($this->user)
            ->test(Dashboard::class)
            ->assertSee('Balanço Financeiro Acumulado')
            ->assertSee('R$ 3,50')
            ->assertSee('Ver detalhamento por concurso');
    }
}
