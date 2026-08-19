<?php

namespace Tests\Feature;

use App\Livewire\LotofacilAnalysis;
use App\Models\HistoricalResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LotofacilAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_analysis_page(): void
    {
        $this->get(route('lotofacil.analysis'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_analysis_page(): void
    {
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => '2026-08-01',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        $this->actingAs($this->user)
            ->get(route('lotofacil.analysis'))
            ->assertOk()
            ->assertSee('Análises da Lotofácil')
            ->assertSee('Cálculos Matemáticos')
            ->assertSee('Média de Score dos Concursos');
    }

    public function test_component_renders_correct_statistics_for_consecutive_draws(): void
    {
        // Concurso 1: 1 a 15
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => '2026-08-01',
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        // Concurso 2: 7 a 21 (repete 9 dezenas: 7, 8, 9, 10, 11, 12, 13, 14, 15)
        // Soma: 7+8+9+10+11+12+13+14+15+16+17+18+19+20+21 = 210
        // Pares: 8, 10, 12, 14, 16, 18, 20 (7 pares) / Ímpares: 7, 9, 11, 13, 15, 17, 19, 21 (8 ímpares)
        // Moldura: 10, 11, 15, 16, 20, 21 (6 na moldura) / Centro: 7, 8, 9, 12, 13, 14, 17, 18, 19 (9 no centro)
        HistoricalResult::create([
            'contest_number' => 2,
            'draw_date' => '2026-08-03',
            'drawn_numbers' => [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(7, 21)),
        ]);

        Livewire::actingAs($this->user)
            ->test(LotofacilAnalysis::class)
            ->assertSet('totalContests', 2)
            ->assertSet('consecutiveRepetitionAnalysis.last_draw_repetitions_count', 9)
            ->assertSet('consecutiveRepetitionAnalysis.historical_average', 9.0)
            ->assertSet('sumAnalysis.last_draw_sum', 210)
            ->assertSet('evenOddAnalysis.last_draw_evens', 7)
            ->assertSet('evenOddAnalysis.last_draw_odds', 8)
            ->assertSet('frameCenterAnalysis.last_draw_frame', 6)
            ->assertSet('frameCenterAnalysis.last_draw_center', 9)
            ->assertSee('Repetição de Dezenas do Último Sorteio')
            ->assertSee('Soma das Dezenas Sorteadas')
            ->assertSee('Dezenas Pares e Ímpares')
            ->assertSee('Moldura e Centro')
            ->assertSee('Sequências de Dezenas Consecutivas');
    }

    public function test_component_can_recalculate_statistics(): void
    {
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => '2026-08-01',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        Livewire::actingAs($this->user)
            ->test(LotofacilAnalysis::class)
            ->call('recalculate')
            ->assertHasNoErrors()
            ->assertSet('totalContests', 1);
    }
}
