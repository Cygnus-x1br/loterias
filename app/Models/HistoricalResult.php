<?php

namespace App\Models;

use App\Services\BetScoringService;
use App\Services\HistoricalResultService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistoricalResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'contest_number',
        'draw_date',
        'drawn_numbers',
        'drawn_numbers_hash',
        'score',
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
    ];

    protected function casts(): array
    {
        return [
            'draw_date' => 'date',
            'drawn_numbers' => 'array',
            'score' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (HistoricalResult $result) {
            $numbers = is_array($result->drawn_numbers)
                ? $result->drawn_numbers
                : (json_decode((string) $result->drawn_numbers, true) ?? []);

            if ($result->score === null && count($numbers) === 15) {
                try {
                    $scoreData = app(BetScoringService::class)->calculateScore($numbers);
                    $result->score = $scoreData['total_score'];
                } catch (\Throwable) {
                    // Mantém score nulo caso o serviço de scoring não esteja disponível no contexto
                }
            }

            if ($result->cycle_number === null && count($numbers) === 15) {
                $previousResult = HistoricalResult::where('contest_number', '<', $result->contest_number)
                    ->orderBy('contest_number', 'desc')
                    ->first();

                if (! $previousResult) {
                    $result->cycle_number = 1;
                } else {
                    $previousCycle = $previousResult->cycle_number ?? 1;
                    $cycleResults = HistoricalResult::where('cycle_number', $previousCycle)
                        ->where('contest_number', '<', $result->contest_number)
                        ->pluck('drawn_numbers');
                    
                    $drawnInCycle = [];
                    foreach ($cycleResults as $drawn) {
                        $drawnArr = is_array($drawn) ? $drawn : json_decode((string) $drawn, true);
                        if (is_array($drawnArr)) {
                            foreach ($drawnArr as $num) {
                                $drawnInCycle[(int)$num] = true;
                            }
                        }
                    }

                    if (count($drawnInCycle) === 25) {
                        $result->cycle_number = $previousCycle + 1;
                    } else {
                        $result->cycle_number = $previousCycle;
                    }
                }
            }
        });

        static::saved(function () {
            try {
                app(HistoricalResultService::class)->clearStatisticsCache();
            } catch (\Throwable) {
                // Silencia se o serviço de cache não puder ser instanciado
            }
        });

        static::deleted(function () {
            try {
                app(HistoricalResultService::class)->clearStatisticsCache();
            } catch (\Throwable) {
                // Silencia se o serviço de cache não puder ser instanciado
            }
        });
    }

    /**
     * Gera o hash das dezenas ordenadas.
     */
    public static function generateDrawnNumbersHash(array $numbers): string
    {
        sort($numbers); // Garante que as dezenas estejam sempre ordenadas

        return hash('sha256', json_encode($numbers));
    }
}
