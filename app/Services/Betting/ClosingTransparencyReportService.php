<?php

namespace App\Services\Betting;

use App\Models\Closing;
use App\Services\BetScoringService;
use App\Services\LotofacilSettingService;

/**
 * Relatório de transparência de um fechamento (item 26): reúne método, parâmetros, custo,
 * cobertura, garantias, limitações e a classificação de cada resultado (item 29),
 * sem prometer ganho nem prever resultado (item 30).
 */
class ClosingTransparencyReportService
{
    /**
     * Acima desta quantidade de apostas a faixa de pontuação não é calculada (custo de cálculo).
     */
    public const MAX_BETS_FOR_SCORES = 200;

    public const DISCLAIMER = 'Este relatório descreve como o fechamento foi montado e o que ele cobre. Não prevê o resultado do sorteio, não indica dezenas mais prováveis e não promete prêmio: todas as combinações da Lotofácil têm a mesma chance de ser sorteada, e comprar mais apostas aumenta o custo e a cobertura, não a qualidade da previsão.';

    public function __construct(
        private LotofacilSettingService $settingService,
        private BetScoringService $scoringService,
    ) {}

    /**
     * @param  array<string, mixed>|null  $probabilityReport  Resultado de ClosingProbabilityService, se já calculado.
     * @param  array<string, mixed>|null  $combinatorialCoverage  Resultado de CoverageAuditorService, se houver.
     * @return array<string, mixed>
     */
    public function build(Closing $closing, ?array $probabilityReport = null, ?array $combinatorialCoverage = null): array
    {
        $bets = $closing->bets()->pluck('numbers')
            ->map(fn ($numbers): array => is_array($numbers) ? $numbers : (json_decode((string) $numbers, true) ?? []))
            ->all();

        $method = (string) $closing->method;
        $parameters = $closing->parameters ?? [];
        $unitCost = $this->settingService->getPriceFor((int) $closing->bet_size);

        return [
            'method' => [
                'key' => $method,
                'label' => $this->methodLabel($method),
                'classification' => $this->generationClassification($method),
                'note' => $this->generationNote($method),
            ],
            'base_numbers' => array_values($closing->base_numbers ?? []),
            'bet_size' => (int) $closing->bet_size,
            'planned_bets' => (int) $closing->planned_bets,
            'bets_count' => count($bets),
            'cost' => [
                'unit' => $unitCost,
                'total' => $unitCost * count($bets),
                'classification' => ResultClassification::Exact,
            ],
            'parameters' => $this->describeParameters($parameters),
            'seed' => [
                'recorded' => false,
                'note' => 'A geração usa sorteio aleatório sem semente registrada: gerar de novo com os mesmos parâmetros produz outras apostas. As apostas geradas ficam salvas e podem ser conferidas a qualquer momento.',
            ],
            'scores' => $this->scoreRange($bets),
            'guarantee' => $this->guarantee($closing, $combinatorialCoverage),
            'probability' => $probabilityReport ? [
                'classification' => ResultClassification::Exact,
                'at_least' => $probabilityReport['at_least'],
                'scenarios' => $probabilityReport['total_draws'],
            ] : null,
            'scenarios_evaluated' => $probabilityReport['total_draws'] ?? ($combinatorialCoverage['quantidade_cenarios_analisados'] ?? null),
            'validation' => $this->validationMethod($probabilityReport, $combinatorialCoverage),
            'warnings' => array_values($closing->generation_warnings ?? []),
            'limitations' => $this->limitations($method, $probabilityReport !== null, $combinatorialCoverage),
            'randomness' => $this->randomnessNotes($method),
            'disclaimer' => self::DISCLAIMER,
        ];
    }

    public function methodLabel(string $method): string
    {
        return match ($method) {
            'integral' => 'Combinação integral',
            'reduced' => 'Fechamento reduzido',
            'wheel' => 'Sistema de roda',
            'random' => 'Geração aleatória',
            'balanced' => 'Geração equilibrada',
            default => ucfirst($method),
        };
    }

    public function generationClassification(string $method): ResultClassification
    {
        return match ($method) {
            'integral' => ResultClassification::Exact,
            'wheel' => ResultClassification::Feasible,
            default => ResultClassification::Heuristic,
        };
    }

    private function generationNote(string $method): string
    {
        return match ($method) {
            'integral' => 'Todas as combinações do grupo-base foram enumeradas; combinações de 15 dezenas já sorteadas no passado são omitidas.',
            'reduced' => 'Seleção gulosa de apostas para cobrir os cenários pedidos. Não há prova de que o número de apostas seja o mínimo possível.',
            'wheel' => 'Dezenas fixas mais rotação das variáveis. Atende à estrutura pedida, sem prova de ser a melhor roda.',
            'random' => 'Apostas sorteadas ao acaso dentro do grupo-base, sem repetir apostas nem combinações já sorteadas.',
            'balanced' => 'Apostas sorteadas ao acaso que respeitam filtros de organização (paridade, soma, repetidas, temperatura etc.). Os filtros organizam as apostas; não aumentam a chance de acerto.',
            default => 'Método sem descrição cadastrada.',
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, array{label: string, value: string}>
     */
    private function describeParameters(array $parameters): array
    {
        $labels = [
            'even_odd_balance' => 'Pares por aposta',
            'sum_range' => 'Faixa de soma',
            'primes_count' => 'Primos por aposta',
            'fibonacci_count' => 'Fibonacci por aposta',
            'repeated_last_draw' => 'Repetidas do último concurso',
            'score_range' => 'Faixa de score',
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            if (isset($parameters[$key]) && is_array($parameters[$key]) && count($parameters[$key]) === 2) {
                $rows[] = ['label' => $label, 'value' => $parameters[$key][0].' a '.$parameters[$key][1]];
            }
        }

        foreach (['hot' => 'Quentes', 'neutral' => 'Neutras', 'cold' => 'Frias'] as $key => $label) {
            $range = $parameters['temperature_distribution'][$key] ?? null;
            if (is_array($range) && count($range) === 2) {
                $rows[] = ['label' => "Dezenas {$label} por aposta", 'value' => $range[0].' a '.$range[1]];
            }
        }

        if (! empty($parameters['spread_bets'])) {
            $rows[] = ['label' => 'Distribuir apostas', 'value' => 'ativo (mínima sobreposição entre apostas)'];
        }

        if (! empty($parameters['number_weights']) && is_array($parameters['number_weights'])) {
            ksort($parameters['number_weights']);
            $weights = collect($parameters['number_weights'])
                ->map(fn ($weight, $number): string => str_pad((string) $number, 2, '0', STR_PAD_LEFT).' = '.rtrim(rtrim(number_format((float) $weight, 1, ',', ''), '0'), ','))
                ->implode(', ');
            $rows[] = ['label' => 'Pesos por dezena (distribuição artificial de geração)', 'value' => $weights];
        }

        foreach (['fixed_numbers' => 'Dezenas fixas', 'variable_numbers' => 'Dezenas variáveis'] as $key => $label) {
            if (! empty($parameters[$key]) && is_array($parameters[$key])) {
                $rows[] = ['label' => $label, 'value' => implode(', ', $parameters[$key])];
            }
        }

        if (isset($parameters['wheel_size'])) {
            $rows[] = ['label' => 'Tamanho da roda', 'value' => (string) $parameters['wheel_size']];
        }

        $reduced = $parameters['reduced_parameters'] ?? [];
        if (isset($reduced['guarantee_hits'])) {
            $rows[] = ['label' => 'Acertos no grupo-base (condição)', 'value' => (string) $reduced['guarantee_hits']];
        }
        if (isset($reduced['guarantee_points'])) {
            $rows[] = ['label' => 'Pontos pedidos', 'value' => (string) $reduced['guarantee_points']];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, int>>  $bets
     * @return array{computed: bool, min: int|null, max: int|null, note: string, classification: ResultClassification}
     */
    private function scoreRange(array $bets): array
    {
        $note = 'Score é uma pontuação descritiva de organização (padrões históricos). Não altera a probabilidade de a aposta ser sorteada.';

        if ($bets === [] || count($bets) > self::MAX_BETS_FOR_SCORES) {
            return [
                'computed' => false,
                'min' => null,
                'max' => null,
                'note' => $bets === []
                    ? 'Sem apostas geradas.'
                    : 'Faixa de score não calculada para mais de '.self::MAX_BETS_FOR_SCORES.' apostas. '.$note,
                'classification' => ResultClassification::Heuristic,
            ];
        }

        $scores = array_map(fn (array $bet): int => (int) $this->scoringService->calculateScore($bet)['total_score'], $bets);

        return [
            'computed' => true,
            'min' => min($scores),
            'max' => max($scores),
            'note' => $note,
            'classification' => ResultClassification::Heuristic,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $coverage
     * @return array<string, mixed>
     */
    private function guarantee(Closing $closing, ?array $coverage): array
    {
        if ($coverage === null) {
            return [
                'available' => false,
                'note' => 'Nenhuma garantia foi verificada para este fechamento. Sem verificação, não se deve supor garantia de prêmio.',
            ];
        }

        $simulated = ! empty($coverage['is_monte_carlo']);
        $confirmed = (bool) ($coverage['garantia_confirmada'] ?? false);

        return [
            'available' => true,
            'condition' => $coverage['condicoes_da_garantia'] ?? null,
            'classification' => $simulated ? ResultClassification::Simulated : ResultClassification::Exact,
            'confirmed' => $confirmed,
            'summary' => match (true) {
                $simulated && $confirmed => 'Nenhuma falha encontrada na amostra. Por ser amostragem, isso não prova a garantia.',
                $simulated => 'A amostra encontrou cenários sem a cobertura pedida. Não há garantia.',
                $confirmed => 'Garantia verificada em todos os cenários do grupo-base, somente nas condições descritas acima.',
                default => 'Garantia não atingida: existem cenários do grupo-base sem a cobertura pedida.',
            },
            'coverage_by_range' => $coverage['cobertura_por_faixa'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $probabilityReport
     * @param  array<string, mixed>|null  $coverage
     */
    private function validationMethod(?array $probabilityReport, ?array $coverage): string
    {
        $parts = [];

        if ($probabilityReport) {
            $parts[] = 'Chance de premiação: enumeração exata dos '.number_format((int) $probabilityReport['total_draws'], 0, ',', '.').' sorteios possíveis.';
        }

        if ($coverage) {
            $parts[] = ! empty($coverage['is_monte_carlo'])
                ? 'Garantia: amostragem aleatória de cenários do grupo-base (Monte Carlo), sem enumeração completa.'
                : 'Garantia: verificação de todos os cenários do grupo-base.';
        }

        return $parts === []
            ? 'Nenhuma validação calculada ainda. Use "Calcular probabilidades" para obter a chance exata de premiação.'
            : implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $coverage
     * @return array<int, string>
     */
    private function limitations(string $method, bool $hasProbability, ?array $coverage): array
    {
        $limitations = [
            'A conferência histórica mostra o que teria acontecido no passado; não prova causalidade nem indica o que vai acontecer.',
            'Frequência, atraso, ciclo, temperatura e score são descrições do passado. Uma dezena atrasada ou quente não tem mais nem menos chance de sair.',
        ];

        if (! $hasProbability) {
            $limitations[] = 'A chance real de premiação ainda não foi calculada para este fechamento.';
        }

        if ($coverage !== null && ! empty($coverage['is_monte_carlo'])) {
            $limitations[] = 'A verificação de garantia foi feita por amostragem e não substitui a enumeração completa.';
        }

        $limitations[] = match ($method) {
            'balanced', 'random' => 'Sem semente registrada, a geração não pode ser reproduzida exatamente.',
            'reduced' => 'O método guloso não prova que o fechamento use o menor número possível de apostas.',
            'wheel' => 'Não há cálculo de cobertura de pares e trincas para esta roda.',
            default => 'Fechamentos grandes custam mais; custo maior não significa previsão melhor.',
        };

        return $limitations;
    }

    /**
     * @return array<int, string>
     */
    private function randomnessNotes(string $method): array
    {
        $notes = ['O sorteio da Lotofácil é aleatório e uniforme: cada combinação de 15 dezenas tem a mesma probabilidade (1 em 3.268.760).'];

        if (in_array($method, ['balanced', 'random', 'reduced'], true)) {
            $notes[] = 'Esta geração usa números pseudoaleatórios; apostas diferentes não são "melhores" ou "piores" por isso.';
        }

        return $notes;
    }
}
