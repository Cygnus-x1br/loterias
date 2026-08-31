<?php

namespace App\Services;

use App\Models\HistoricalResult;
use Illuminate\Support\Facades\Cache;

class HistoricalResultService
{
    public function __construct(
        protected ?BetScoringService $scoringService = null
    ) {
        $this->scoringService = $scoringService ?? app(BetScoringService::class);
    }

    /**
     * Cria um novo resultado de sorteio histórico.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): HistoricalResult
    {
        $numbers = array_map('intval', $data['drawn_numbers']);
        sort($numbers);

        $data['drawn_numbers'] = $numbers;
        $data['drawn_numbers_hash'] = HistoricalResult::generateDrawnNumbersHash($numbers);

        if (! isset($data['score']) || $data['score'] === null) {
            try {
                $scoreResult = $this->scoringService->calculateScore($numbers, $data['contest_number'] ?? null);
                $data['score'] = $scoreResult['total_score'];
            } catch (\Throwable) {
                // Deixa o hook do modelo ou nulo caso não seja possível calcular
            }
        }

        $result = HistoricalResult::create($data);

        $this->clearStatisticsCache();

        return $result;
    }

    /**
     * Atualiza um resultado de sorteio histórico existente.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(HistoricalResult $result, array $data): HistoricalResult
    {
        $numbers = array_map('intval', $data['drawn_numbers']);
        sort($numbers);

        $data['drawn_numbers'] = $numbers;
        $data['drawn_numbers_hash'] = HistoricalResult::generateDrawnNumbersHash($numbers);

        if (! isset($data['score']) || $data['score'] === null) {
            try {
                $scoreResult = $this->scoringService->calculateScore($numbers, $result->contest_number);
                $data['score'] = $scoreResult['total_score'];
            } catch (\Throwable) {
                // Mantém score inalterado caso o serviço de score falhe
            }
        }

        $result->update($data);

        $this->clearStatisticsCache();

        return $result;
    }

    /**
     * Exclui um resultado de sorteio histórico.
     */
    public function delete(HistoricalResult $result): bool
    {
        $deleted = $result->delete();

        $this->clearStatisticsCache();

        return (bool) $deleted;
    }

    /**
     * Limpa as chaves de cache utilizadas pelo LotofacilStatisticsService.
     */
    public function clearStatisticsCache(): void
    {
        Cache::forget('last_contest');
        Cache::forget('last_contest_full_statistics');
        Cache::forget('last_contest_with_sum');
        Cache::forget('repeated_draws_analysis');
        Cache::forget('historical_draw_hashes');
        Cache::forget('historical_average_score');
        Cache::forget('lotofacil_advanced_analysis');
        Cache::forget('lotofacil_decades_cycle');
        Cache::forget('lotofacil_current_delay');
        Cache::forget('number_frequencies_all');
        Cache::forget('number_frequencies_10');
        Cache::forget('number_frequencies_25');
        Cache::forget('number_frequencies_50');

        foreach ([10, 15, 20, 25, 30, 50] as $recent) {
            Cache::forget("number_temperature_classification_{$recent}");
        }

        foreach ([10, 25, 50, 100] as $limit) {
            Cache::forget("lotofacil_contests_comparison_{$limit}");
        }

        foreach ([10, 15, 20, 25] as $limit) {
            foreach (['all', 10, 25, 50, 100] as $contestSpan) {
                Cache::forget("most_drawn_numbers_{$limit}_{$contestSpan}");
                Cache::forget("least_drawn_numbers_{$limit}_{$contestSpan}");
                Cache::forget("most_frequent_pairs_{$limit}_{$contestSpan}");
                Cache::forget("most_frequent_trios_{$limit}_{$contestSpan}");
            }
        }
    }
}
