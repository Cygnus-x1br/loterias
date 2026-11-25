<?php

namespace App\Services\Betting;

use App\Models\Closing;
use App\Services\BetScoringService;
use App\Services\Betting\Generators\BalancedBetGenerator;
use App\Services\Betting\Generators\BetGeneratorInterface;
use App\Services\Betting\Generators\RandomBetGenerator;
use App\Services\Betting\Generators\ReducedBetGenerator;
use App\Services\LotofacilSettingService;
use App\Services\LotofacilStatisticsService;
use InvalidArgumentException;
use Throwable;

/**
 * Compara métodos de geração (item 24) sob as mesmas condições: mesmo grupo-base, mesmo
 * orçamento de apostas, mesmo universo de cenários (os 3.268.760 sorteios), mesmos critérios
 * de avaliação e a mesma semente aleatória. Nada é gravado no banco.
 */
class ClosingComparisonService
{
    /**
     * Máximo de apostas por método: a chance exata enumera todos os sorteios para cada aposta.
     */
    public const MAX_BETS = 30;

    public const MIN_BETS = 2;

    /**
     * Métodos comparáveis (os que não dependem de dezenas fixas informadas à mão).
     *
     * @var array<string, array{label: string, generator: class-string<BetGeneratorInterface>, generation: string}>
     */
    public const METHODS = [
        'random' => ['label' => 'Aleatório', 'generator' => RandomBetGenerator::class, 'generation' => 'random'],
        'balanced' => ['label' => 'Equilibrado (filtros)', 'generator' => BalancedBetGenerator::class, 'generation' => 'balanced'],
        'balanced_spread' => ['label' => 'Equilibrado + distribuir apostas', 'generator' => BalancedBetGenerator::class, 'generation' => 'balanced'],
        'balanced_local' => ['label' => 'Equilibrado + busca local', 'generator' => BalancedBetGenerator::class, 'generation' => 'balanced'],
        'reduced' => ['label' => 'Reduzido (guloso)', 'generator' => ReducedBetGenerator::class, 'generation' => 'reduced'],
    ];

    /**
     * Grupo-base máximo em que a garantia de 14 pontos é verificada por enumeração.
     */
    public const MAX_BASE_FOR_GUARANTEE = 20;

    /**
     * O reduzido guloso testa cada aposta candidata contra cada cenário a cada passo:
     * com 19 dezenas já são 15 milhões de testes por aposta escolhida, e com 21 passam de 2 bilhões.
     */
    public const MAX_BASE_FOR_REDUCED = 18;

    public function __construct(
        private ClosingProbabilityService $probability,
        private BetScoringService $scoring,
        private LotofacilStatisticsService $statistics,
        private LotofacilSettingService $settings,
        private CoverageAuditorService $auditor,
        private ClosingTransparencyReportService $report,
        private LocalSearchService $localSearch,
    ) {}

    /**
     * @param  array<int, int>  $baseNumbers
     * @param  array<int, string>  $methods
     * @return array{
     *     seed: int,
     *     base_numbers: array<int, int>,
     *     bets_budget: int,
     *     universe: int,
     *     results: array<string, array<string, mixed>>
     * }
     *
     * @throws InvalidArgumentException
     */
    public function compare(array $baseNumbers, int $betsBudget, array $methods, int $seed): array
    {
        $baseNumbers = array_values(array_unique(array_map('intval', $baseNumbers)));
        sort($baseNumbers);

        $this->validate($baseNumbers, $betsBudget, $methods);

        // A chance exata percorre 3,2 milhões de sorteios por aposta: a comparação pode passar do limite padrão de 30 s
        if (function_exists('set_time_limit')) {
            set_time_limit(180);
        }

        $patterns = $this->statistics->getScoringPatterns();
        $results = [];

        foreach (array_values(array_unique($methods)) as $method) {
            $results[$method] = $this->evaluateMethod($method, $baseNumbers, $betsBudget, $seed, $patterns);
        }

        // Deixa o gerador global de números aleatórios imprevisível de novo
        mt_srand();

        return [
            'seed' => $seed,
            'base_numbers' => $baseNumbers,
            'bets_budget' => $betsBudget,
            'universe' => ClosingProbabilityService::TOTAL_DRAWS,
            'results' => $results,
        ];
    }

    /**
     * @param  array<int, int>  $baseNumbers
     * @param  array<int, string>  $methods
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $baseNumbers, int $betsBudget, array $methods): void
    {
        if (count($baseNumbers) < 16 || count($baseNumbers) > 25) {
            throw new InvalidArgumentException('O grupo-base deve ter entre 16 e 25 dezenas.');
        }

        foreach ($baseNumbers as $number) {
            if ($number < 1 || $number > 25) {
                throw new InvalidArgumentException('As dezenas do grupo-base devem estar entre 1 e 25.');
            }
        }

        if ($betsBudget < self::MIN_BETS || $betsBudget > self::MAX_BETS) {
            throw new InvalidArgumentException(sprintf('O orçamento deve ser de %d a %d apostas.', self::MIN_BETS, self::MAX_BETS));
        }

        if (count(array_unique($methods)) < 2) {
            throw new InvalidArgumentException('Escolha pelo menos dois métodos para comparar.');
        }

        foreach ($methods as $method) {
            if (! isset(self::METHODS[$method])) {
                throw new InvalidArgumentException("Método de comparação desconhecido: {$method}.");
            }
        }
    }

    /**
     * @param  array<int, int>  $baseNumbers
     * @param  array<string, mixed>  $patterns
     * @return array<string, mixed>
     */
    private function evaluateMethod(string $method, array $baseNumbers, int $betsBudget, int $seed, array $patterns): array
    {
        $definition = self::METHODS[$method];
        $base = [
            'label' => $definition['label'],
            'classification' => $this->report->generationClassification($definition['generation']),
            'error' => null,
        ];

        $closing = new Closing([
            'method' => $definition['generation'],
            'base_numbers' => $baseNumbers,
            'bet_size' => 15,
            'planned_bets' => $betsBudget,
            'parameters' => $this->parametersFor($method, $baseNumbers),
        ]);

        if ($method === 'reduced' && count($baseNumbers) > self::MAX_BASE_FOR_REDUCED) {
            return array_merge($base, ['error' => sprintf(
                'O reduzido (guloso) só é comparado com grupo-base de até %d dezenas: acima disso o cálculo leva muitos minutos.',
                self::MAX_BASE_FOR_REDUCED
            )]);
        }

        try {
            $generator = app($definition['generator']);
            $generator->validate($closing);

            // Mesma semente para todos os métodos: cada um parte do mesmo estado do sorteio
            mt_srand($seed);
            memory_reset_peak_usage();
            $memoryBefore = memory_get_usage();
            $startedAt = hrtime(true);

            $bets = [];
            foreach ($generator->generate($closing) as $bet) {
                $bet = array_map('intval', $bet);
                sort($bet);
                $bets[] = $bet;

                if (count($bets) >= $betsBudget) {
                    break;
                }
            }

            if ($method === 'balanced_local') {
                $bets = $this->localSearch->improve($baseNumbers, $bets, [
                    'seed' => $seed,
                    'max_iterations' => 800,
                    'max_stale' => 300,
                    'time_limit' => 5,
                ])['bets'];
            }

            $elapsedMs = (hrtime(true) - $startedAt) / 1e6;
            $memoryBytes = max(0, memory_get_peak_usage() - $memoryBefore);
        } catch (Throwable $exception) {
            return array_merge($base, ['error' => $exception->getMessage()]);
        }

        if ($bets === []) {
            return array_merge($base, ['error' => 'Nenhuma aposta foi gerada com este método.']);
        }

        return array_merge($base, $this->metrics($bets, $baseNumbers, $patterns), [
            'bets' => $bets,
            'time_ms' => round($elapsedMs, 2),
            'memory_bytes' => $memoryBytes,
        ]);
    }

    /**
     * @param  array<int, int>  $baseNumbers
     * @return array<string, mixed>|null
     */
    private function parametersFor(string $method, array $baseNumbers): ?array
    {
        return match ($method) {
            'balanced' => ['even_odd_balance' => [6, 9]],
            'balanced_spread', 'balanced_local' => ['even_odd_balance' => [6, 9], 'spread_bets' => true],
            'reduced' => ['reduced_parameters' => ['guarantee_hits' => min(15, count($baseNumbers)), 'guarantee_points' => 14]],
            default => null,
        };
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     * @param  array<int, int>  $baseNumbers
     * @param  array<string, mixed>  $patterns
     * @return array<string, mixed>
     */
    private function metrics(array $bets, array $baseNumbers, array $patterns): array
    {
        $probability = $this->probability->calculate($bets);

        $scores = array_map(
            fn (array $bet): int => (int) $this->scoring->calculateScore($bet, null, $patterns)['total_score'],
            $bets
        );

        $unitCost = $this->settings->getPriceFor(15);

        return [
            'bets_count' => count($bets),
            'cost' => $unitCost * count($bets),
            'coverage' => array_map(
                fn (array $row): array => ['probability' => $row['probability'], 'one_in' => $row['one_in']],
                $probability['at_least']
            ),
            'expected_prized_bets' => $probability['expected_prized_bets'],
            'coverage_classification' => ResultClassification::Exact,
            'scores' => [
                'min' => min($scores),
                'mean' => round(array_sum($scores) / count($scores), 1),
                'max' => max($scores),
            ],
            'redundancy' => $this->redundancy($bets),
            'distribution' => $this->distribution($bets, $baseNumbers),
            'guarantee' => $this->guarantee($bets, $baseNumbers),
        ];
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     * @return array{average_overlap: float|null, near_duplicates: int, pairs: int}
     */
    private function redundancy(array $bets): array
    {
        $nearDuplicates = 0;
        $pairs = 0;

        for ($i = 0; $i < count($bets); $i++) {
            for ($j = $i + 1; $j < count($bets); $j++) {
                $pairs++;
                if (count(array_intersect($bets[$i], $bets[$j])) >= 14) {
                    $nearDuplicates++;
                }
            }
        }

        return [
            'average_overlap' => $this->probability->averageOverlap($bets),
            'near_duplicates' => $nearDuplicates,
            'pairs' => $pairs,
        ];
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     * @param  array<int, int>  $baseNumbers
     * @return array{usage: array<int, int>, min: int, max: int, spread: float}
     */
    private function distribution(array $bets, array $baseNumbers): array
    {
        $usage = array_fill_keys($baseNumbers, 0);
        foreach ($bets as $bet) {
            foreach ($bet as $number) {
                $usage[$number] = ($usage[$number] ?? 0) + 1;
            }
        }

        $mean = array_sum($usage) / count($usage);
        $variance = array_sum(array_map(fn (int $count): float => ($count - $mean) ** 2, $usage)) / count($usage);

        return [
            'usage' => $usage,
            'min' => min($usage),
            'max' => max($usage),
            'spread' => round(sqrt($variance), 2),
        ];
    }

    /**
     * Garantia de 14 pontos se as 15 dezenas sorteadas estiverem no grupo-base, por enumeração exata.
     *
     * @param  array<int, array<int, int>>  $bets
     * @param  array<int, int>  $baseNumbers
     * @return array{evaluated: bool, confirmed: bool|null, covered_percent: float|null, classification: ResultClassification|null, note: string}
     */
    private function guarantee(array $bets, array $baseNumbers): array
    {
        if (count($baseNumbers) > self::MAX_BASE_FOR_GUARANTEE) {
            return [
                'evaluated' => false,
                'confirmed' => null,
                'covered_percent' => null,
                'classification' => null,
                'note' => 'Não avaliada: grupo-base acima de '.self::MAX_BASE_FOR_GUARANTEE.' dezenas.',
            ];
        }

        $audit = $this->auditor->auditCoverage($baseNumbers, $bets, 14, 15);

        return [
            'evaluated' => true,
            'confirmed' => (bool) $audit['garantia_confirmada'],
            'covered_percent' => (float) ($audit['cobertura_por_faixa']['14_acertos'] ?? 0),
            'classification' => ResultClassification::Exact,
            'note' => 'Todos os '.number_format((int) $audit['quantidade_cenarios_analisados'], 0, ',', '.').' cenários do grupo-base foram verificados.',
        ];
    }
}
