<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Closing extends Model
{
    use HasFactory;

    /**
     * Campos permitidos para preenchimento em massa.
     */
    protected $fillable = [
        'user_id',
        'name',
        'method',
        'base_numbers',
        'bet_size',
        'planned_bets',
        'guarantee',
        'budget',
        'parameters',
        'status',
        'contest_number',
        'draw_date',
        'notes',
    ];

    /**
     * Conversões automáticas dos atributos.
     */
    protected function casts(): array
    {
        return [
            'base_numbers' => 'array',
            'bet_size' => 'integer',
            'planned_bets' => 'integer',
            'guarantee' => 'integer',
            'budget' => 'decimal:2',
            'parameters' => 'array',
            'contest_number' => 'integer',
            'draw_date' => 'date',
        ];
    }

    /**
     * Usuário proprietário do fechamento.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bets(): HasMany
    {
        return $this->hasMany(Bet::class);
    }
}
