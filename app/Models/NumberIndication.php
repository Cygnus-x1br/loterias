<?php

namespace App\Models;

use Database\Factories\NumberIndicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Indicação heurística registrada antes de um concurso: as dezenas de maior e de menor
 * pontuação pelo critério da sugestão estatística. Depois do sorteio, guarda quantas de
 * cada grupo saíram, para acompanhar o critério com concursos que ainda não tinham ocorrido.
 */
class NumberIndication extends Model
{
    /** @use HasFactory<NumberIndicationFactory> */
    use HasFactory;

    protected $fillable = [
        'target_contest_number',
        'based_on_contest_number',
        'scores',
        'most_indicated',
        'least_indicated',
        'most_hits',
        'least_hits',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'target_contest_number' => 'integer',
            'based_on_contest_number' => 'integer',
            'scores' => 'array',
            'most_indicated' => 'array',
            'least_indicated' => 'array',
            'most_hits' => 'integer',
            'least_hits' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }
}
