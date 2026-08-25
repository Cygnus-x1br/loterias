<?php

namespace App\Services;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use Illuminate\Support\Collection;

class FinancialAnalysisService
{
    public function __construct(
        private ?LotteryPrizeCalculatorService $prizeCalculator = null
    ) {
        $this->prizeCalculator = $prizeCalculator ?? app(LotteryPrizeCalculatorService::class);
    }

    /**
     * Retorna o resumo financeiro geral acumulado do usuário.
     * Considera apenas apostas/jogos com status 'placed' ou 'checked'.
     *
     * @return array<string, mixed>
     */
    public function getOverallSummary(int $userId): array
    {
        $bets = Bet::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['placed', 'checked'])
            ->get();

        $contestNumbers = $bets->pluck('contest_number')->filter()->unique()->values()->all();

        /** @var Collection<int, HistoricalResult> $historicalResults */
        $historicalResults = HistoricalResult::query()
            ->whereIn('contest_number', $contestNumbers)
            ->get()
            ->keyBy('contest_number');

        $totalSpent = 0.0;
        $totalReturn = 0.0;
        $totalBets = $bets->count();
        $awardedBets = 0;
        $checkedBets = 0;
        $placedBets = 0;

        foreach ($bets as $bet) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $betSize = count($numbers);
            $cost = $this->prizeCalculator->getBetCost($betSize);
            $totalSpent += $cost;

            if ($bet->status === 'checked') {
                $checkedBets++;
                $hits = (int) $bet->hits;
                $payouts = $this->extractPayouts($historicalResults->get($bet->contest_number));
                $prize = $this->prizeCalculator->calculateTotalPrizeAmount($betSize, $hits, $payouts);
                $totalReturn += $prize;

                if ($prize > 0 || $hits >= 11) {
                    $awardedBets++;
                }
            } else {
                $placedBets++;
            }
        }

        $netProfit = $totalReturn - $totalSpent;
        $roi = $totalSpent > 0 ? (($totalReturn - $totalSpent) / $totalSpent) * 100 : 0.0;
        $winRate = $totalBets > 0 ? ($awardedBets / $totalBets) * 100 : 0.0;

        $totalClosings = Closing::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['placed', 'checked'])
            ->count();

        return [
            'total_spent' => $totalSpent,
            'total_return' => $totalReturn,
            'net_profit' => $netProfit,
            'is_profit' => $netProfit >= 0,
            'roi' => $roi,
            'total_bets' => $totalBets,
            'checked_bets' => $checkedBets,
            'placed_bets' => $placedBets,
            'awarded_bets' => $awardedBets,
            'win_rate' => $winRate,
            'total_closings' => $totalClosings,
            'total_contests' => count($contestNumbers),
        ];
    }

    /**
     * Retorna a lista detalhada e agrupada de gastos e retornos por concurso.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getContestsBreakdown(int $userId, ?int $contestFilter = null, ?string $statusFilter = null): array
    {
        $betsQuery = Bet::query()
            ->where('user_id', $userId)
            ->whereIn('status', ['placed', 'checked'])
            ->whereNotNull('contest_number');

        if ($contestFilter) {
            $betsQuery->where('contest_number', $contestFilter);
        }

        $allBets = $betsQuery->with('closing')->get();

        if ($allBets->isEmpty()) {
            return [];
        }

        $contestNumbers = $allBets->pluck('contest_number')->unique()->sortDesc()->values()->all();

        /** @var Collection<int, HistoricalResult> $historicalResults */
        $historicalResults = HistoricalResult::query()
            ->whereIn('contest_number', $contestNumbers)
            ->get()
            ->keyBy('contest_number');

        $breakdown = [];

        foreach ($contestNumbers as $contestNumber) {
            $contestItem = $this->buildContestItem(
                (int) $contestNumber,
                $allBets->where('contest_number', $contestNumber),
                $historicalResults->get($contestNumber)
            );

            if ($this->shouldIncludeContestStatus($contestItem['status'], $statusFilter)) {
                $breakdown[] = $contestItem;
            }
        }

        return $breakdown;
    }

    /**
     * Constrói o consolidado de um concurso individual.
     */
    private function buildContestItem(int $contestNumber, Collection $contestBets, ?HistoricalResult $hr): array
    {
        $payouts = $this->extractPayouts($hr);

        $closingsResult = $this->buildClosingsData($contestBets, $payouts);
        $individualResult = $this->buildIndividualBetsData($contestBets, $payouts);

        $contestTotalSpent = $closingsResult['total_spent'] + $individualResult['total_spent'];
        $contestTotalReturn = $closingsResult['total_return'] + $individualResult['total_return'];
        $contestNetProfit = $contestTotalReturn - $contestTotalSpent;
        $contestRoi = $contestTotalSpent > 0 ? (($contestTotalReturn - $contestTotalSpent) / $contestTotalSpent) * 100 : 0.0;

        $totalContestBets = $contestBets->count();
        $totalCheckedContestBets = $contestBets->where('status', 'checked')->count();

        $contestStatus = 'placed';
        if ($totalCheckedContestBets === $totalContestBets) {
            $contestStatus = 'checked';
        } elseif ($totalCheckedContestBets > 0) {
            $contestStatus = 'partial';
        }

        $firstBetWithDate = $contestBets->firstWhere('draw_date', '!==', null);
        $drawDate = $hr?->draw_date ?? $firstBetWithDate?->draw_date ?? null;

        return [
            'contest_number' => $contestNumber,
            'draw_date' => $drawDate,
            'is_drawn' => $hr !== null,
            'drawn_numbers' => $this->extractDrawnNumbers($hr),
            'status' => $contestStatus,
            'total_bets' => $totalContestBets,
            'checked_bets' => $totalCheckedContestBets,
            'total_spent' => $contestTotalSpent,
            'total_return' => $contestTotalReturn,
            'net_profit' => $contestNetProfit,
            'is_profit' => $contestNetProfit >= 0,
            'roi' => $contestRoi,
            'closings_count' => count($closingsResult['closings']),
            'closings' => $closingsResult['closings'],
            'individual_bets_count' => count($individualResult['bets']),
            'individual_bets' => $individualResult['bets'],
        ];
    }

    /**
     * Processa os fechamentos vinculados a um concurso.
     */
    private function buildClosingsData(Collection $contestBets, array $payouts): array
    {
        $closingGroups = $contestBets->whereNotNull('closing_id')->groupBy('closing_id');
        $closingsData = [];
        $totalSpent = 0.0;
        $totalReturn = 0.0;

        foreach ($closingGroups as $closingId => $closingBets) {
            $singleClosing = $this->buildSingleClosingData((int) $closingId, $closingBets, $payouts);

            $totalSpent += $singleClosing['total_spent'];
            $totalReturn += $singleClosing['total_return'];
            $closingsData[] = $singleClosing;
        }

        return [
            'closings' => $closingsData,
            'total_spent' => $totalSpent,
            'total_return' => $totalReturn,
        ];
    }

    /**
     * Processa os dados de um único fechamento.
     */
    private function buildSingleClosingData(int $closingId, Collection $closingBets, array $payouts): array
    {
        $closingModel = $closingBets->first()?->closing;
        $closingSpent = 0.0;
        $closingReturn = 0.0;
        $hitsDistribution = [15 => 0, 14 => 0, 13 => 0, 12 => 0, 11 => 0, 'outros' => 0];
        $betsDetail = [];
        $closingCheckedBets = 0;

        foreach ($closingBets as $bet) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $betSize = count($numbers);
            $cost = $this->prizeCalculator->getBetCost($betSize);
            $closingSpent += $cost;

            $prize = 0.0;
            if ($bet->status === 'checked') {
                $closingCheckedBets++;
                $hits = (int) $bet->hits;
                $prize = $this->prizeCalculator->calculateTotalPrizeAmount($betSize, $hits, $payouts);
                $closingReturn += $prize;

                if (isset($hitsDistribution[$hits])) {
                    $hitsDistribution[$hits]++;
                } else {
                    $hitsDistribution['outros']++;
                }
            }

            $betsDetail[] = [
                'id' => $bet->id,
                'numbers' => $numbers,
                'bet_size' => $betSize,
                'cost' => $cost,
                'hits' => $bet->status === 'checked' ? $bet->hits : null,
                'prize' => $prize,
                'status' => $bet->status,
            ];
        }

        $closingNetProfit = $closingReturn - $closingSpent;
        $closingRoi = $closingSpent > 0 ? (($closingReturn - $closingSpent) / $closingSpent) * 100 : 0.0;

        $closingStatus = 'placed';
        if ($closingCheckedBets === $closingBets->count()) {
            $closingStatus = 'checked';
        } elseif ($closingCheckedBets > 0) {
            $closingStatus = 'partial';
        }

        return [
            'id' => $closingId,
            'name' => $closingModel?->name ?? "Fechamento #{$closingId}",
            'method' => $closingModel?->method ?? 'Fechamento',
            'status' => $closingStatus,
            'total_bets' => $closingBets->count(),
            'checked_bets' => $closingCheckedBets,
            'total_spent' => $closingSpent,
            'total_return' => $closingReturn,
            'net_profit' => $closingNetProfit,
            'roi' => $closingRoi,
            'hits_distribution' => $hitsDistribution,
            'bets' => $betsDetail,
        ];
    }

    /**
     * Processa as apostas avulsas (individuais) de um concurso.
     */
    private function buildIndividualBetsData(Collection $contestBets, array $payouts): array
    {
        $individualBetsList = $contestBets->whereNull('closing_id');
        $individualBetsData = [];
        $totalSpent = 0.0;
        $totalReturn = 0.0;

        foreach ($individualBetsList as $bet) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $betSize = count($numbers);
            $cost = $this->prizeCalculator->getBetCost($betSize);
            $totalSpent += $cost;

            $prize = 0.0;
            if ($bet->status === 'checked') {
                $hits = (int) $bet->hits;
                $prize = $this->prizeCalculator->calculateTotalPrizeAmount($betSize, $hits, $payouts);
                $totalReturn += $prize;
            }

            $individualBetsData[] = [
                'id' => $bet->id,
                'name' => $bet->name ?: "Aposta #{$bet->id}",
                'numbers' => $numbers,
                'bet_size' => $betSize,
                'cost' => $cost,
                'hits' => $bet->status === 'checked' ? $bet->hits : null,
                'prize' => $prize,
                'net_profit' => $prize - $cost,
                'status' => $bet->status,
            ];
        }

        return [
            'bets' => $individualBetsData,
            'total_spent' => $totalSpent,
            'total_return' => $totalReturn,
        ];
    }

    /**
     * Extrai o array de payouts de um HistoricalResult.
     */
    private function extractPayouts(?HistoricalResult $hr): array
    {
        if (! $hr) {
            return [];
        }

        return [
            'payout_15_hits' => $hr->payout_15_hits,
            'payout_14_hits' => $hr->payout_14_hits,
            'payout_13_hits' => $hr->payout_13_hits,
            'payout_12_hits' => $hr->payout_12_hits,
            'payout_11_hits' => $hr->payout_11_hits,
        ];
    }

    /**
     * Extrai dezenas ordenadas do HistoricalResult.
     *
     * @return array<int, int>
     */
    private function extractDrawnNumbers(?HistoricalResult $hr): array
    {
        if (! $hr || ! $hr->drawn_numbers) {
            return [];
        }

        $rawDrawn = is_array($hr->drawn_numbers)
            ? $hr->drawn_numbers
            : (json_decode((string) $hr->drawn_numbers, true) ?? []);

        $drawnNumbers = array_map('intval', $rawDrawn);
        sort($drawnNumbers);

        return $drawnNumbers;
    }

    /**
     * Verifica se o concurso atende ao filtro de status.
     */
    private function shouldIncludeContestStatus(string $contestStatus, ?string $statusFilter): bool
    {
        if ($statusFilter === 'checked') {
            return $contestStatus === 'checked';
        }

        if ($statusFilter === 'placed') {
            return $contestStatus !== 'checked';
        }

        return true;
    }
}
