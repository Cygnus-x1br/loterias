<?php

namespace App\Models;

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

    protected $casts = [
        'draw_date' => 'date',
        'drawn_numbers' => 'array',
    ];

    /**
     * Gera o hash das dezenas ordenadas.
     */
    public static function generateDrawnNumbersHash(array $numbers): string
    {
        sort($numbers); // Garante que as dezenas estejam sempre ordenadas

        return hash('sha256', json_encode($numbers));
    }
}
