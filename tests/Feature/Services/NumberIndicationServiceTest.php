<?php

namespace Tests\Feature\Services;

use App\Livewire\LotofacilAnalysis;
use App\Models\HistoricalResult;
use App\Models\NumberIndication;
use App\Models\User;
use App\Services\BaseNumbersSuggestionService;
use App\Services\NumberIndicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class NumberIndicationServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $numbers
     */
    private function createContest(int $contestNumber, array $numbers): HistoricalResult
    {
        sort($numbers);

        return HistoricalResult::create([
            'contest_number' => $contestNumber,
            'draw_date' => now()->subDays(100 - $contestNumber)->toDateString(),
            'drawn_numbers' => $numbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
            'score' => 500,
        ]);
    }

    private function seedContests(int $quantity): void
    {
        mt_srand(21);
        foreach (range(1, $quantity) as $contest) {
            $numbers = range(1, 25);
            shuffle($numbers);
            $this->createContest($contest, array_slice($numbers, 0, 15));
        }
    }

    public function test_saving_the_latest_contest_registers_the_next_one(): void
    {
        $this->seedContests(5);

        $indication = NumberIndication::where('target_contest_number', 6)->first();

        $this->assertNotNull($indication);
        $this->assertSame(5, $indication->based_on_contest_number);
        $this->assertCount(25, $indication->scores);
        $this->assertCount(BaseNumbersSuggestionService::MOST_INDICATED, $indication->most_indicated);
        $this->assertCount(BaseNumbersSuggestionService::LEAST_INDICATED, $indication->least_indicated);
        $this->assertSame([], array_intersect($indication->most_indicated, $indication->least_indicated));
        $this->assertNull($indication->evaluated_at);

        // Só existe indicação pendente para o próximo concurso
        $this->assertSame(1, NumberIndication::whereNull('evaluated_at')->count());
    }

    public function test_indication_uses_the_lowest_and_highest_heuristic_scores(): void
    {
        $this->seedContests(5);

        $suggestion = app(BaseNumbersSuggestionService::class);
        $scores = $suggestion->heuristicScores();
        $indication = NumberIndication::where('target_contest_number', 6)->first();

        $this->assertEquals($scores, array_map('floatval', $indication->scores));

        $lowestIncluded = max(array_map(fn (int $n): float => $scores[$n], $indication->least_indicated));
        $highestExcluded = min(array_map(fn (int $n): float => $scores[$n], array_diff(range(1, 25), $indication->least_indicated)));
        $this->assertLessThanOrEqual($highestExcluded, $lowestIncluded);
    }

    public function test_result_of_target_contest_evaluates_the_indication(): void
    {
        $this->seedContests(5);
        $indication = NumberIndication::where('target_contest_number', 6)->first();

        $drawn = array_merge($indication->least_indicated, array_slice(array_values(array_diff(range(1, 25), $indication->least_indicated)), 0, 10));
        $this->createContest(6, $drawn);

        $indication->refresh();

        $this->assertNotNull($indication->evaluated_at);
        $this->assertSame(5, $indication->least_hits);
        $this->assertSame(count(array_intersect($indication->most_indicated, $drawn)), $indication->most_hits);
        $this->assertNotNull(NumberIndication::where('target_contest_number', 7)->first());
    }

    public function test_evaluated_indication_is_never_overwritten(): void
    {
        $this->seedContests(5);
        $this->createContest(6, range(1, 15));

        $evaluated = NumberIndication::where('target_contest_number', 6)->first();
        $before = $evaluated->only(['scores', 'most_indicated', 'least_indicated', 'least_hits']);

        // Reeditar um concurso antigo e sincronizar não muda a indicação já conferida
        HistoricalResult::where('contest_number', 3)->first()->update(['score' => 600]);
        app(NumberIndicationService::class)->sync();

        $this->assertSame($before, $evaluated->fresh()->only(['scores', 'most_indicated', 'least_indicated', 'least_hits']));
    }

    public function test_sync_evaluates_results_inserted_without_model_events(): void
    {
        $this->seedContests(5);

        // Como no seeder: insert() não dispara os eventos do model
        HistoricalResult::insert([
            'contest_number' => 6,
            'draw_date' => now()->toDateString(),
            'drawn_numbers' => json_encode(range(1, 15)),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
            'score' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::flush();

        $result = app(NumberIndicationService::class)->sync();

        $this->assertSame(['evaluated' => 1, 'registered_for' => 7], $result);
        $this->assertNotNull(NumberIndication::where('target_contest_number', 6)->first()->evaluated_at);
    }

    public function test_summary_reports_rates_and_waits_for_enough_contests(): void
    {
        NumberIndication::factory()->count(10)->evaluated(mostHits: 6, leastHits: 3)->create();

        $summary = app(NumberIndicationService::class)->summary();

        $this->assertSame(10, $summary['evaluated_contests']);
        $this->assertEqualsWithDelta(0.6, $summary['least']['rate'], 1e-9);
        $this->assertEqualsWithDelta(0.6, $summary['most']['rate'], 1e-9);
        // 15 sorteadas por concurso: 3 entre as menos indicadas, 12 entre as outras 20
        $this->assertEqualsWithDelta(0.6, $summary['others']['rate'], 1e-9);
        $this->assertStringContainsString('Amostra ainda pequena', $summary['verdict']);
    }

    public function test_summary_detects_least_indicated_below_expected_after_enough_contests(): void
    {
        NumberIndication::factory()->count(150)->evaluated(mostHits: 6, leastHits: 2)->create();

        $summary = app(NumberIndicationService::class)->summary();

        $this->assertEqualsWithDelta(0.4, $summary['least']['rate'], 1e-9);
        $this->assertStringContainsString('abaixo dos 60% esperados', $summary['verdict']);
    }

    public function test_sync_command_registers_next_indication(): void
    {
        $this->seedContests(3);
        NumberIndication::query()->delete();

        $this->artisan('lotofacil:indications')
            ->expectsOutputToContain('Indicação registrada para o concurso 4')
            ->assertSuccessful();
    }

    public function test_suggestion_leaves_least_indicated_numbers_out_when_possible(): void
    {
        $this->seedContests(30);
        $service = app(BaseNumbersSuggestionService::class);

        // Base de 15 com 5 repetidas: entram as 10 dezenas fora do último concurso e 5 das 15 que saíram nele.
        // As menos indicadas (atraso 0) estão entre as que saíram, então dá para deixar todas de fora.
        $lastDrawn = HistoricalResult::orderByDesc('contest_number')->first()->drawn_numbers;
        $suggestion = $service->suggest(15, 5);
        $report = $suggestion['report'];

        $this->assertSame([], array_values(array_diff($report['least_indicated'], $lastDrawn)));

        $this->assertCount(BaseNumbersSuggestionService::LEAST_INDICATED, $report['least_indicated']);
        $this->assertSame([], $report['least_included']);
        $this->assertSame([], array_values(array_intersect($report['least_indicated'], $suggestion['numbers'])));
        $this->assertStringContainsString('ficaram fora do grupo', $report['criteria']['cross']);

        // Base de 25: todas entram, e o relatório avisa
        $full = $service->suggest(25, 15)['report'];
        $this->assertSame($full['least_indicated'], $full['least_included']);
        $this->assertStringContainsString('precisaram entrar', $full['criteria']['cross']);
    }

    public function test_analysis_page_shows_the_tracking_panel(): void
    {
        $this->seedContests(5);

        Livewire::actingAs(User::factory()->create())
            ->test(LotofacilAnalysis::class)
            ->assertSee('Acompanhamento: dezenas mais e menos indicadas')
            ->assertSee('Mais indicadas para o concurso 6')
            ->assertSee('Amostra ainda pequena');
    }
}
