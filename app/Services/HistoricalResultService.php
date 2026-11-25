<?php

namespace App\Services;

use App\Models\HistoricalResult;

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
     * Invalida todas as estatísticas derivadas da série histórica
     * (frequências, temperaturas, ciclos, atrasos, hashes de sorteios, análises).
     */
    public function clearStatisticsCache(): void
    {
        app(StatisticsCache::class)->flush();
    }
}
