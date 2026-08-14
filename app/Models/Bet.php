<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bet extends Model
{
    use HasFactory;

    /**
     * Campos permitidos para preenchimento em massa.
     */
    protected $fillable = [
        'user_id',
        'name',
        'numbers',
        'source',
        'method',
        'status',
        'contest_number',
        'draw_date',
        'hits',
        'notes',
        'closing_id',
    ];

    /**
     * Conversões automáticas dos atributos.
     */
    protected function casts(): array
    {
        return [
            'numbers' => 'array',
            'hits' => 'integer',
            'contest_number' => 'integer',
            'draw_date' => 'date',
        ];
    }

    /**
     * Usuário proprietário da aposta.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closing()
    {
        return $this->belongsTo(Closing::class);
    }
}
