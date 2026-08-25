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

    /**
     * Calcula a média de Score histórico de todos os sorteios registrados.
     *
     * @return array{total_contests: int, average_score: float, min_score: int, max_score: int, classification: string, color: string}
     */
    public function getHistoricalAverageScore(): array
    {
        return Cache::remember('historical_average_score', now()->addHours(6), function () {
            $results = HistoricalResult::query()
                ->orderBy('contest_number')
                ->get(['id', 'contest_number', 'drawn_numbers']);

            $totalContests = $results->count();

            if ($totalContests === 0) {
                return [
                    'total_contests' => 0,
                    'average_score' => 0.0,
                    'min_score' => 0,
                    'max_score' => 0,
                    'classification' => '—',
                    'color' => 'slate',
                ];
            }

            // Pré-carregar dados estáticos para cálculo em memória super rápido
            $frameSet = array_flip([1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25]);
            $top10Numbers = array_flip($this->getMostDrawnNumbers(10)->keys()->toArray());
            $top10Pairs = array_flip($this->getMostFrequentPairs(10)->keys()->toArray());
            $top10Trios = array_flip($this->getMostFrequentTrios(10)->keys()->toArray());

            $analysisCache = Cache::get('lotofacil_advanced_analysis');
            $consecutiveTop = $analysisCache['consecutive_sequences'] ?? [];
            $topPairsCons = array_flip(array_keys($consecutiveTop['top_pairs_consecutive'] ?? []));
            $topTriosCons = array_flip(array_keys($consecutiveTop['top_trios_consecutive'] ?? []));
            $topQuadsCons = array_flip(array_keys($consecutiveTop['top_quads_consecutive'] ?? []));

            $lastContestData = $this->getLastContestWithSum();
            $lastNumbers = $lastContestData['result']['drawn_numbers'] ?? [];
            if (is_string($lastNumbers)) {
                $lastNumbers = json_decode($lastNumbers, true) ?? [];
            }
            $lastNumbersSet = array_flip(array_map('intval', (array) $lastNumbers));

            $totalScoreSum = 0;
            $minScore = PHP_INT_MAX;
            $maxScore = 0;

            foreach ($results as $item) {
                $numbers = is_array($item->drawn_numbers)
                    ? $item->drawn_numbers
                    : (json_decode((string) $item->drawn_numbers, true) ?? []);

                if (! is_array($numbers) || count($numbers) !== 15) {
                    continue;
                }

                $numbers = array_map('intval', $numbers);
                sort($numbers);

                $totalScore = 0;

                // 1. Soma
                $sum = array_sum($numbers);
                if ($sum >= 180 && $sum <= 220) {
                    $totalScore += 200;
                } elseif (($sum >= 170 && $sum <= 179) || ($sum >= 221 && $sum <= 230)) {
                    $totalScore += 100;
                }

                // 2. Paridade
                $evens = 0;
                $frame = 0;
                foreach ($numbers as $num) {
                    if ($num % 2 === 0) {
                        $evens++;
                    }
                    if (isset($frameSet[$num])) {
                        $frame++;
                    }
                }
                $odds = 15 - $evens;
                if (($evens === 7 && $odds === 8) || ($evens === 8 && $odds === 7)) {
                    $totalScore += 200;
                } elseif (($evens === 6 && $odds === 9) || ($evens === 9 && $odds === 6)) {
                    $totalScore += 100;
                }

                // 3. Moldura
                $center = 15 - $frame;
                if (($frame === 9 && $center === 6) || ($frame === 10 && $center === 5)) {
                    $totalScore += 200;
                } elseif (($frame === 8 && $center === 7) || ($frame === 11 && $center === 4)) {
                    $totalScore += 100;
                }

                // 4. Repetição Último Concurso
                if (! empty($lastNumbersSet)) {
                    $repCount = 0;
                    foreach ($numbers as $num) {
                        if (isset($lastNumbersSet[$num])) {
                            $repCount++;
                        }
                    }
                    if (in_array($repCount, [8, 9, 10], true)) {
                        $totalScore += 150;
                    } elseif (in_array($repCount, [7, 11], true)) {
                        $totalScore += 75;
                    }
                }

                // 5. Ineditismo (concurso histórico tem base pontual padrão 50 de pontuação de estrutura)
                $totalScore += 50;

                // 6. Top 10 Dezenas
                $top10Count = 0;
                foreach ($numbers as $num) {
                    if (isset($top10Numbers[$num])) {
                        $top10Count++;
                    }
                }
                if ($top10Count >= 6 && $top10Count <= 8) {
                    $totalScore += 50;
                } elseif (in_array($top10Count, [4, 5, 9, 10], true)) {
                    $totalScore += 25;
                }

                // 7. Pares Top 10
                $pairsCount = 0;
                for ($i = 0; $i < 15; $i++) {
                    for ($j = $i + 1; $j < 15; $j++) {
                        $pairStr = sprintf('%02d-%02d', $numbers[$i], $numbers[$j]);
                        if (isset($top10Pairs[$pairStr])) {
                            $pairsCount++;
                        }
                    }
                }
                if ($pairsCount >= 3) {
                    $totalScore += 30;
                } elseif ($pairsCount >= 1) {
                    $totalScore += 15;
                }

                // 8. Trios Top 10
                $triosCount = 0;
                for ($i = 0; $i < 15; $i++) {
                    for ($j = $i + 1; $j < 15; $j++) {
                        for ($k = $j + 1; $k < 15; $k++) {
                            $trioStr = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$j], $numbers[$k]);
                            if (isset($top10Trios[$trioStr])) {
                                $triosCount++;
                            }
                        }
                    }
                }
                if ($triosCount >= 2) {
                    $totalScore += 30;
                } elseif ($triosCount === 1) {
                    $totalScore += 15;
                }

                // 9, 10, 11. Consecutivos
                if (! empty($topPairsCons) || ! empty($topTriosCons) || ! empty($topQuadsCons)) {
                    $myPairsCons = 0;
                    $myTriosCons = 0;
                    $myQuadsCons = 0;

                    for ($i = 0; $i < 14; $i++) {
                        if ($numbers[$i + 1] === $numbers[$i] + 1) {
                            $pairStr = sprintf('%02d-%02d', $numbers[$i], $numbers[$i + 1]);
                            if (isset($topPairsCons[$pairStr])) {
                                $myPairsCons++;
                            }

                            if ($i < 13 && $numbers[$i + 2] === $numbers[$i] + 2) {
                                $trioStr = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2]);
                                if (isset($topTriosCons[$trioStr])) {
                                    $myTriosCons++;
                                }

                                if ($i < 12 && $numbers[$i + 3] === $numbers[$i] + 3) {
                                    $quadStr = sprintf('%02d-%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2], $numbers[$i + 3]);
                                    if (isset($topQuadsCons[$quadStr])) {
                                        $myQuadsCons++;
                                    }
                                }
                            }
                        }
                    }

                    if ($myPairsCons >= 3) {
                        $totalScore += 30;
                    } elseif ($myPairsCons >= 1) {
                        $totalScore += 15;
                    }

                    if ($myTriosCons >= 2) {
                        $totalScore += 30;
                    } elseif ($myTriosCons === 1) {
                        $totalScore += 15;
                    }

                    if ($myQuadsCons >= 1) {
                        $totalScore += 30;
                    }
                }

                $totalScoreSum += $totalScore;
                if ($totalScore < $minScore) {
                    $minScore = $totalScore;
                }
                if ($totalScore > $maxScore) {
                    $maxScore = $totalScore;
                }
            }

            $averageScore = round($totalScoreSum / $totalContests, 1);

            $classification = '🔴 Fora da Curva';
            $color = 'rose';
            if ($averageScore >= 800) {
                $classification = '🟢 Excelente';
                $color = 'emerald';
            } elseif ($averageScore >= 600) {
                $classification = '🟡 Boa';
                $color = 'amber';
            } elseif ($averageScore >= 400) {
                $classification = '🟠 Regular';
                $color = 'orange';
            }

            return [
                'total_contests' => $totalContests,
                'average_score' => $averageScore,
                'min_score' => $minScore === PHP_INT_MAX ? 0 : $minScore,
                'max_score' => $maxScore,
                'classification' => $classification,
                'color' => $color,
            ];
        });
    }

    /**
     * Calcula as cotas de repetição baseadas no atraso (delay) do histórico recente.
     * Dá preferência para repetições que estão mais "atrasadas" no histórico.
     */
    public function calculateRepetitionQuotas(int $minRepeated, int $maxRepeated, int $totalGames, int $historyLimit = 50): array
    {
        $cacheKey = "repetition_quotas_{$minRepeated}_{$maxRepeated}_{$totalGames}_{$historyLimit}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($minRepeated, $maxRepeated, $totalGames, $historyLimit) {
            $results = HistoricalResult::orderByDesc('contest_number')
                ->take($historyLimit + 1)
                ->get(['contest_number', 'drawn_numbers'])
                ->reverse()
                ->values();

            $history = [];
            for ($i = 1; $i < $results->count(); $i++) {
                $prev = is_array($results[$i - 1]->drawn_numbers) ? $results[$i - 1]->drawn_numbers : json_decode((string) $results[$i - 1]->drawn_numbers, true);
                $curr = is_array($results[$i]->drawn_numbers) ? $results[$i]->drawn_numbers : json_decode((string) $results[$i]->drawn_numbers, true);

                if (is_array($prev) && is_array($curr)) {
                    $repeated = count(array_intersect($prev, $curr));
                    $history[] = $repeated;
                }
            }

            // Inverte o histórico para que o índice 0 seja o sorteio mais recente
            $history = array_reverse($history);

            $delays = [];
            for ($i = $minRepeated; $i <= $maxRepeated; $i++) {
                $delays[$i] = count($history); // Máximo possível como padrão (nunca saiu na janela)
                foreach ($history as $index => $rep) {
                    if ($rep === $i) {
                        $delays[$i] = $index;
                        break;
                    }
                }
            }

            // Cálculo do peso: Peso = Delay + 1 (evita peso 0 para o mais recente)
            $weights = [];
            $totalWeight = 0;
            foreach ($delays as $rep => $delay) {
                $weight = $delay + 1;
                $weights[$rep] = $weight;
                $totalWeight += $weight;
            }

            $quotas = [];
            $remainingGames = $totalGames;
            $remainders = [];

            if ($totalWeight > 0) {
                foreach ($weights as $rep => $weight) {
                    $exactQuota = ($weight / $totalWeight) * $totalGames;
                    $quotas[$rep] = (int) floor($exactQuota);
                    $remainders[$rep] = $exactQuota - $quotas[$rep];
                    $remainingGames -= $quotas[$rep];
                }

                // Distribui os jogos restantes com base no maior resto decimal
                arsort($remainders);
                foreach ($remainders as $rep => $remainder) {
                    if ($remainingGames > 0) {
                        $quotas[$rep]++;
                        $remainingGames--;
                    } else {
                        break;
                    }
                }
            } else {
                // Fallback de distribuição igualitária
                $options = range($minRepeated, $maxRepeated);
                foreach ($options as $opt) {
                    $quotas[$opt] = 0;
                }
                while ($remainingGames > 0) {
                    foreach ($options as $opt) {
                        if ($remainingGames > 0) {
                            $quotas[$opt]++;
                            $remainingGames--;
                        }
                    }
                }
            }

            return $quotas;
        });
    }

    /**
     * Retorna a análise comparativa detalhada dos últimos N concursos (10, 25, 50, 100).
     *
     * @param  int  $limit  Quantidade de concursos (10, 25, 50, 100).
     * @return array<string, mixed>
     */
    public function getContestsComparisonAnalysis(int $limit = 25): array
    {
        $limit = in_array($limit, [10, 25, 50, 100], true) ? $limit : 25;
        $cacheKey = "lotofacil_contests_comparison_{$limit}";

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($limit) {
            $rawResults = HistoricalResult::query()
                ->orderBy('contest_number', 'desc')
                ->take($limit + 1)
                ->get(['id', 'contest_number', 'draw_date', 'drawn_numbers']);

            if ($rawResults->isEmpty()) {
                return [
                    'limit' => $limit,
                    'total_analyzed' => 0,
                    'last_contest' => null,
                    'contests' => [],
                    'averages' => [],
                    'temperature_map' => [],
                    'hot_numbers' => [],
                    'neutral_numbers' => [],
                    'cold_numbers' => [],
                ];
            }

            $results = $rawResults->take($limit);
            $temperatureMap = $this->calculatePeriodTemperatures($results);
            $contestsList = $this->buildContestsComparisonList($rawResults, $limit, $temperatureMap);
            $averages = $this->calculateComparisonAverages($contestsList);

            $hotNumbers = [];
            $neutralNumbers = [];
            $coldNumbers = [];
            foreach ($temperatureMap as $num => $info) {
                if ($info['temperature'] === 'hot') {
                    $hotNumbers[] = $num;
                } elseif ($info['temperature'] === 'cold') {
                    $coldNumbers[] = $num;
                } else {
                    $neutralNumbers[] = $num;
                }
            }

            return [
                'limit' => $limit,
                'total_analyzed' => $results->count(),
                'last_contest' => $contestsList[0] ?? null,
                'contests' => $contestsList,
                'averages' => $averages,
                'temperature_map' => $temperatureMap,
                'hot_numbers' => $hotNumbers,
                'neutral_numbers' => $neutralNumbers,
                'cold_numbers' => $coldNumbers,
            ];
        });
    }

    /**
     * Calcula as temperaturas das 25 dezenas com base nas frequências do período selecionado.
     *
     * @param  Collection<int, HistoricalResult>  $results
     * @return array<int, array{frequency: int, percentage: float, temperature: string}>
     */
    private function calculatePeriodTemperatures(Collection $results): array
    {
        $totalContests = $results->count();
        $frequencies = array_fill(1, 25, 0);

        foreach ($results as $res) {
            $numbers = is_array($res->drawn_numbers) ? $res->drawn_numbers : json_decode((string) $res->drawn_numbers, true);
            if (! is_array($numbers)) {
                continue;
            }
            foreach ($numbers as $num) {
                $numInt = (int) $num;
                if ($numInt >= 1 && $numInt <= 25) {
                    $frequencies[$numInt]++;
                }
            }
        }

        $sortedFreqs = $frequencies;
        sort($sortedFreqs);
        $count = count($sortedFreqs);
        $coldThreshold = $sortedFreqs[(int) floor($count * 0.33)] ?? 0;
        $hotThreshold = $sortedFreqs[(int) floor($count * 0.67)] ?? 0;

        $map = [];
        for ($i = 1; $i <= 25; $i++) {
            $freq = $frequencies[$i];
            $temp = 'neutral';
            if ($freq >= $hotThreshold && $freq > 0) {
                $temp = 'hot';
            } elseif ($freq <= $coldThreshold) {
                $temp = 'cold';
            }

            $map[$i] = [
                'frequency' => $freq,
                'percentage' => $totalContests > 0 ? round(($freq / $totalContests) * 100, 1) : 0.0,
                'temperature' => $temp,
            ];
        }

        return $map;
    }

    /**
     * Constrói a lista estruturada de concursos para a tabela comparativa.
     */
    private function buildContestsComparisonList(Collection $rawResults, int $limit, array $temperatureMap): array
    {
        $primesConst = [2, 3, 5, 7, 11, 13, 17, 19, 23];
        $fibonacciConst = [1, 2, 3, 5, 8, 13, 21];
        $frameConst = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

        $contestsList = [];
        $resultsArray = $rawResults->values();
        $targetCount = min($limit, $resultsArray->count());

        $betScoringService = null;
        try {
            $betScoringService = app(BetScoringService::class);
        } catch (\Throwable) {
            $betScoringService = null;
        }

        for ($idx = 0; $idx < $targetCount; $idx++) {
            $current = $resultsArray->get($idx);
            $previous = $resultsArray->get($idx + 1);

            $drawn = is_array($current->drawn_numbers) ? $current->drawn_numbers : json_decode((string) $current->drawn_numbers, true);
            $drawn = is_array($drawn) ? array_map('intval', $drawn) : [];
            sort($drawn);

            $prevDrawn = [];
            if ($previous) {
                $rawPrev = is_array($previous->drawn_numbers) ? $previous->drawn_numbers : json_decode((string) $previous->drawn_numbers, true);
                $prevDrawn = is_array($rawPrev) ? array_map('intval', $rawPrev) : [];
            }

            $drawnSet = array_flip($drawn);

            // Grade das 25 dezenas com status
            $numbersGrid = [];
            for ($num = 1; $num <= 25; $num++) {
                $isDrawn = isset($drawnSet[$num]);
                $numbersGrid[$num] = [
                    'number' => $num,
                    'is_drawn' => $isDrawn,
                    'temperature' => $temperatureMap[$num]['temperature'] ?? 'neutral',
                    'frequency' => $temperatureMap[$num]['frequency'] ?? 0,
                ];
            }

            $evens = count(array_filter($drawn, fn ($n) => $n % 2 === 0));
            $odds = count($drawn) - $evens;
            $sum = array_sum($drawn);
            $primes = count(array_intersect($drawn, $primesConst));
            $fibonacci = count(array_intersect($drawn, $fibonacciConst));
            $frame = count(array_intersect($drawn, $frameConst));
            $center = count($drawn) - $frame;
            $repeatedFromPrevious = ! empty($prevDrawn) ? count(array_intersect($drawn, $prevDrawn)) : null;

            $scoreData = null;
            if ($betScoringService && count($drawn) === 15) {
                try {
                    $scoreData = $betScoringService->calculateScore($drawn);
                } catch (\Throwable) {
                    $scoreData = null;
                }
            }

            $formattedDate = null;
            if ($current->draw_date instanceof \DateTimeInterface) {
                $formattedDate = $current->draw_date->format('d/m/Y');
            } elseif ($current->draw_date) {
                $formattedDate = Carbon::parse($current->draw_date)->format('d/m/Y');
            }

            $contestsList[] = [
                'contest_number' => $current->contest_number,
                'draw_date' => $formattedDate,
                'drawn_numbers' => $drawn,
                'numbers_grid' => $numbersGrid,
                'sum' => $sum,
                'evens' => $evens,
                'odds' => $odds,
                'primes' => $primes,
                'fibonacci' => $fibonacci,
                'frame' => $frame,
                'center' => $center,
                'repeated_from_previous' => $repeatedFromPrevious,
                'previous_contest_number' => $previous?->contest_number,
                'score' => $scoreData['total_score'] ?? null,
                'score_classification' => $scoreData['classification'] ?? '—',
                'score_color' => $scoreData['color'] ?? 'slate',
            ];
        }

        return $contestsList;
    }

    /**
     * Calcula as médias das métricas estatísticas dos concursos listados.
     */
    private function calculateComparisonAverages(array $contestsList): array
    {
        $count = count($contestsList);
        if ($count === 0) {
            return [];
        }

        $sumTotal = 0;
        $evensTotal = 0;
        $oddsTotal = 0;
        $primesTotal = 0;
        $fibonacciTotal = 0;
        $frameTotal = 0;
        $centerTotal = 0;
        $repeatedTotal = 0;
        $repeatedCount = 0;
        $scoreTotal = 0;
        $scoreCount = 0;

        foreach ($contestsList as $c) {
            $sumTotal += $c['sum'];
            $evensTotal += $c['evens'];
            $oddsTotal += $c['odds'];
            $primesTotal += $c['primes'];
            $fibonacciTotal += $c['fibonacci'];
            $frameTotal += $c['frame'];
            $centerTotal += $c['center'];

            if ($c['repeated_from_previous'] !== null) {
                $repeatedTotal += $c['repeated_from_previous'];
                $repeatedCount++;
            }

            if ($c['score'] !== null) {
                $scoreTotal += $c['score'];
                $scoreCount++;
            }
        }

        return [
            'avg_sum' => round($sumTotal / $count, 1),
            'avg_evens' => round($evensTotal / $count, 1),
            'avg_odds' => round($oddsTotal / $count, 1),
            'avg_primes' => round($primesTotal / $count, 1),
            'avg_fibonacci' => round($fibonacciTotal / $count, 1),
            'avg_frame' => round($frameTotal / $count, 1),
            'avg_center' => round($centerTotal / $count, 1),
            'avg_repeated' => $repeatedCount > 0 ? round($repeatedTotal / $repeatedCount, 1) : null,
            'avg_score' => $scoreCount > 0 ? round($scoreTotal / $scoreCount, 0) : null,
        ];
    }
}
