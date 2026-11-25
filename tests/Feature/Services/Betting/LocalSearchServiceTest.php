<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\Betting\ClosingComparisonService;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\LocalSearchService;
use App\Services\Betting\ResultClassification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LocalSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Apostas quase iguais: ponto de partida ruim (muita redundância, pouca cobertura).
     *
     * @return array<int, array<int, int>>
     */
    private function poorBets(int $count = 6): array
    {
        $pairs = [[14, 15], [14, 16], [14, 17], [14, 18], [15, 16], [15, 17], [15, 18], [16, 17], [16, 18], [17, 18]];

        return array_map(fn (array $pair): array => [...range(1, 13), ...$pair], array_slice($pairs, 0, $count));
    }

    private function search(array $bets, array $options = [], int $base = 18): array
    {
        return app(LocalSearchService::class)->improve(range(1, $base), $bets, array_merge(['seed' => 11, 'time_limit' => 20], $options));
    }

    public function test_improves_a_poor_solution_and_keeps_every_bet_valid(): void
    {
        $bets = $this->poorBets();
        $result = $this->search($bets, ['max_iterations' => 600, 'max_stale' => 600]);

        $this->assertTrue($result['improved']);
        $this->assertGreaterThan($result['before']['f'], $result['after']['f']);
        $this->assertGreaterThan($result['before']['coverage'][14], $result['after']['coverage'][14]);
        $this->assertLessThan($result['before']['redundancy'], $result['after']['redundancy']);
        $this->assertSame(ResultClassification::Heuristic, $result['classification']);
        $this->assertSame(816, $result['scenarios']);

        $keys = [];
        foreach ($result['bets'] as $bet) {
            $this->assertCount(15, $bet);
            $this->assertSame([], array_diff($bet, range(1, 18)));
            $keys[implode('-', $bet)] = true;
        }
        $this->assertCount(count($result['bets']), $keys);
        $this->assertLessThanOrEqual(count($bets), count($result['bets']));
        $this->assertSame($result['after']['count'], count($result['bets']));
    }

    public function test_is_reproducible_with_the_same_seed(): void
    {
        $bets = $this->poorBets();
        $options = ['max_iterations' => 150, 'max_stale' => 150];

        $first = $this->search($bets, $options);
        $second = $this->search($bets, $options);
        $other = $this->search($bets, $options + ['seed' => 12]);

        $this->assertSame($first['bets'], $second['bets']);
        $this->assertEquals($first['after'], $second['after']);
        $this->assertNotSame($first['bets'], $other['bets']);
    }

    public function test_stop_reasons(): void
    {
        $bets = $this->poorBets();

        $this->assertSame('max_iterations', $this->search($bets, ['max_iterations' => 0])['stop_reason']);
        $this->assertSame('time_limit', $this->search($bets, ['time_limit' => 0])['stop_reason']);
        $this->assertSame('stale', $this->search($bets, ['max_stale' => 0])['stop_reason']);

        // Cobertura desejada já atingida: para sem nenhuma iteração
        $reached = $this->search($bets, ['target' => ['hits' => 11, 'coverage' => 0.0]]);
        $this->assertSame('target', $reached['stop_reason']);
        $this->assertSame(0, $reached['iterations']);
        $this->assertFalse($reached['improved']);

        $this->assertSame('Cobertura desejada atingida', $reached['stop_reason_label']);
    }

    public function test_high_cost_penalty_removes_redundant_bets(): void
    {
        $bets = $this->poorBets(6);
        $result = $this->search($bets, ['cost_penalty' => 5.0, 'redundancy_penalty' => 0.0, 'max_iterations' => 400, 'max_stale' => 400, 'min_bets' => 1]);

        $this->assertLessThan(count($bets), count($result['bets']));
        $this->assertGreaterThan(0, $result['accepted']['remove']);
    }

    public function test_never_grows_beyond_max_bets_and_can_insert_when_allowed(): void
    {
        $bets = array_slice($this->poorBets(), 0, 2);

        // Por padrão o orçamento é fixo: o número de apostas não muda
        $fixed = $this->search($bets, ['max_iterations' => 300, 'max_stale' => 300]);
        $this->assertCount(2, $fixed['bets']);
        $this->assertSame(0, $fixed['attempted']['remove'] + $fixed['attempted']['insert']);

        $growing = $this->search($bets, ['cost_penalty' => 0.0, 'min_bets' => 2, 'max_bets' => 5, 'max_iterations' => 400, 'max_stale' => 400]);
        $this->assertLessThanOrEqual(5, count($growing['bets']));
        $this->assertGreaterThan(2, count($growing['bets']));
        $this->assertGreaterThan(0, $growing['accepted']['insert']);
    }

    public function test_every_move_type_is_attempted(): void
    {
        $result = $this->search($this->poorBets(), ['cost_penalty' => 0.0, 'min_bets' => 1, 'max_bets' => 8, 'max_iterations' => 400, 'max_stale' => 400]);

        foreach (array_keys(LocalSearchService::MOVES) as $move) {
            $this->assertGreaterThan(0, $result['attempted'][$move], "Movimento {$move} nunca foi tentado");
        }
        $this->assertSame($result['iterations'], array_sum($result['attempted']));
    }

    public function test_never_produces_a_bet_already_drawn_in_the_past(): void
    {
        $drawn = range(1, 15);
        HistoricalResult::create([
            'contest_number' => 1,
            'draw_date' => now()->subDay()->toDateString(),
            'drawn_numbers' => $drawn,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($drawn),
            'score' => 500,
        ]);

        $result = $this->search([[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 16]], ['cost_penalty' => 0.0, 'max_bets' => 6, 'max_iterations' => 800, 'max_stale' => 800]);

        foreach ($result['bets'] as $bet) {
            $this->assertNotSame($drawn, $bet);
        }
    }

    public function test_rejects_invalid_input(): void
    {
        $service = app(LocalSearchService::class);
        $valid = [range(1, 15)];

        foreach ([
            [range(1, 10), $valid],
            [range(1, 22), $valid],
            [range(1, 18), []],
            [range(1, 18), [range(1, 14)]],
            [range(1, 18), [range(5, 19)]],
            [range(1, 18), [range(1, 15), range(1, 15)]],
        ] as [$base, $bets]) {
            try {
                $service->improve($base, $bets);
                $this->fail('Deveria rejeitar a entrada.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private function completedClosing(?int $baseSize = 18): Closing
    {
        $closing = Closing::factory()->create([
            'user_id' => User::factory()->create()->id,
            'method' => 'random',
            'status' => 'completed',
            'base_numbers' => range(1, $baseSize),
            'bet_size' => 15,
            'planned_bets' => 6,
        ]);

        foreach ($this->poorBets(6) as $numbers) {
            Bet::factory()->create(['user_id' => $closing->user_id, 'closing_id' => $closing->id, 'numbers' => $numbers]);
        }

        return $closing;
    }

    public function test_replace_bets_swaps_the_set_and_validates_every_bet(): void
    {
        $closing = $this->completedClosing();
        $generator = app(ClosingGenerator::class);
        $new = [range(1, 15), range(2, 16), range(4, 18)];

        $this->assertSame(3, $generator->replaceBets($closing, $new));
        $this->assertSame($new, $closing->bets()->orderBy('id')->pluck('numbers')->all());

        foreach ([[range(1, 14)], [[...range(1, 14), 19]], [range(1, 15), range(1, 15)], []] as $invalid) {
            try {
                $generator->replaceBets($closing, $invalid);
                $this->fail('Deveria rejeitar.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(3, $closing->bets()->count());

        $closing->update(['status' => 'placed']);
        $this->expectException(\LogicException::class);
        $generator->replaceBets($closing, $new);
    }

    public function test_show_page_runs_previews_and_applies_the_local_search(): void
    {
        $closing = $this->completedClosing();
        $before = $closing->bets()->orderBy('id')->pluck('numbers')->all();

        $component = Volt::actingAs($closing->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertSee('Melhorar com busca local')
            ->call('runLocalSearch')
            ->assertSee('Heurístico')
            ->assertSee('Redundância')
            ->assertSee('Aplicar melhoria');

        // Rodar não grava nada
        $this->assertSame($before, $closing->bets()->orderBy('id')->pluck('numbers')->all());

        $component->call('applyLocalSearch')->assertSee('atualizada(s) pela busca local');

        $after = $closing->bets()->orderBy('id')->pluck('numbers')->all();
        $this->assertCount(6, $after);
        $this->assertNotSame($before, $after);
    }

    public function test_preview_cannot_be_tampered_with_and_panel_is_hidden_after_betting(): void
    {
        $closing = $this->completedClosing();

        try {
            Volt::actingAs($closing->user)
                ->test('pages.closings.show', ['closing' => $closing])
                ->set('localSearchPreview', ['bets' => [range(1, 15)]]);
            $this->fail('A prévia deveria estar protegida contra edição pelo navegador.');
        } catch (\Throwable $exception) {
            $this->assertStringContainsString('locked property', $exception->getMessage());
        }

        $closing->update(['status' => 'placed']);

        Volt::actingAs($closing->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertDontSee('Melhorar com busca local');
    }

    public function test_local_search_is_available_in_the_method_comparison(): void
    {
        $result = app(ClosingComparisonService::class)
            ->compare(range(1, 18), 6, ['balanced_spread', 'balanced_local'], 5);

        $local = $result['results']['balanced_local'];
        $this->assertNull($local['error']);
        $this->assertSame(6, $local['bets_count']);
        $this->assertSame(ResultClassification::Heuristic, $local['classification']);
        $this->assertGreaterThan(0, $local['time_ms']);
    }
}
