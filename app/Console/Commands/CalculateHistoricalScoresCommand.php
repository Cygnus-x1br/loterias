<?php

namespace App\Console\Commands;

use App\Models\HistoricalResult;
use App\Services\BetScoringService;
use App\Services\LotofacilStatisticsService;
use App\Services\ScoringPatternTracker;
use App\Services\StatisticsCache;
use Illuminate\Console\Command;

class CalculateHistoricalScoresCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lotofacil:calculate-scores';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula e salva o score de todos os resultados históricos da Lotofácil';

    /**
     * Execute the console command.
     */
    public function handle(BetScoringService $scoringService, StatisticsCache $statisticsCache, LotofacilStatisticsService $statisticsService)
    {
        $this->info('Limpando scores e ciclos antigos...');
        HistoricalResult::query()->update(['score' => null, 'cycle_number' => null]);
        $statisticsCache->flush();

        $this->info('Iniciando o cálculo dos scores e ciclos históricos...');

        $results = HistoricalResult::orderBy('contest_number', 'asc')->get();

        $bar = $this->output->createProgressBar(count($results));
        $bar->start();

        $currentCycle = 1;
        $drawnInCycle = [];

        // Padrões acumulados apenas com os concursos já processados (anteriores ao atual),
        // para que nenhum score use informação de concursos futuros
        $patternTracker = new ScoringPatternTracker;
        $recentDraws = [];

        foreach ($results as $result) {
            $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);

            if (is_array($numbers) && count($numbers) === 15) {
                $numbers = array_map('intval', $numbers);

                // 1. Score com os padrões dos concursos anteriores
                $patterns = $patternTracker->snapshot();
                $patterns['temperatures'] = $statisticsService->temperatureClassificationFromDraws($recentDraws);

                $scoreResult = $scoringService->calculateScore($numbers, $result->contest_number, $patterns);
                $result->score = $scoreResult['total_score'];

                $patternTracker->add($numbers);
                array_unshift($recentDraws, $numbers);
                $recentDraws = array_slice($recentDraws, 0, 50);

                // 2. Calculate Cycle
                foreach ($numbers as $num) {
                    $drawnInCycle[(int) $num] = true;
                }

                $result->cycle_number = $currentCycle;
                $result->save();

                // Fechou o ciclo?
                if (count($drawnInCycle) === 25) {
                    $currentCycle++;
                    $drawnInCycle = [];
                }
            }
            $bar->advance();
        }

        $bar->finish();

        $statisticsCache->flush();

        $this->newLine();
        $this->info('Scores e ciclos calculados com sucesso!');
    }
}
