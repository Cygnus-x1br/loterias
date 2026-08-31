<?php

namespace App\Console\Commands;

use App\Models\HistoricalResult;
use App\Services\BetScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

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
    public function handle(BetScoringService $scoringService)
    {
        $this->info('Limpando scores e ciclos antigos...');
        HistoricalResult::query()->update(['score' => null, 'cycle_number' => null]);
        Cache::forget('historical_average_score');
        Cache::forget('lotofacil_decades_cycle');

        $this->info('Iniciando o cálculo dos scores e ciclos históricos...');

        $results = HistoricalResult::orderBy('contest_number', 'asc')->get();

        $bar = $this->output->createProgressBar(count($results));
        $bar->start();

        $currentCycle = 1;
        $drawnInCycle = [];

        foreach ($results as $result) {
            $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);

            if (is_array($numbers) && count($numbers) === 15) {
                $numbers = array_map('intval', $numbers);

                // 1. Calculate Score
                $scoreResult = $scoringService->calculateScore($numbers, $result->contest_number);
                $result->score = $scoreResult['total_score'];

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

        $this->newLine();
        $this->info('Scores e ciclos calculados com sucesso!');
    }
}
