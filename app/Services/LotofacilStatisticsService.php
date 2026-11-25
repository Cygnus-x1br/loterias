<?php

namespace App\Services;

use App\Models\HistoricalResult;
use App\Services\Betting\Math\Combinatorics;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LotofacilStatisticsService
{
    protected function cache(): StatisticsCache
    {
        return app(StatisticsCache::class);
    }

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

        $data = $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
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

        $data = $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
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

        $data = $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($lastContests) {
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

        $data = $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
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

        $data = $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($lastContests, $limit) {
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
        $cachedAttributes = $this->cache()->remember('last_contest', now()->addMinutes(30), function () {
            $last = HistoricalResult::orderByDesc('contest_number')->first();

            return $last ? $last->getAttributes() : null;
        });

        if (! $cachedAttributes || ! is_array($cachedAttributes)) {
            return null;
        }

        return (new HistoricalResult)->newFromBuilder($cachedAttributes);
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
        return $this->cache()->remember('last_contest_full_statistics', now()->addMinutes(30), function () {
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
        return $this->cache()->remember('last_contest_with_sum', now()->addMinutes(30), function () {
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
        return $this->cache()->remember('repeated_draws_analysis', now()->addHours(6), function () {
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
        return $this->cache()->remember('historical_draw_hashes', now()->addHours(6), function () {
            $hashes = HistoricalResult::query()
                ->whereNotNull('drawn_numbers_hash')
                ->pluck('drawn_numbers_hash')
                ->all();

            return array_fill_keys($hashes, true);
        });
    }

    /**
     * Retorna a classificação de temperatura (quente, neutra, fria) de cada dezena (1 a 25)
     * pela frequência nos últimos $recentContests concursos (ver classifyTemperatures()).
     * A classificação é descritiva: não altera a chance de uma dezena sair.
     *
     * @param  int  $recentContests  Quantidade de concursos recentes para a análise de momento.
     * @return array<int, array{number: int, temperature: string, recent_count: int, total_count: int, delay: int}>
     */
    public function getNumberTemperatureClassification(int $recentContests = 20): array
    {
        $cacheKey = 'number_temperature_classification_'.$recentContests;

        return $this->cache()->remember($cacheKey, now()->addMinutes(30), function () use ($recentContests) {
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

            $temperatures = $this->classifyTemperatures(
                array_column($scoredNumbers, 'recent_count', 'number'),
                $delays
            );

            $classification = [];
            for ($num = 1; $num <= 25; $num++) {
                $classification[$num] = array_merge($scoredNumbers[$num], [
                    'temperature' => $temperatures[$num],
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
        return $this->cache()->remember('historical_average_score', now()->addHours(6), function () {
            // Se existir valores nulos, usar o artisan command lotofacil:calculate-scores
            $totalContests = HistoricalResult::count();
            $averageScore = HistoricalResult::whereNotNull('score')->avg('score') ?? 0;
            $minScore = HistoricalResult::whereNotNull('score')->min('score') ?? 0;
            $maxScore = HistoricalResult::whereNotNull('score')->max('score') ?? 0;

            $averageScore = round((float) $averageScore, 1);

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
                'min_score' => (int) $minScore,
                'max_score' => (int) $maxScore,
                'classification' => $classification,
                'color' => $color,
            ];
        });
    }

    /**
     * Probabilidade de uma combinação aleatória de $betSize dezenas conter exatamente $repeated
     * das 15 dezenas do último concurso (distribuição hipergeométrica):
     * P(k) = C(15, k) · C(10, m − k) / C(25, m).
     *
     * Para m = 15 é exatamente a distribuição de repetidas entre dois sorteios consecutivos.
     */
    public function repetitionProbability(int $repeated, int $betSize = 15): float
    {
        $total = Combinatorics::countCombinations(25, $betSize);

        if ($total === 0) {
            return 0.0;
        }

        return Combinatorics::countCombinations(15, $repeated)
            * Combinatorics::countCombinations(10, $betSize - $repeated)
            / $total;
    }

    /**
     * Distribui $totalGames apostas entre as quantidades de repetidas do último concurso
     * ($minRepeated a $maxRepeated), proporcionalmente à probabilidade real de cada quantidade.
     * As sobras do arredondamento vão para os maiores restos decimais.
     *
     * @return array<int, int> Chave: quantidade de repetidas, valor: quantidade de apostas.
     */
    public function calculateRepetitionQuotas(int $minRepeated, int $maxRepeated, int $totalGames, int $betSize = 15): array
    {
        $options = range($minRepeated, $maxRepeated);
        $weights = [];

        foreach ($options as $repeated) {
            $weights[$repeated] = $this->repetitionProbability($repeated, $betSize);
        }

        $totalWeight = array_sum($weights);

        // Se nenhuma quantidade da faixa for possível, distribui igualmente
        if ($totalWeight <= 0) {
            $weights = array_fill_keys($options, 1.0);
            $totalWeight = count($options);
        }

        $quotas = [];
        $remainders = [];
        $remainingGames = $totalGames;

        foreach ($weights as $repeated => $weight) {
            $exactQuota = $weight / $totalWeight * $totalGames;
            $quotas[$repeated] = (int) floor($exactQuota);
            $remainders[$repeated] = $exactQuota - $quotas[$repeated];
            $remainingGames -= $quotas[$repeated];
        }

        arsort($remainders);
        foreach (array_keys($remainders) as $repeated) {
            if ($remainingGames <= 0) {
                break;
            }

            $quotas[$repeated]++;
            $remainingGames--;
        }

        ksort($quotas);

        return $quotas;
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
        $historyWindow = 20;
        $cacheKey = "lotofacil_contests_comparison_{$limit}";

        return $this->cache()->remember($cacheKey, now()->addHours(6), function () use ($limit, $historyWindow) {
            $rawResults = HistoricalResult::query()
                ->orderBy('contest_number', 'desc')
                ->take($limit + $historyWindow + 1)
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
            $contestsList = $this->buildContestsComparisonList($rawResults, $limit, $temperatureMap, $historyWindow);
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
        $results = $results->sortByDesc('contest_number')->values();
        $totalContests = $results->count();
        $frequencies = array_fill(1, 25, 0);
        $delays = array_fill(1, 25, $totalContests);

        foreach ($results as $index => $res) {
            $numbers = is_array($res->drawn_numbers) ? $res->drawn_numbers : json_decode((string) $res->drawn_numbers, true);
            if (! is_array($numbers)) {
                continue;
            }
            foreach ($numbers as $num) {
                $numInt = (int) $num;
                if ($numInt >= 1 && $numInt <= 25) {
                    $frequencies[$numInt]++;
                    $delays[$numInt] = min($delays[$numInt], $index);
                }
            }
        }

        $temperatures = $this->classifyTemperatures($frequencies, $delays);

        $map = [];
        for ($i = 1; $i <= 25; $i++) {
            $map[$i] = [
                'frequency' => $frequencies[$i],
                'percentage' => $totalContests > 0 ? round(($frequencies[$i] / $totalContests) * 100, 1) : 0.0,
                'temperature' => $temperatures[$i],
            ];
        }

        return $map;
    }

    /**
     * Regra única de temperatura usada em todo o sistema.
     *
     * As 25 dezenas são ordenadas pela frequência no período (mais frequente primeiro);
     * empates são decididos por quem saiu mais recentemente (menor atraso) e depois pela dezena.
     * As 8 primeiras são quentes, as 8 últimas frias e as 9 do meio neutras.
     *
     * O atraso só desempata: uma dezena atrasada nunca fica "mais quente" por isso.
     * Na série histórica, quentes, neutras e frias saem com a mesma frequência (≈ 60%),
     * então a classificação descreve o passado e não prevê o próximo sorteio.
     *
     * @param  array<int, int>  $frequencies  Frequência de cada dezena (1 a 25) no período.
     * @param  array<int, int>  $delays  Concursos desde a última aparição (0 = saiu no mais recente).
     * @return array<int, string> 'hot', 'neutral' ou 'cold' por dezena.
     */
    public function classifyTemperatures(array $frequencies, array $delays): array
    {
        $numbers = range(1, 25);

        usort($numbers, fn (int $a, int $b): int => [($frequencies[$b] ?? 0), -($delays[$b] ?? 0), -$b]
            <=> [($frequencies[$a] ?? 0), -($delays[$a] ?? 0), -$a]);

        $temperatures = [];
        foreach ($numbers as $position => $number) {
            $temperatures[$number] = match (true) {
                $position < 8 => 'hot',
                $position >= 17 => 'cold',
                default => 'neutral',
            };
        }

        ksort($temperatures);

        return $temperatures;
    }

    /**
     * Constrói a lista estruturada de concursos para a tabela comparativa.
     */
    private function buildContestsComparisonList(Collection $rawResults, int $limit, array $temperatureMap, int $historyWindow = 20): array
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

            // Janela histórica contextual (concursos anteriores ao sorteio atual)
            $contestHistorySlice = $resultsArray->slice($idx + 1, $historyWindow);
            if ($contestHistorySlice->isNotEmpty()) {
                $contestTemperatureMap = $this->calculatePeriodTemperatures($contestHistorySlice);
            } else {
                // Caso seja o concurso mais antigo disponível sem histórico anterior, usa o mapa global do período
                $contestTemperatureMap = $temperatureMap;
            }

            // Grade das 25 dezenas com status e temperatura específica deste concurso
            $numbersGrid = [];
            for ($num = 1; $num <= 25; $num++) {
                $isDrawn = isset($drawnSet[$num]);
                $numbersGrid[$num] = [
                    'number' => $num,
                    'is_drawn' => $isDrawn,
                    'temperature' => $contestTemperatureMap[$num]['temperature'] ?? 'neutral',
                    'frequency' => $contestTemperatureMap[$num]['frequency'] ?? 0,
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
                    $scoreData = $betScoringService->calculateScore($drawn, $current->contest_number);
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

    /**
     * Calcula o ciclo atual das dezenas (quantas faltam e quais faltam para fechar o ciclo).
     */
    public function getDecadesCycleAnalysis(?int $contextContestNumber = null): array
    {
        $cacheKey = 'lotofacil_decades_cycle'.($contextContestNumber ? '_'.$contextContestNumber : '');

        return $this->cache()->remember($cacheKey, now()->addMinutes(30), function () use ($contextContestNumber) {
            $query = HistoricalResult::query();
            if ($contextContestNumber) {
                $query->where('contest_number', '<', $contextContestNumber);
            }
            $latestResult = $query->orderBy('contest_number', 'desc')->first(['contest_number', 'cycle_number']);

            if (! $latestResult || ! $latestResult->cycle_number) {
                return [
                    'missing_numbers' => [],
                    'missing_count' => 25,
                    'drawn_count' => 0,
                    'contests_in_current_cycle' => 0,
                    'started_at_contest' => null,
                    'cycle_progression' => [],
                    'average_cycle_length' => 0,
                ];
            }

            $currentCycle = $latestResult->cycle_number;

            $cycleQuery = HistoricalResult::where('cycle_number', $currentCycle);
            if ($contextContestNumber) {
                $cycleQuery->where('contest_number', '<', $contextContestNumber);
            }

            $cycleResults = $cycleQuery->orderBy('contest_number', 'asc')
                ->get(['contest_number', 'drawn_numbers']);

            $drawnSinceLastCycle = [];
            $contestsCount = 0;
            $cycleStartContest = $cycleResults->first()->contest_number ?? $latestResult->contest_number;

            $cycleProgression = [];

            foreach ($cycleResults as $result) {
                $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);
                if (is_array($numbers)) {
                    $newInCycle = [];
                    foreach ($numbers as $num) {
                        if (! isset($drawnSinceLastCycle[(int) $num])) {
                            $newInCycle[] = $num;
                            $drawnSinceLastCycle[(int) $num] = true;
                        }
                    }
                    $cycleProgression[] = [
                        'contest_number' => $result->contest_number,
                        'drawn_numbers' => $newInCycle,
                    ];
                }
                $contestsCount++;
            }

            $missingNumbers = [];
            for ($i = 1; $i <= 25; $i++) {
                if (! isset($drawnSinceLastCycle[$i])) {
                    $missingNumbers[] = $i;
                }
            }

            $averageCycleLength = HistoricalResult::query()
                ->whereNotNull('cycle_number')
                ->where('cycle_number', '<', $currentCycle)
                ->selectRaw('cycle_number, count(*) as total_contests')
                ->groupBy('cycle_number')
                ->get()
                ->avg('total_contests') ?? 0;

            return [
                'missing_numbers' => $missingNumbers,
                'missing_count' => count($missingNumbers),
                'drawn_count' => 25 - count($missingNumbers),
                'contests_in_current_cycle' => $contestsCount,
                'started_at_contest' => $cycleStartContest,
                'cycle_progression' => $cycleProgression,
                'average_cycle_length' => round($averageCycleLength, 2),
            ];
        });
    }

    /**
     * Calcula o atraso (delay) atual de cada dezena (quantos concursos faz que ela não sai).
     */
    public function getCurrentDelayAnalysis(?int $contextContestNumber = null): array
    {
        $cacheKey = 'lotofacil_current_delay'.($contextContestNumber ? '_'.$contextContestNumber : '');

        return $this->cache()->remember($cacheKey, now()->addMinutes(30), function () use ($contextContestNumber) {
            $query = HistoricalResult::query();
            if ($contextContestNumber) {
                $query->where('contest_number', '<', $contextContestNumber);
            }

            $results = $query->orderBy('contest_number', 'desc')
                ->take(50) // 50 sorteios devem cobrir o maior atraso
                ->get(['contest_number', 'drawn_numbers']);

            if ($results->isEmpty()) {
                $emptyDelays = [];
                for ($i = 1; $i <= 25; $i++) {
                    $emptyDelays[] = [
                        'number' => $i,
                        'delay' => 0,
                    ];
                }

                return $emptyDelays;
            }

            $delays = [];

            foreach (range(1, 25) as $num) {
                $delay = 0;
                foreach ($results as $index => $result) {
                    $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);
                    if (is_array($numbers) && in_array($num, $numbers, true)) {
                        $delay = $index;
                        break;
                    }
                }
                $delays[$num] = $delay;
            }

            $formattedDelays = [];
            foreach ($delays as $num => $delay) {
                $formattedDelays[] = [
                    'number' => $num,
                    'delay' => $delay,
                ];
            }

            // Sort by highest delay first
            usort($formattedDelays, function ($a, $b) {
                if ($a['delay'] === $b['delay']) {
                    return $a['number'] <=> $b['number'];
                }

                return $b['delay'] <=> $a['delay'];
            });

            return $formattedDelays;
        });
    }

    /**
     * Análise de sequências consecutivas (tamanhos 2, 3 e 4) em toda a série histórica,
     * com as sequências específicas mais frequentes.
     *
     * @return array{summary: array<string, int|float>, top_pairs_consecutive: array<string, int>, top_trios_consecutive: array<string, int>, top_quads_consecutive: array<string, int>}
     */
    public function getConsecutiveSequencesAnalysis(): array
    {
        return $this->cache()->remember('consecutive_sequences_analysis', now()->addHours(6), function (): array {
            $results = HistoricalResult::query()
                ->orderBy('contest_number')
                ->pluck('drawn_numbers')
                ->map(function ($drawnNumbers): array {
                    $numbers = is_array($drawnNumbers) ? $drawnNumbers : (json_decode((string) $drawnNumbers, true) ?? []);
                    $numbers = array_map('intval', $numbers);
                    sort($numbers);

                    return ['drawn_numbers' => $numbers];
                })
                ->filter(fn (array $result): bool => $result['drawn_numbers'] !== []);

            return $this->calculateConsecutiveSequencesAnalysis($results);
        });
    }

    /**
     * @param  Collection<int, array{drawn_numbers: array<int, int>}>  $results
     */
    private function calculateConsecutiveSequencesAnalysis(Collection $results): array
    {
        $count = $results->count();
        if ($count === 0) {
            return [
                'summary' => [],
                'top_pairs_consecutive' => [],
                'top_trios_consecutive' => [],
                'top_quads_consecutive' => [],
            ];
        }

        $drawsWithSeq2 = 0;
        $drawsWithSeq3 = 0;
        $drawsWithSeq4 = 0;

        $pairSequencesCount = [];
        $trioSequencesCount = [];
        $quadSequencesCount = [];

        foreach ($results as $result) {
            $numbers = $result['drawn_numbers'];

            $seqFlags = $this->extractConsecutiveRunsFlags($numbers);
            if ($seqFlags['hasSeq2']) {
                $drawsWithSeq2++;
            }
            if ($seqFlags['hasSeq3']) {
                $drawsWithSeq3++;
            }
            if ($seqFlags['hasSeq4']) {
                $drawsWithSeq4++;
            }

            $this->tallySubSequences($numbers, $pairSequencesCount, $trioSequencesCount, $quadSequencesCount);
        }

        arsort($pairSequencesCount);
        arsort($trioSequencesCount);
        arsort($quadSequencesCount);

        return [
            'summary' => [
                'seq2_percentage' => round(($drawsWithSeq2 / $count) * 100, 1),
                'seq2_count' => $drawsWithSeq2,
                'seq3_percentage' => round(($drawsWithSeq3 / $count) * 100, 1),
                'seq3_count' => $drawsWithSeq3,
                'seq4_percentage' => round(($drawsWithSeq4 / $count) * 100, 1),
                'seq4_count' => $drawsWithSeq4,
            ],
            'top_pairs_consecutive' => array_slice($pairSequencesCount, 0, 8, true),
            'top_trios_consecutive' => array_slice($trioSequencesCount, 0, 6, true),
            'top_quads_consecutive' => array_slice($quadSequencesCount, 0, 4, true),
        ];
    }

    /**
     * Identifica a presença de sequências contínuas de 2, 3 e 4 números.
     */
    private function extractConsecutiveRunsFlags(array $numbers): array
    {
        $hasSeq2 = false;
        $hasSeq3 = false;
        $hasSeq4 = false;

        $currentRun = [$numbers[0]];
        $n = count($numbers);

        for ($i = 1; $i < $n; $i++) {
            if ($numbers[$i] === $numbers[$i - 1] + 1) {
                $currentRun[] = $numbers[$i];
            } else {
                $this->updateSeqFlags($currentRun, $hasSeq2, $hasSeq3, $hasSeq4);
                $currentRun = [$numbers[$i]];
            }
        }
        $this->updateSeqFlags($currentRun, $hasSeq2, $hasSeq3, $hasSeq4);

        return [
            'hasSeq2' => $hasSeq2,
            'hasSeq3' => $hasSeq3,
            'hasSeq4' => $hasSeq4,
        ];
    }

    private function updateSeqFlags(array $run, bool &$hasSeq2, bool &$hasSeq3, bool &$hasSeq4): void
    {
        $len = count($run);
        if ($len >= 2) {
            $hasSeq2 = true;
        }
        if ($len >= 3) {
            $hasSeq3 = true;
        }
        if ($len >= 4) {
            $hasSeq4 = true;
        }
    }

    /**
     * Contabiliza sequências específicas de 2, 3 e 4 números consecutivos.
     */
    private function tallySubSequences(
        array $numbers,
        array &$pairCounts,
        array &$trioCounts,
        array &$quadCounts
    ): void {
        $n = count($numbers);
        for ($i = 0; $i < $n - 1; $i++) {
            if ($numbers[$i + 1] === $numbers[$i] + 1) {
                $seq2 = sprintf('%02d-%02d', $numbers[$i], $numbers[$i + 1]);
                $pairCounts[$seq2] = ($pairCounts[$seq2] ?? 0) + 1;

                if ($i < $n - 2 && $numbers[$i + 2] === $numbers[$i] + 2) {
                    $seq3 = sprintf('%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2]);
                    $trioCounts[$seq3] = ($trioCounts[$seq3] ?? 0) + 1;

                    if ($i < $n - 3 && $numbers[$i + 3] === $numbers[$i] + 3) {
                        $seq4 = sprintf('%02d-%02d-%02d-%02d', $numbers[$i], $numbers[$i + 1], $numbers[$i + 2], $numbers[$i + 3]);
                        $quadCounts[$seq4] = ($quadCounts[$seq4] ?? 0) + 1;
                    }
                }
            }
        }
    }
}
