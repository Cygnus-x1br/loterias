<?php

namespace Tests\Feature\Services;

use App\Models\HistoricalResult;
use App\Services\BetScoringService;
use App\Services\HistoricalResultService;
use App\Services\LotofacilStatisticsService;
use App\Services\StatisticsCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StatisticsCacheTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, int>  $numbers
     */
    private function createContest(int $contestNumber, array $numbers): HistoricalResult
    {
        return HistoricalResult::create([
            'contest_number' => $contestNumber,
            'draw_date' => now()->subDays(100 - $contestNumber)->toDateString(),
            'drawn_numbers' => $numbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
            'score' => 500,
        ]);
    }

    public function test_flush_invalidates_every_cached_statistic(): void
    {
        $cache = app(StatisticsCache::class);

        $this->assertSame('antigo', $cache->remember('qualquer_chave_123', 3600, fn () => 'antigo'));
        $this->assertSame('antigo', $cache->remember('qualquer_chave_123', 3600, fn () => 'novo'));

        $cache->flush();

        $this->assertSame('novo', $cache->remember('qualquer_chave_123', 3600, fn () => 'novo'));
    }

    public function test_frequencies_of_any_window_refresh_after_a_new_contest(): void
    {
        $this->createContest(1, range(1, 15));
        $this->createContest(2, range(2, 16));

        $service = app(LotofacilStatisticsService::class);

        $this->assertSame(1, $service->getNumberFrequencies(20)[1]);
        $this->assertSame(1, $service->getNumberTemperatureClassification(20)[1]['recent_count']);

        $this->createContest(3, range(1, 15));

        $this->assertSame(2, $service->getNumberFrequencies(20)[1]);
        $this->assertSame(2, $service->getNumberTemperatureClassification(20)[1]['recent_count']);
    }

    public function test_contextual_cycle_analysis_refreshes_after_editing_a_past_contest(): void
    {
        $this->createContest(1, range(1, 15));
        $second = $this->createContest(2, range(2, 16));
        $this->createContest(3, range(5, 19));

        $service = app(LotofacilStatisticsService::class);

        $this->assertSame(range(17, 25), $service->getDecadesCycleAnalysis(3)['missing_numbers']);

        app(HistoricalResultService::class)->update($second, [
            'contest_number' => 2,
            'draw_date' => $second->draw_date->toDateString(),
            'drawn_numbers' => range(11, 25),
        ]);

        $this->assertSame([], $service->getDecadesCycleAnalysis(3)['missing_numbers']);
    }

    public function test_score_counts_consecutive_sequences_without_warming_the_analysis_page(): void
    {
        $this->createContest(1, range(1, 15));
        $this->createContest(2, [1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 21, 23, 24, 25]);

        // Antes, esta pontuação só existia se a tela de Análises tivesse preenchido o cache
        Cache::flush();

        $result = app(BetScoringService::class)->calculateScore([1, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 21, 22, 24, 25]);

        $this->assertGreaterThan(0, $result['details']['top_consecutive_pairs']['value']);
        $this->assertGreaterThan(0, $result['details']['top_consecutive_pairs']['points']);
    }
}
