<?php

namespace Tests\Feature;

use App\Livewire\LotofacilContestComparison;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\LotofacilStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class LotofacilContestComparisonTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->user = User::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('lotofacil.contest_comparison'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_comparison_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('lotofacil.contest_comparison'));

        $response->assertOk();
        $response->assertSee('Comparativo de Concursos');
    }

    public function test_can_switch_contest_limits_and_view_metrics(): void
    {
        // Cria 12 concursos históricos
        for ($i = 1; $i <= 12; $i++) {
            $drawn = range(1, 15);
            HistoricalResult::create([
                'contest_number' => 3000 + $i,
                'draw_date' => '2026-08-20',
                'drawn_numbers' => $drawn,
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawn),
            ]);
        }

        Livewire::actingAs($this->user)
            ->test(LotofacilContestComparison::class)
            ->assertSee('Concurso #3012')
            ->assertSet('limit', 25)
            ->call('setLimit', 10)
            ->assertSet('limit', 10)
            ->assertSee('10 jogos')
            ->call('recalculate')
            ->assertOk();
    }

    public function test_statistics_service_calculates_comparison_accurately(): void
    {
        // Concurso anterior
        HistoricalResult::create([
            'contest_number' => 3201,
            'draw_date' => '2026-08-20',
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        // Concurso atual (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16)
        HistoricalResult::create([
            'contest_number' => 3202,
            'draw_date' => '2026-08-21',
            'drawn_numbers' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16],
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16]),
        ]);

        $service = app(LotofacilStatisticsService::class);
        $data = $service->getContestsComparisonAnalysis(10);

        $this->assertEquals(2, $data['total_analyzed']);
        $this->assertNotNull($data['last_contest']);
        $this->assertEquals(3202, $data['last_contest']['contest_number']);

        // Repetições do concurso 3202 em relação a 3201 (14 números repetidos)
        $this->assertEquals(14, $data['last_contest']['repeated_from_previous']);

        // Primos em 3202: [2, 3, 5, 7, 11, 13] -> 6 primos
        $this->assertEquals(6, $data['last_contest']['primes']);

        // Fibonacci em 3202: [1, 2, 3, 5, 8, 13] -> 6 fibonacci
        $this->assertEquals(6, $data['last_contest']['fibonacci']);

        // Soma em 3202: sum(1..14) + 16 = 105 + 16 = 121
        $this->assertEquals(121, $data['last_contest']['sum']);

        // Pares em 3202: 2,4,6,8,10,12,14,16 -> 8 pares
        $this->assertEquals(8, $data['last_contest']['evens']);
        $this->assertEquals(7, $data['last_contest']['odds']);

        // Validação da grade das 25 dezenas
        $grid = $data['last_contest']['numbers_grid'];
        $this->assertCount(25, $grid);
        $this->assertTrue($grid[1]['is_drawn']);
        $this->assertTrue($grid[16]['is_drawn']);
        $this->assertFalse($grid[25]['is_drawn']);
    }

    public function test_contests_have_distinct_contextual_temperatures(): void
    {
        // Cria sequência de 35 concursos para verificar que a dezena 25 é 'cold' nos concursos iniciais
        // e evolui para 'hot' nos concursos mais recentes conforme sua frequência histórica aumenta.
        // Concursos 9101 a 9115 (15 concursos): a dezena 25 nunca é sorteada.
        for ($c = 9101; $c <= 9115; $c++) {
            $drawn = [];
            for ($i = 0; $i < 15; $i++) {
                $drawn[] = (($c * 5 + $i) % 24) + 1;
            }
            sort($drawn);
            HistoricalResult::create([
                'contest_number' => $c,
                'draw_date' => '2026-08-01',
                'drawn_numbers' => $drawn,
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawn),
            ]);
        }

        // Concursos 9116 a 9135 (20 concursos): a dezena 25 é sempre sorteada.
        for ($c = 9116; $c <= 9135; $c++) {
            $drawn = [25];
            for ($i = 0; $i < 14; $i++) {
                $drawn[] = (($c * 5 + $i) % 24) + 1;
            }
            sort($drawn);
            HistoricalResult::create([
                'contest_number' => $c,
                'draw_date' => '2026-08-10',
                'drawn_numbers' => $drawn,
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawn),
            ]);
        }

        Cache::forget('lotofacil_contests_comparison_25');

        $service = app(LotofacilStatisticsService::class);
        $data = $service->getContestsComparisonAnalysis(25);

        $contests = collect($data['contests'])->keyBy('contest_number');

        // No concurso 9135, a dezena 25 apareceu em 19 dos 20 concursos anteriores (alta frequência), logo 'hot'
        $tempAt9135 = $contests[9135]['numbers_grid'][25]['temperature'];
        $this->assertEquals('hot', $tempAt9135);

        // No concurso 9116, a dezena 25 não apareceu em nenhum dos 15 concursos anteriores (frequência 0), logo 'cold'
        $tempAt9116 = $contests[9116]['numbers_grid'][25]['temperature'];
        $this->assertEquals('cold', $tempAt9116);

        // Garante que a classificação térmica da mesma dezena é dinâmica e contextual a cada sorteio
        $this->assertNotEquals($tempAt9135, $tempAt9116);
    }
}
