<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsolidatedAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contest_number',
        'closing_ids',
        'base_numbers',
        'total_bets',
        'status',
        'report_type',
        'guarantee_hits',
        'guarantee_points',
        'coverage_data',
    ];

    protected $casts = [
        'closing_ids' => 'array',
        'base_numbers' => 'array',
        'coverage_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
