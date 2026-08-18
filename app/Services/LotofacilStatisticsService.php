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
                if (is_string($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                }
                if (! is_array($drawnNumbersArray)) {
                    continue;
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
                if (is_string($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                }
                if (! is_array($drawnNumbersArray)) {
                    continue;
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
                if (is_string($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                }
                if (! is_array($drawnNumbersArray)) {
                    continue;
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
                if (is_string($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                }
                if (! is_array($drawnNumbersArray)) {
                    continue;
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
                if (is_string($drawnNumbersArray)) {
                    $drawnNumbersArray = json_decode($drawnNumbersArray, true);
                }
                if (! is_array($drawnNumbersArray)) {
                    continue;
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
        return Cache::remember('last_contest', now()->addMinutes(30), function () {
            return HistoricalResult::orderByDesc('contest_number')->first();
        });
    }

    /**
     * Calcula a soma das dezenas sorteadas para um resultado histórico.
     */
    public function calculateDrawnNumbersSum(HistoricalResult $result): int
    {
        return array_sum($result->drawn_numbers);
    }

    /**
     * Retorna estatísticas detalhadas do último concurso:
     * soma, pares, ímpares, repetições do concurso anterior, primos, fibonacci e moldura.
     */
    public function getLastContestFullStatistics(): ?array
    {
        return Cache::remember('last_contest_full_statistics', now()->addMinutes(30), function () {
            $lastTwo = HistoricalResult::orderByDesc('contest_number')->take(2)->get();
            $latest = $lastTwo->first();

            if (! $latest) {
                return null;
            }

            $drawn = is_array($latest->drawn_numbers) ? $latest->drawn_numbers : json_decode($latest->drawn_numbers, true);
            if (! is_array($drawn)) {
                return null;
            }

            $drawn = array_map('intval', $drawn);
            sort($drawn);

            $previous = $lastTwo->count() > 1 ? $lastTwo->last() : null;
            $prevDrawn = [];
            if ($previous) {
                $prevDrawn = is_array($previous->drawn_numbers) ? $previous->drawn_numbers : json_decode($previous->drawn_numbers, true);
                $prevDrawn = is_array($prevDrawn) ? array_map('intval', $prevDrawn) : [];
            }

            $primesConst = [2, 3, 5, 7, 11, 13, 17, 19, 23];
            $fibonacciConst = [1, 2, 3, 5, 8, 13, 21];
            $frameConst = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

            $evens = count(array_filter($drawn, fn ($n) => $n % 2 === 0));
            $odds = count($drawn) - $evens;
            $sum = array_sum($drawn);
            $repeatedFromPrevious = ! empty($prevDrawn) ? count(array_intersect($drawn, $prevDrawn)) : null;
            $primes = count(array_intersect($drawn, $primesConst));
            $fibonacci = count(array_intersect($drawn, $fibonacciConst));
            $frame = count(array_intersect($drawn, $frameConst));
            $center = count($drawn) - $frame;

            return [
                'contest_number' => $latest->contest_number,
                'draw_date' => $latest->draw_date instanceof \DateTimeInterface
                    ? $latest->draw_date->format('d/m/Y')
                    : ($latest->draw_date ? Carbon::parse($latest->draw_date)->format('d/m/Y') : null),
                'drawn_numbers' => $drawn,
                'sum' => $sum,
                'evens' => $evens,
                'odds' => $odds,
                'repeated_from_previous' => $repeatedFromPrevious,
                'previous_contest_number' => $previous?->contest_number,
                'primes' => $primes,
                'fibonacci' => $fibonacci,
                'frame' => $frame,
                'center' => $center,
            ];
        });
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

    /**
     * Retorna a classificação de temperatura (quente, neutra, fria) de cada dezena (1 a 25)
     * baseando-se na amostragem recente de concursos e histórico global.
     *
     * @param  int  $recentContests  Quantidade de concursos recentes para a análise de momento.
     * @return array<int, array{number: int, temperature: string, recent_count: int, total_count: int, delay: int}>
     */
    public function getNumberTemperatureClassification(int $recentContests = 20): array
    {
        $cacheKey = 'number_temperature_classification_'.$recentContests;

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($recentContests) {
            $latestContestNumber = HistoricalResult::max('contest_number') ?? 0;

            // Frequência recente
            $recentFrequencies = $this->getNumberFrequencies($recentContests)->toArray();

            // Frequência global
            $totalFrequencies = $this->getNumberFrequencies(null)->toArray();

            // Calcular atraso (concursos desde o último sorteio de cada dezena)
            $delays = [];
            for ($i = 1; $i <= 25; $i++) {
                $delays[$i] = 0;
            }

            if ($latestContestNumber > 0) {
                $results = HistoricalResult::orderByDesc('contest_number')
                    ->take(50)
                    ->get(['contest_number', 'drawn_numbers']);

                foreach (range(1, 25) as $num) {
                    $found = false;
                    foreach ($results as $index => $row) {
                        $drawn = is_array($row->drawn_numbers) ? $row->drawn_numbers : json_decode($row->drawn_numbers, true);
                        if (is_array($drawn) && in_array($num, $drawn, true)) {
                            $delays[$num] = $index;
                            $found = true;
                            break;
                        }
                    }
                    if (! $found) {
                        $delays[$num] = count($results);
                    }
                }
            }

            // Ordenar por frequência recente (decrescente), desempatando por total global
            $scoredNumbers = [];
            for ($num = 1; $num <= 25; $num++) {
                $recent = $recentFrequencies[$num] ?? 0;
                $total = $totalFrequencies[$num] ?? 0;
                $delay = $delays[$num] ?? 0;

                $scoredNumbers[$num] = [
                    'number' => $num,
                    'recent_count' => $recent,
                    'total_count' => $total,
                    'delay' => $delay,
                ];
            }

            // Ordena do mais frequente recente para o menos frequente
            $sorted = $scoredNumbers;
            uasort($sorted, function ($a, $b) {
                if ($a['recent_count'] === $b['recent_count']) {
                    return $b['total_count'] <=> $a['total_count'];
                }

                return $b['recent_count'] <=> $a['recent_count'];
            });

            $sortedKeys = array_keys($sorted);

            // Top 8 são 'hot' (quentes), Últimos 8 são 'cold' (frias), 9 intermediárias são 'neutral' (médias/mornas)
            $hotKeys = array_slice($sortedKeys, 0, 8);
            $coldKeys = array_slice($sortedKeys, 17, 8);

            $classification = [];
            for ($num = 1; $num <= 25; $num++) {
                $temp = 'neutral';
                if (in_array($num, $hotKeys, true)) {
                    $temp = 'hot';
                } elseif (in_array($num, $coldKeys, true)) {
                    $temp = 'cold';
                }

                $classification[$num] = array_merge($scoredNumbers[$num], [
                    'temperature' => $temp,
                ]);
            }

            return $classification;
        });
    }
}
