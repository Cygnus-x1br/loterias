<?php

namespace Database\Seeders;

use App\Models\HistoricalResult;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class HistoricalResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = database_path('seeders/historical_results.json');

        if (! File::exists($jsonPath)) {
            $this->command->error("Arquivo {$jsonPath} não encontrado.");

            return;
        }

        $records = json_decode(File::get($jsonPath), true);

        if (! is_array($records)) {
            $this->command->error("Formato JSON inválido em {$jsonPath}.");

            return;
        }

        $this->command->info('Semeando '.count($records).' resultados históricos da Lotofácil...');

        foreach (array_chunk($records, 500) as $chunk) {
            HistoricalResult::upsert($chunk, ['contest_number'], [
                'draw_date',
                'drawn_numbers',
                'drawn_numbers_hash',
                'winners_15_hits',
                'payout_15_hits',
                'winners_14_hits',
                'payout_14_hits',
                'winners_13_hits',
                'payout_13_hits',
                'winners_12_hits',
                'payout_12_hits',
                'winners_11_hits',
                'payout_11_hits',
            ]);
        }

        $this->command->info('Seeder finalizado com sucesso!');
    }
}
