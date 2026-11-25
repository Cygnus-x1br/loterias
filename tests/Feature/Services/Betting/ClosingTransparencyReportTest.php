<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use App\Services\Betting\ClosingProbabilityService;
use App\Services\Betting\ClosingTransparencyReportService;
use App\Services\Betting\CoverageAuditorService;
use App\Services\Betting\ResultClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingTransparencyReportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function closingWithBets(array $attributes = []): Closing
    {
        $closing = Closing::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'method' => 'balanced',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 2,
            'parameters' => ['even_odd_balance' => [6, 9], 'number_weights' => [3 => 0.0, 7 => 4.0]],
            'generation_warnings' => ['Aviso de teste'],
        ], $attributes));

        foreach ([range(1, 15), range(4, 18)] as $numbers) {
            Bet::factory()->create(['user_id' => $closing->user_id, 'closing_id' => $closing->id, 'numbers' => $numbers]);
        }

        return $closing;
    }

    public function test_enum_exposes_the_five_categories_with_labels(): void
    {
        $labels = array_map(fn (ResultClassification $case): string => $case->label(), ResultClassification::cases());

        $this->assertSame(['Exato', 'Ótimo comprovado', 'Viável', 'Heurístico', 'Simulado'], $labels);
        foreach (ResultClassification::cases() as $case) {
            $this->assertNotSame('', $case->description());
            $this->assertNotSame('', $case->badgeClasses());
        }
    }

    public function test_generation_classification_by_method(): void
    {
        $service = app(ClosingTransparencyReportService::class);

        $this->assertSame(ResultClassification::Exact, $service->generationClassification('integral'));
        $this->assertSame(ResultClassification::Feasible, $service->generationClassification('wheel'));
        foreach (['balanced', 'random', 'reduced'] as $method) {
            $this->assertSame(ResultClassification::Heuristic, $service->generationClassification($method));
        }
    }

    public function test_report_covers_identification_cost_parameters_and_limitations(): void
    {
        $report = app(ClosingTransparencyReportService::class)->build($this->closingWithBets());

        $this->assertSame('Geração equilibrada', $report['method']['label']);
        $this->assertSame(ResultClassification::Heuristic, $report['method']['classification']);
        $this->assertSame(2, $report['bets_count']);
        $this->assertEqualsWithDelta($report['cost']['unit'] * 2, $report['cost']['total'], 1e-9);
        $this->assertFalse($report['seed']['recorded']);
        $this->assertSame(['Aviso de teste'], $report['warnings']);

        $labels = array_column($report['parameters'], 'label');
        $this->assertContains('Pares por aposta', $labels);
        $this->assertContains('Pesos por dezena (distribuição artificial de geração)', $labels);

        $this->assertTrue($report['scores']['computed']);
        $this->assertLessThanOrEqual($report['scores']['max'], $report['scores']['min']);
        $this->assertSame(ResultClassification::Heuristic, $report['scores']['classification']);

        $this->assertFalse($report['guarantee']['available']);
        $this->assertNull($report['probability']);
        $this->assertStringContainsString('ainda não foi calculada', implode(' ', $report['limitations']));
        $this->assertStringContainsString('não promete prêmio', $report['disclaimer']);
    }

    public function test_exact_probability_and_exact_guarantee_are_labelled_exact(): void
    {
        $closing = $this->closingWithBets();
        $probability = app(ClosingProbabilityService::class)->calculate([range(1, 15), range(4, 18)]);
        $coverage = app(CoverageAuditorService::class)->auditCoverage(range(1, 16), [range(1, 15)], 14, 15);

        $report = app(ClosingTransparencyReportService::class)->build($closing, $probability, $coverage);

        $this->assertSame(ResultClassification::Exact, $report['probability']['classification']);
        $this->assertSame(ResultClassification::Exact, $report['guarantee']['classification']);
        $this->assertSame(3268760, $report['scenarios_evaluated']);
        $this->assertStringContainsString('enumeração exata', $report['validation']);
    }

    public function test_monte_carlo_guarantee_is_labelled_simulated_and_never_a_proof(): void
    {
        $coverage = [
            'quantidade_cenarios_analisados' => 1000,
            'is_monte_carlo' => true,
            'cobertura_por_faixa' => [],
            'garantia_confirmada' => true,
            'condicoes_da_garantia' => 'Estimativa por simulação',
        ];

        $report = app(ClosingTransparencyReportService::class)->build($this->closingWithBets(), null, $coverage);

        $this->assertSame(ResultClassification::Simulated, $report['guarantee']['classification']);
        $this->assertStringContainsString('não prova a garantia', $report['guarantee']['summary']);
        $this->assertStringContainsString('amostragem', $report['validation']);
        $this->assertStringContainsString('amostragem', implode(' ', $report['limitations']));
    }

    public function test_auditor_no_longer_calls_a_simulation_a_guarantee(): void
    {
        $coverage = app(CoverageAuditorService::class)->auditCoverageMonteCarlo(range(1, 20), [range(1, 15)], 14, 15, 200);

        $this->assertStringNotContainsString('Garante', $coverage['condicoes_da_garantia']);
        $this->assertStringContainsString('não é garantia', $coverage['condicoes_da_garantia']);
    }

    public function test_show_page_toggles_the_report_with_classification_badges(): void
    {
        $closing = $this->closingWithBets();

        Volt::actingAs($closing->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertSee('Relatório de transparência')
            ->assertDontSee('Método de validação')
            ->call('toggleTransparencyReport')
            ->assertSee('Método de validação')
            ->assertSee('Heurístico')
            ->assertSee('Não registrada')
            ->assertSee('não promete prêmio');
    }

    public function test_communication_rules_in_optimizer_simulation_and_education_pages(): void
    {
        $user = User::factory()->create();
        $closing = $this->closingWithBets(['user_id' => $user->id]);

        Volt::actingAs($user)
            ->test('pages.closings.optimize', ['closing' => $closing])
            ->assertSee('Heurístico')
            ->assertSee('não aumentam a chance de acerto')
            ->assertDontSee('jogos mais fortes');

        Volt::actingAs($user)
            ->test('pages.simulations.index')
            ->assertSee('Resultado histórico')
            ->assertSee('Simulado')
            ->assertSee('É uma estimativa, não uma previsão')
            ->assertDontSee('para prever a taxa de lucro');
    }
}
