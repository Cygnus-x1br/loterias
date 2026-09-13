<?php

namespace App\Jobs;

use App\Models\Closing;
use App\Models\ConsolidatedAudit;
use App\Services\Betting\CoverageAuditorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateConsolidatedAuditJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // Allow 1 hour for execution if base numbers are close to 25

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ConsolidatedAudit $audit
    ) {}

    /**
     * Execute the job.
     */
    public function handle(CoverageAuditorService $auditor): void
    {
        $this->audit->update(['status' => 'processing']);

        try {
            $baseNumbers = $this->audit->base_numbers;
            $closingIds = $this->audit->closing_ids;

            // Gather all bets
            $allBets = [];
            $closings = Closing::whereIn('id', $closingIds)->with('bets')->get();
            foreach ($closings as $closing) {
                foreach ($closing->bets as $bet) {
                    $allBets[] = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
                }
            }

            $coverageData = $auditor->auditCoverage(
                baseNumbers: $baseNumbers,
                bets: $allBets,
                guaranteePoints: $this->audit->guarantee_points,
                guaranteeHits: $this->audit->guarantee_hits
            );

            $this->audit->update([
                'status' => 'completed',
                'coverage_data' => $coverageData,
            ]);
        } catch (\Exception $e) {
            $this->audit->update([
                'status' => 'failed',
            ]);

            throw $e;
        }
    }
}
