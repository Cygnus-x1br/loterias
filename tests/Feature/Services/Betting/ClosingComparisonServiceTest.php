<?php

namespace Tests\Feature\Services\Betting;

use App\Models\User;
use App\Services\Betting\ClosingComparisonService;
use App\Services\Betting\ResultClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingComparisonServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_compares_methods_under_the_same_base_budget_and_universe(): void
    {
        $result = app(ClosingComparisonService::class)->compare(range(1, 18), 8, ['random', 'balanced', 'reduced'], 42);

        $this->assertSame(3268760, $result['universe']);
        $this->assertSame(range(1, 18), $result['base_numbers']);
        $this->assertSame(['random', 'balanced', 'reduced'], array_keys($result['results']));

        foreach (['random', 'balanced'] as $method) {
            $row = $result['results'][$method];
            $this->assertNull($row['error']);
            $this->assertSame(8, $row['bets_count']);
            $this->assertEqualsWithDelta(8 * 3.5, $row['cost'], 1.0);
            $this->assertSame(ResultClassification::Heuristic, $row['classification']);
            $this->assertSame(ResultClassification::Exact, $row['coverage_classification']);
            $this->assertSame([11, 12, 13, 14, 15], array_keys($row['coverage']));
            $this->assertGreaterThanOrEqual($row['coverage'][12]['probability'], $row['coverage'][11]['probability']);
            $this->assertLessThanOrEqual($row['scores']['max'], $row['scores']['min']);
            $this->assertGreaterThan(0, $row['time_ms']);
            $this->assertSame(8 * 15, array_sum($row['distribution']['usage']));
            $this->assertTrue($row['guarantee']['evaluated']);
            $this->assertSame(ResultClassification::Exact, $row['guarantee']['classification']);
        }

        $reduced = $result['results']['reduced'];
        $this->assertNull($reduced['error']);
        $this->assertLessThanOrEqual(8, $reduced['bets_count']);
    }

    public function test_same_seed_reproduces_the_same_bets_and_other_seed_differs(): void
    {
        $service = app(ClosingComparisonService::class);

        $first = $service->compare(range(1, 20), 5, ['random', 'balanced'], 7);
        $second = $service->compare(range(1, 20), 5, ['random', 'balanced'], 7);
        $other = $service->compare(range(1, 20), 5, ['random', 'balanced'], 8);

        foreach (['random', 'balanced'] as $method) {
            $this->assertSame($first['results'][$method]['bets'], $second['results'][$method]['bets']);
            $this->assertEquals($first['results'][$method]['coverage'], $second['results'][$method]['coverage']);
        }
        $this->assertNotSame($first['results']['random']['bets'], $other['results']['random']['bets']);
    }

    public function test_same_seed_gives_each_method_the_same_starting_point_regardless_of_order(): void
    {
        $service = app(ClosingComparisonService::class);

        $a = $service->compare(range(1, 20), 5, ['random', 'balanced'], 99);
        $b = $service->compare(range(1, 20), 5, ['balanced', 'random'], 99);

        $this->assertSame($a['results']['random']['bets'], $b['results']['random']['bets']);
        $this->assertSame($a['results']['balanced']['bets'], $b['results']['balanced']['bets']);
    }

    public function test_spread_option_lowers_the_average_overlap_on_average(): void
    {
        $service = app(ClosingComparisonService::class);
        $plain = [];
        $spread = [];

        foreach ([1, 2, 3] as $seed) {
            $result = $service->compare(range(1, 21), 10, ['balanced', 'balanced_spread'], $seed);
            $plain[] = $result['results']['balanced']['redundancy']['average_overlap'];
            $spread[] = $result['results']['balanced_spread']['redundancy']['average_overlap'];
        }

        $this->assertLessThan(array_sum($plain) / 3, array_sum($spread) / 3);
    }

    public function test_guarantee_is_not_evaluated_for_large_bases_and_method_errors_are_reported(): void
    {
        $result = app(ClosingComparisonService::class)->compare(range(1, 25), 4, ['random', 'reduced'], 1);

        $this->assertFalse($result['results']['random']['guarantee']['evaluated']);
        $this->assertStringContainsString('Não avaliada', $result['results']['random']['guarantee']['note']);

        // O reduzido aceita no máximo 23 dezenas: o erro aparece na coluna, sem derrubar a comparação
        $this->assertNotNull($result['results']['reduced']['error']);
        $this->assertNull($result['results']['random']['error']);
    }

    public function test_reduced_is_skipped_quickly_for_bases_too_large_for_the_greedy_method(): void
    {
        $startedAt = microtime(true);

        $result = app(ClosingComparisonService::class)->compare(range(1, 21), 4, ['random', 'reduced'], 3);

        $this->assertLessThan(30, microtime(true) - $startedAt);
        $this->assertStringContainsString('até 18 dezenas', $result['results']['reduced']['error']);
        $this->assertNull($result['results']['random']['error']);
        $this->assertSame(4, $result['results']['random']['bets_count']);
    }

    public function test_rejects_invalid_requests(): void
    {
        $service = app(ClosingComparisonService::class);

        foreach ([
            [range(1, 10), 5, ['random', 'balanced']],
            [range(1, 20), 1, ['random', 'balanced']],
            [range(1, 20), 31, ['random', 'balanced']],
            [range(1, 20), 5, ['random']],
            [range(1, 20), 5, ['random', 'magico']],
        ] as [$base, $budget, $methods]) {
            try {
                $service->compare($base, $budget, $methods, 1);
                $this->fail('Deveria rejeitar: '.json_encode([count($base), $budget, $methods]));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_compare_page_requires_login_and_runs_the_comparison(): void
    {
        $this->get(route('closings.compare'))->assertRedirect(route('login'));

        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->assertSee('Comparar métodos de geração')
            ->set('baseNumbers', range(1, 18))
            ->set('betsBudget', 4)
            ->set('methods', ['random', 'balanced'])
            ->set('seed', 5)
            ->call('compare')
            ->assertHasNoErrors()
            ->assertSee('Chance de pelo menos 11 pontos')
            ->assertSee('Semente aleatória')
            ->assertSee('Exato')
            ->assertSee('Heurístico');
    }

    public function test_compare_page_shows_validation_messages(): void
    {
        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->set('baseNumbers', range(1, 10))
            ->set('methods', ['random', 'balanced'])
            ->call('compare')
            ->assertSee('O grupo-base deve ter entre 16 e 25 dezenas.');

        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->set('betsBudget', 99)
            ->call('compare')
            ->assertHasErrors(['betsBudget']);
    }

    public function test_compare_page_toggles_numbers_in_sorted_order(): void
    {
        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->call('toggleNumber', 9)
            ->call('toggleNumber', 3)
            ->assertSet('baseNumbers', [3, 9])
            ->call('toggleNumber', 9)
            ->assertSet('baseNumbers', [3]);
    }

    public function test_compare_page_unchecks_and_locks_reduced_above_18_numbers(): void
    {
        $component = Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->set('baseNumbers', range(1, 17))
            ->assertSet('methods', ['random', 'balanced', 'balanced_spread', 'reduced'])
            ->assertDontSeeHtml('data-method="reduced" disabled');

        // 19ª dezena: o reduzido sai da seleção e o campo é bloqueado
        $component->set('baseNumbers', range(1, 18))
            ->call('toggleNumber', 19)
            ->assertSet('methods', ['random', 'balanced', 'balanced_spread'])
            ->assertSeeHtml('data-method="reduced" disabled');

        // Voltando a 18 dezenas, o campo é liberado (sem remarcar sozinho)
        $component->call('toggleNumber', 19)
            ->assertSet('methods', ['random', 'balanced', 'balanced_spread'])
            ->assertDontSeeHtml('data-method="reduced" disabled');
    }

    public function test_compare_page_ignores_a_reduced_selection_forced_with_a_large_base(): void
    {
        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.compare')
            ->set('baseNumbers', range(1, 20))
            ->set('methods', ['random', 'reduced'])
            ->set('betsBudget', 3)
            ->call('compare')
            ->assertSet('methods', ['random'])
            ->assertSee('Escolha pelo menos dois métodos');
    }
}
