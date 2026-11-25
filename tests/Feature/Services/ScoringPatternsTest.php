<?php

namespace Tests\Feature\Services;

use App\Models\HistoricalResult;
use App\Services\BetScoringService;
use App\Services\LotofacilStatisticsService;
use App\Services\ScoringPatternTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringPatternsTest extends TestCase
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
            'draw_date' => now()->subDays(500 - $contestNumber)->toDateString(),
            'drawn_numbers' => $numbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
            'score' => 500,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function randomDraw(): array
    {
        $numbers = range(1, 25);
        shuffle($numbers);

        return array_slice($numbers, 0, 15);
    }

    public function test_tracker_counts_numbers_pairs_trios_and_consecutive_sequences(): void
    {
        $tracker = new ScoringPatternTracker;
        $tracker->add([1, 2, 3, 4, 10, 11, 12, 13, 14, 15, 20, 21, 22, 23, 25]);
        $tracker->add([1, 2, 3, 5, 7, 9, 11, 13, 15, 17, 19, 21, 23, 24, 25]);

        $patterns = $tracker->snapshot();

        $this->assertSame(2, $tracker->draws());
        // Saíram nos dois concursos: 1, 2, 3, 11, 13, 15, 21, 23, 25 (empate decidido pela menor dezena)
        $this->assertSame([1, 2, 3, 11, 13, 15, 21, 23, 25, 4], $patterns['top_numbers']);
        $this->assertSame('01-02', $patterns['top_pairs'][0]);
        $this->assertSame('01-02-03', $patterns['top_trios'][0]);
        $this->assertSame(['01-02', '02-03'], array_slice($patterns['top_consecutive_pairs'], 0, 2));
        $this->assertSame('01-02-03', $patterns['top_consecutive_trios'][0]);
        $this->assertContains('01-02-03-04', $patterns['top_consecutive_quads']);
        $this->assertNotContains('02-03-04-05', $patterns['top_consecutive_quads']);
    }

    public function test_removing_a_draw_undoes_adding_it(): void
    {
        $base = [range(1, 15), range(11, 25)];
        $extra = [2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22, 24, 1, 3, 5];

        $tracker = new ScoringPatternTracker;
        foreach ($base as $draw) {
            $tracker->add($draw);
        }
        $expected = $tracker->snapshot();

        $tracker->add($extra);
        $tracker->remove($extra);

        $this->assertSame($expected, $tracker->snapshot());
    }

    public function test_patterns_before_a_contest_ignore_later_contests(): void
    {
        mt_srand(5);
        foreach (range(1, 12) as $contest) {
            $this->createContest($contest, $this->randomDraw());
        }

        $service = app(LotofacilStatisticsService::class);
        $before = $service->getScoringPatterns(8);
        $contestEight = HistoricalResult::where('contest_number', 8)->first();
        $scoreBefore = app(BetScoringService::class)->calculateScore($contestEight->drawn_numbers, 8);

        // Concursos futuros com o mesmo padrão repetido mudariam os "mais sorteados" da série inteira
        foreach (range(13, 30) as $contest) {
            $this->createContest($contest, range(11, 25));
        }

        $this->assertSame($before, $service->getScoringPatterns(8));
        $this->assertNotSame($service->getScoringPatterns(), $service->getScoringPatterns(8));
        $this->assertSame(
            $scoreBefore['total_score'],
            app(BetScoringService::class)->calculateScore($contestEight->drawn_numbers, 8)['total_score']
        );
    }

    public function test_patterns_before_a_contest_match_forward_counting(): void
    {
        mt_srand(9);
        $draws = [];
        foreach (range(1, 20) as $contest) {
            $draws[$contest] = $this->randomDraw();
            $this->createContest($contest, $draws[$contest]);
        }

        $tracker = new ScoringPatternTracker;
        foreach (range(1, 14) as $contest) {
            $tracker->add($draws[$contest]);
        }

        $this->assertSame($tracker->snapshot(), app(LotofacilStatisticsService::class)->getScoringPatterns(15));
    }

    public function test_recalculation_command_matches_individual_contextual_scores(): void
    {
        mt_srand(13);
        foreach (range(1, 25) as $contest) {
            $this->createContest($contest, $this->randomDraw());
        }

        $this->artisan('lotofacil:calculate-scores')->assertSuccessful();

        $scoringService = app(BetScoringService::class);

        foreach (HistoricalResult::orderBy('contest_number')->get() as $result) {
            $this->assertSame(
                $scoringService->calculateScore($result->drawn_numbers, $result->contest_number)['total_score'],
                $result->score,
                "Concurso {$result->contest_number}"
            );
        }
    }

    public function test_first_contest_has_no_historical_pattern_points(): void
    {
        $first = $this->createContest(1, range(1, 15));
        $this->createContest(2, range(1, 15));

        $result = app(BetScoringService::class)->calculateScore($first->drawn_numbers, 1);

        $this->assertSame(0, $result['details']['top_10_numbers']['points']);
        $this->assertSame(0, $result['details']['top_pairs']['points']);
        $this->assertSame(0, $result['details']['top_trios']['points']);
        $this->assertSame(0, $result['details']['top_consecutive_pairs']['points']);
    }
}
