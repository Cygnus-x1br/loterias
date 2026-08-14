<?php

namespace App\Services;

use App\Models\HistoricalResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LotofacilStatisticsService
{
    /**
     * Retorna as dezenas mais sorteadas.
     *
     * @param  int  $limit  Quantidade de dezenas a retornar.
     * @param  int|null  $lastContests  Considerar apenas os últimos N concursos.
     * @return Collection<int, int> Chave: dezena, Valor: frequência.
     */
    public function getMostDrawnNumbers(int $limit = 10, ?int $lastContests = null): Collection
    {
        $cacheKey = 'most_drawn_numbers_'.$limit.'_'.($lastContests ?? 'all');

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
            $query = HistoricalResult::query();

            if ($lastContests) {
                $latestContestNumber = HistoricalResult::max('contest_number');
                if ($latestContestNumber) {
                    $query->where('contest_number', '>=', $latestContestNumber - $lastContests + 1);
                }
            }

            $results = $query->pluck('drawn_numbers');

            $allNumbers = [];
            foreach ($results as $drawnNumbersArray) {
                if (! is_array($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                    if (! is_array($drawnNumbersArray)) {
                        continue;
                    }
                }
                foreach ($drawnNumbersArray as $number) {
                    $allNumbers[] = $number;
                }
            }

            return collect($allNumbers)
                ->countBy()
                ->sortDesc()
                ->take($limit)
                ->toArray();
        });

        return collect($data);
    }

    /**
     * Retorna as dezenas menos sorteadas.
     *
     * @param  int  $limit  Quantidade de dezenas a retornar.
     * @param  int|null  $lastContests  Considerar apenas os últimos N concursos.
     * @return Collection<int, int> Chave: dezena, Valor: frequência.
     */
    public function getLeastDrawnNumbers(int $limit = 10, ?int $lastContests = null): Collection
    {
        $cacheKey = 'least_drawn_numbers_'.$limit.'_'.($lastContests ?? 'all');

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
            $query = HistoricalResult::query();

            if ($lastContests) {
                $latestContestNumber = HistoricalResult::max('contest_number');
                if ($latestContestNumber) {
                    $query->where('contest_number', '>=', $latestContestNumber - $lastContests + 1);
                }
            }

            $results = $query->pluck('drawn_numbers');

            $allNumbers = [];
            foreach ($results as $drawnNumbersArray) {
                if (! is_array($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                    if (! is_array($drawnNumbersArray)) {
                        continue;
                    }
                }
                foreach ($drawnNumbersArray as $number) {
                    $allNumbers[] = $number;
                }
            }

            return collect($allNumbers)
                ->countBy()
                ->sort() // Sort ascending for least drawn
                ->take($limit)
                ->toArray();
        });

        return collect($data);
    }

    /**
     * Retorna a frequência de todas as dezenas (1 a 25).
     *
     * @param  int|null  $lastContests  Considerar apenas os últimos N concursos.
     * @return Collection<int, int> Chave: dezena, Valor: frequência.
     */
    public function getNumberFrequencies(?int $lastContests = null): Collection
    {
        $cacheKey = 'number_frequencies_'.($lastContests ?? 'all');

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($lastContests) {
            $query = HistoricalResult::query();

            if ($lastContests) {
                $latestContestNumber = HistoricalResult::max('contest_number');
                if ($latestContestNumber) {
                    $query->where('contest_number', '>=', $latestContestNumber - $lastContests + 1);
                }
            }

            $results = $query->pluck('drawn_numbers');

            $allNumbers = [];
            foreach ($results as $drawnNumbersArray) {
                if (! is_array($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                    if (! is_array($drawnNumbersArray)) {
                        continue;
                    }
                }
                foreach ($drawnNumbersArray as $number) {
                    $allNumbers[] = $number;
                }
            }

            $frequencies = collect($allNumbers)->countBy();

            // Garante que todas as dezenas de 1 a 25 estejam presentes, mesmo que com frequência 0
            for ($i = 1; $i <= 25; $i++) {
                if (! $frequencies->has($i)) {
                    $frequencies->put($i, 0);
                }
            }

            return $frequencies->sortKeys()->toArray(); // Ordena pelas dezenas (1, 2, 3...)
        });

        return collect($data);
    }

    /**
     * Retorna os pares de dezenas mais frequentes.
     *
     * @param  int  $limit  Quantidade de pares a retornar.
     * @param  int|null  $lastContests  Considerar apenas os últimos N concursos.
     * @return Collection<string, int> Chave: par (ex: "01-02"), Valor: frequência.
     */
    public function getMostFrequentPairs(int $limit = 10, ?int $lastContests = null): Collection
    {
        $cacheKey = 'most_frequent_pairs_'.$limit.'_'.($lastContests ?? 'all');

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
            $query = HistoricalResult::query();

            if ($lastContests) {
                $latestContestNumber = HistoricalResult::max('contest_number');
                if ($latestContestNumber) {
                    $query->where('contest_number', '>=', $latestContestNumber - $lastContests + 1);
                }
            }

            $results = $query->pluck('drawn_numbers');

            $pairCounts = [];
            foreach ($results as $drawnNumbersArray) {
                if (! is_array($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                    if (! is_array($drawnNumbersArray)) {
                        continue;
                    }
                }
                sort($drawnNumbersArray); // Garante que o par "01-02" seja o mesmo que "02-01"
                $count = count($drawnNumbersArray);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        $pair = sprintf('%02d-%02d', $drawnNumbersArray[$i], $drawnNumbersArray[$j]);
                        $pairCounts[$pair] = ($pairCounts[$pair] ?? 0) + 1;
                    }
                }
            }

            return collect($pairCounts)
                ->sortDesc()
                ->take($limit)
                ->toArray();
        });

        return collect($data);
    }

    /**
     * Retorna os trios de dezenas mais frequentes.
     *
     * @param  int  $limit  Quantidade de trios a retornar.
     * @param  int|null  $lastContests  Considerar apenas os últimos N concursos.
     * @return Collection<string, int> Chave: trio (ex: "01-02-03"), Valor: frequência.
     */
    public function getMostFrequentTrios(int $limit = 10, ?int $lastContests = null): Collection
    {
        $cacheKey = 'most_frequent_trios_'.$limit.'_'.($lastContests ?? 'all');

        $data = Cache::remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
            $query = HistoricalResult::query();

            if ($lastContests) {
                $latestContestNumber = HistoricalResult::max('contest_number');
                if ($latestContestNumber) {
                    $query->where('contest_number', '>=', $latestContestNumber - $lastContests + 1);
                }
            }

            $results = $query->pluck('drawn_numbers');

            $trioCounts = [];
            foreach ($results as $drawnNumbersArray) {
                if (! is_array($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                    if (! is_array($drawnNumbersArray)) {
                        continue;
                    }
                }
                sort($drawnNumbersArray);
                $count = count($drawnNumbersArray);
                for ($i = 0; $i < $count; $i++) {
                    for ($j = $i + 1; $j < $count; $j++) {
                        for ($k = $j + 1; $k < $count; $k++) {
                            $trio = sprintf('%02d-%02d-%02d', $drawnNumbersArray[$i], $drawnNumbersArray[$j], $drawnNumbersArray[$k]);
                            $trioCounts[$trio] = ($trioCounts[$trio] ?? 0) + 1;
                        }
                    }
                }
            }

            return collect($trioCounts)
                ->sortDesc()
                ->take($limit)
                ->toArray();
        });

        return collect($data);
    }

    /**
     * Retorna o último concurso e suas dezenas.
     */
    public function getLastContest(): ?HistoricalResult
    {
        $data = Cache::remember('last_contest', now()->addMinutes(30), function () {
            $lastContest = HistoricalResult::orderByDesc('contest_number')->first();

            return $lastContest ? $lastContest->toArray() : null;
        });

        return $data ? (new HistoricalResult)->newFromBuilder($data) : null;
    }

    /**
     * Calcula a soma das dezenas sorteadas para um resultado histórico.
     */
    public function calculateDrawnNumbersSum(HistoricalResult $result): int
    {
        return array_sum($result->drawn_numbers);
    }

    /**
     * Retorna o último concurso e suas dezenas, incluindo a soma.
     *
     * @return array|null Retorna um array com o resultado e a soma, ou null.
     */
    public function getLastContestWithSum(): ?array
    {
        return Cache::remember('last_contest_with_sum', now()->addMinutes(30), function () {
            $lastContest = HistoricalResult::orderByDesc('contest_number')->first();
            if ($lastContest) {
                return [
                    'result' => $lastContest->toArray(), // Convertendo o modelo para array aqui
                    'sum' => $this->calculateDrawnNumbersSum($lastContest),
                ];
            }

            return null;
        });
    }

    /**
     * Avalia repetição de todas as 15 dezenas entre concursos da história.
     *
     * @return array{total_contests: int, has_repeated: bool, repeated_groups_count: int, repetitions: array}
     */
    public function checkRepeatedDraws(): array
    {
        return Cache::remember('repeated_draws_analysis', now()->addHours(6), function () {
            $results = HistoricalResult::orderBy('contest_number')
                ->get(['id', 'contest_number', 'draw_date', 'drawn_numbers', 'drawn_numbers_hash']);

            $totalContests = $results->count();

            // Agrupa por hash das dezenas ordenadas
            $groupedByHash = $results->groupBy('drawn_numbers_hash');

            $repetitions = [];
            foreach ($groupedByHash as $hash => $group) {
                if ($group->count() > 1) {
                    $firstResult = $group->first();
                    $repetitions[] = [
                        'drawn_numbers' => $firstResult->drawn_numbers,
                        'total_occurrences' => $group->count(),
                        'contests' => $group->map(function ($res) {
                            return [
                                'contest_number' => $res->contest_number,
                                'draw_date' => $res->draw_date instanceof \DateTimeInterface
                                    ? $res->draw_date->format('d/m/Y')
                                    : ($res->draw_date ? Carbon::parse($res->draw_date)->format('d/m/Y') : null),
                            ];
                        })->values()->toArray(),
                    ];
                }
            }

            return [
                'total_contests' => $totalContests,
                'has_repeated' => count($repetitions) > 0,
                'repeated_groups_count' => count($repetitions),
                'repetitions' => $repetitions,
            ];
        });
    }

    /**
     * Retorna um array associativo com todos os hashes (sha256) das dezenas já sorteadas na história.
     *
     * @return array<string, bool>
     */
    public function getHistoricalDrawHashes(): array
    {
        return Cache::remember('historical_draw_hashes', now()->addHours(6), function () {
            $hashes = HistoricalResult::query()
                ->whereNotNull('drawn_numbers_hash')
                ->pluck('drawn_numbers_hash')
                ->all();

            return array_fill_keys($hashes, true);
        });
    }
}
