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
        $this->info('Iniciando o cálculo dos scores históricos...');

        $results = HistoricalResult::whereNull('score')->get();

        $bar = $this->output->createProgressBar(count($results));
        $bar->start();

        foreach ($results as $result) {
            $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);
            if (is_array($numbers) && count($numbers) === 15) {
                $numbers = array_map('intval', $numbers);
                $scoreResult = $scoringService->calculateScore($numbers);
                $result->score = $scoreResult['total_score'];
                $result->save();
            }
            $bar->advance();
        }

        $bar->finish();

        Cache::forget('historical_average_score');

        $this->newLine();
        $this->info('Scores calculados com sucesso!');
    }
}
