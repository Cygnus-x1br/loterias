<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LotofacilStatistics;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\LotofacilStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class LotofacilStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_estatisticas_retornam_collections_e_funcionam_com_cache(): void
    {
        $drawnNumbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => '2026-01-01',
            'drawn_numbers' => $drawnNumbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawnNumbers),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(LotofacilStatisticsService::class);

        $mostDrawn = $service->getMostDrawnNumbers(10);
        $this->assertInstanceOf(Collection::class, $mostDrawn);

        // Test cache hit
        $mostDrawnCached = $service->getMostDrawnNumbers(10);
        $this->assertInstanceOf(Collection::class, $mostDrawnCached);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(LotofacilStatistics::class)
            ->assertSet('lastContest.result.contest_number', 1)
            ->assertSee('Estatísticas da Lotofácil')
            ->assertSee('Repetição de Resultados Anteriores')
            ->assertSee('0 repetições de 15 dezenas');
    }

    public function test_estatisticas_detectam_repeticoes_de_15_dezenas(): void
    {
        $drawnNumbers1 = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        $hash1 = HistoricalResult::generateDrawnNumbersHash($drawnNumbers1);

        HistoricalResult::create([
            'contest_number' => 100,
            'draw_date' => '2026-01-01',
            'drawn_numbers' => $drawnNumbers1,
            'drawn_numbers_hash' => $hash1,
        ]);

        // Mesmo sorteio em outro concurso (simulado)
        HistoricalResult::create([
            'contest_number' => 105,
            'draw_date' => '2026-01-10',
            'drawn_numbers' => $drawnNumbers1,
            'drawn_numbers_hash' => $hash1,
        ]);

        $service = app(LotofacilStatisticsService::class);
        $analysis = $service->checkRepeatedDraws();

        $this->assertTrue($analysis['has_repeated']);
        $this->assertEquals(1, $analysis['repeated_groups_count']);
        $this->assertEquals(2, $analysis['total_contests']);
        $this->assertEquals(2, $analysis['repetitions'][0]['total_occurrences']);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(LotofacilStatistics::class)
            ->assertSee('1 repetição(ões) encontrada(s)')
            ->assertSee('#100')
            ->assertSee('#105')
            ->call('recalculateRepeatedDraws')
            ->assertHasNoErrors();
    }

    public function test_calcula_media_de_score_historico_e_temperaturas_do_ultimo_sorteio(): void
    {
        $drawnNumbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => '2026-01-01',
            'drawn_numbers' => $drawnNumbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawnNumbers),
        ]);

        $service = app(LotofacilStatisticsService::class);
        $averageScore = $service->getHistoricalAverageScore();

        $this->assertIsArray($averageScore);
        $this->assertEquals(1, $averageScore['total_contests']);
        $this->assertGreaterThan(0, $averageScore['average_score']);

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(LotofacilStatistics::class)
            ->assertSee('Média de Score dos Concursos')
            ->assertSee('Quentes')
            ->assertSee('Neutras')
            ->assertSee('Frias')
            ->assertSet('lastContestTemperatures.hot_count', fn ($val) => is_int($val))
            ->assertSet('lastContestTemperatures.neutral_count', fn ($val) => is_int($val))
            ->assertSet('lastContestTemperatures.cold_count', fn ($val) => is_int($val));
    }
}
