<?php

namespace Tests\Feature\Services;

use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\BaseNumbersSuggestionService;
use App\Services\LotofacilStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BaseNumbersSuggestionServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Dezenas do último concurso cadastrado nos testes.
     *
     * @var array<int, int>
     */
    private array $lastDrawn;

    protected function setUp(): void
    {
        parent::setUp();

        $draws = [
            1 => range(11, 25),
            2 => range(6, 20),
            3 => range(1, 15),
        ];

        foreach ($draws as $contestNumber => $numbers) {
            HistoricalResult::create([
                'contest_number' => $contestNumber,
                'draw_date' => now()->subDays(10 - $contestNumber)->toDateString(),
                'drawn_numbers' => $numbers,
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
                'score' => 500,
            ]);
        }

        $this->lastDrawn = range(1, 15);
    }

    private function service(): BaseNumbersSuggestionService
    {
        return app(BaseNumbersSuggestionService::class);
    }

    public function test_repetition_range_reflects_numbers_outside_last_draw(): void
    {
        $this->assertSame(['min' => 5, 'max' => 15], $this->service()->repetitionRange(15));
        $this->assertSame(['min' => 8, 'max' => 15], $this->service()->repetitionRange(18));
        $this->assertSame(['min' => 15, 'max' => 15], $this->service()->repetitionRange(25));
    }

    public function test_builds_base_with_exact_size_and_repetitions_for_every_valid_combination(): void
    {
        foreach (range(15, 25) as $totalBase) {
            $range = $this->service()->repetitionRange($totalBase);

            foreach (range($range['min'], $range['max']) as $repetitions) {
                $suggestion = $this->service()->suggest($totalBase, $repetitions);
                $numbers = $suggestion['numbers'];
                $report = $suggestion['report'];
                $label = "base {$totalBase}, repetidas {$repetitions}";

                $this->assertCount($totalBase, $numbers, $label);
                $this->assertSame($numbers, array_values(array_unique($numbers)), $label);
                $this->assertSame($repetitions, $report['repetitions'], $label);
                $this->assertNull($report['adjustment'], $label);
                $this->assertSame([], array_values(array_diff($report['repeated_list'], $this->lastDrawn)), $label);
                $this->assertSame([], array_values(array_intersect($report['new_list'], $this->lastDrawn)), $label);
                $this->assertGreaterThanOrEqual(1, min($numbers), $label);
                $this->assertLessThanOrEqual(25, max($numbers), $label);
            }
        }
    }

    public function test_adjusts_repetitions_when_there_are_not_enough_new_numbers(): void
    {
        $suggestion = $this->service()->suggest(20, 8);

        $this->assertCount(20, $suggestion['numbers']);
        $this->assertSame(10, $suggestion['report']['repetitions']);
        $this->assertSame(10, $suggestion['report']['new_numbers']);
        $this->assertSame(8, $suggestion['report']['requested_repetitions']);
        $this->assertNotNull($suggestion['report']['adjustment']);

        $fullBase = $this->service()->suggest(25, 0);

        $this->assertSame(range(1, 25), $fullBase['numbers']);
        $this->assertSame(15, $fullBase['report']['repetitions']);
    }

    public function test_rejects_invalid_base_size_and_repetitions(): void
    {
        foreach ([[14, 5], [26, 15], [18, -1], [18, 16]] as [$totalBase, $repetitions]) {
            try {
                $this->service()->suggest($totalBase, $repetitions);
                $this->fail("Deveria rejeitar base {$totalBase} com {$repetitions} repetidas.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_new_numbers_rebalance_parity_of_the_whole_group(): void
    {
        // As 10 dezenas fora do último concurso (16 a 25) têm 5 pares e 5 ímpares.
        // Escolhendo 6 delas, o grupo final deve ficar equilibrado em vez de puxar para uma paridade só.
        foreach (range(1, 30) as $run) {
            $numbers = $this->service()->suggest(15, 9)['numbers'];
            $evens = count(array_filter($numbers, fn (int $n): bool => $n % 2 === 0));
            $odds = count($numbers) - $evens;

            $this->assertLessThanOrEqual(1, abs($evens - $odds), "Execução {$run}: {$evens} pares / {$odds} ímpares");
        }
    }

    public function test_create_page_applies_suggestion_with_requested_size(): void
    {
        $user = User::factory()->create();

        $component = Volt::actingAs($user)
            ->test('pages.closings.create')
            ->call('generateStatisticalBaseNumbers', 20, 8);

        $this->assertCount(20, $component->get('base_numbers'));
        $this->assertSame(10, $component->get('statisticalReport')['repetitions']);
        $this->assertNotNull($component->get('statisticalReport')['adjustment']);
    }

    public function test_suggestion_is_deterministic_without_seed(): void
    {
        $first = $this->service()->suggest(21, 12);
        $second = $this->service()->suggest(21, 12);

        $this->assertSame($first['numbers'], $second['numbers']);
        $this->assertNull($first['report']['seed']);
    }

    public function test_seed_varies_the_suggestion_reproducibly(): void
    {
        $default = $this->service()->suggest(21, 12)['numbers'];

        $this->assertSame($this->service()->suggest(21, 12, 3)['numbers'], $this->service()->suggest(21, 12, 3)['numbers']);

        $variations = array_map(fn (int $seed): array => $this->service()->suggest(21, 12, $seed)['numbers'], range(1, 5));
        $this->assertNotEmpty(array_filter($variations, fn (array $numbers): bool => $numbers !== $default));
        $this->assertSame(3, $this->service()->suggest(21, 12, 3)['report']['seed']);
    }

    public function test_second_closing_prioritizes_numbers_left_out_of_the_first(): void
    {
        $first = $this->service()->suggest(21, 12)['numbers'];
        $leftOut = array_values(array_diff(range(1, 25), $first));

        $second = $this->service()->suggest(21, 12, null, [$first]);

        $this->assertNotSame($first, $second['numbers']);
        $this->assertSame([], array_values(array_diff($leftOut, $second['numbers'])));
        $this->assertSame(12, $second['report']['repetitions']);
        $this->assertSame(1, $second['report']['previous_closings']);
        $this->assertSame($leftOut, $second['report']['prioritized_numbers']);
        $this->assertTrue($second['report']['distinct_from_previous']);
        $this->assertStringContainsString('diferente de todos os anteriores', $second['report']['criteria']['previous']);

        $third = $this->service()->suggest(21, 12, null, [$first, $second['numbers']]);

        $this->assertNotSame($first, $third['numbers']);
        $this->assertNotSame($second['numbers'], $third['numbers']);
        $this->assertSame(2, $third['report']['previous_closings']);
    }

    public function test_reports_when_a_different_group_is_impossible(): void
    {
        $suggestion = $this->service()->suggest(25, 15, null, [range(1, 25)]);

        $this->assertFalse($suggestion['report']['distinct_from_previous']);
        $this->assertStringContainsString('Não foi possível formar um grupo diferente', $suggestion['report']['criteria']['previous']);
    }

    public function test_swap_keeps_group_sizes_when_the_group_repeats_a_previous_one(): void
    {
        $service = new class(app(LotofacilStatisticsService::class)) extends BaseNumbersSuggestionService
        {
            public function distinct(array $repeated, array $new, array $lastDrawn, array $candidates, array $scores, array $previous): array
            {
                return $this->ensureDistinct($repeated, $new, $lastDrawn, $candidates, $scores, $previous);
            }
        };

        $lastDrawn = range(1, 15);
        $candidates = range(16, 25);
        $scores = array_fill_keys(range(1, 25), 1.0);
        $repeated = range(1, 12);
        $new = range(16, 24);
        $previous = [array_merge($repeated, $new)];

        [$newRepeated, $newNew, $distinct] = $service->distinct($repeated, $new, $lastDrawn, $candidates, $scores, $previous);

        $group = array_merge($newRepeated, $newNew);
        sort($group);

        $this->assertTrue($distinct);
        $this->assertNotSame($previous[0], $group);
        $this->assertCount(12, $newRepeated);
        $this->assertCount(9, $newNew);
        $this->assertSame([], array_values(array_diff($newRepeated, $lastDrawn)));
    }

    public function test_previous_bases_only_include_own_closings_for_the_same_contest(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $sameTarget = Closing::factory()->create(['user_id' => $user->id, 'target_contest_number' => 4, 'base_numbers' => range(1, 21)]);
        Closing::factory()->create(['user_id' => $user->id, 'contest_number' => 4, 'status' => 'placed', 'base_numbers' => range(5, 25)]);
        Closing::factory()->create(['user_id' => $user->id, 'target_contest_number' => 5, 'base_numbers' => range(2, 22)]);
        Closing::factory()->create(['user_id' => $other->id, 'target_contest_number' => 4, 'base_numbers' => range(3, 23)]);

        $this->assertSame([range(1, 21), range(5, 25)], $this->service()->previousBasesFor($user->id, 4));
        $this->assertSame([range(5, 25)], $this->service()->previousBasesFor($user->id, 4, $sameTarget->id));
        $this->assertSame([], $this->service()->previousBasesFor($user->id, null));
        $this->assertSame(4, $this->service()->nextContestNumber());
    }

    public function test_create_page_builds_a_different_group_for_the_same_contest(): void
    {
        $user = User::factory()->create();
        $firstGroup = $this->service()->suggest(21, 12)['numbers'];
        Closing::factory()->create(['user_id' => $user->id, 'target_contest_number' => 4, 'base_numbers' => $firstGroup]);

        $component = Volt::actingAs($user)
            ->test('pages.closings.create')
            ->assertSet('target_contest_number', 4)
            ->call('generateStatisticalBaseNumbers', 21, 12, false);

        $this->assertNotSame($firstGroup, $component->get('base_numbers'));
        $this->assertSame(1, $component->get('statisticalReport')['previous_closings']);

        $component->call('generateStatisticalBaseNumbers', 21, 12, true)
            ->assertSet('suggestionSeed', 1)
            ->call('generateStatisticalBaseNumbers', 21, 12, true)
            ->assertSet('suggestionSeed', 2)
            ->call('generateStatisticalBaseNumbers', 21, 12, false)
            ->assertSet('suggestionSeed', null);

        // Para outro concurso, não há fechamento anterior
        $component->set('target_contest_number', 5)
            ->call('generateStatisticalBaseNumbers', 21, 12, false);

        $this->assertSame(0, $component->get('statisticalReport')['previous_closings']);
        $this->assertSame($firstGroup, $component->get('base_numbers'));
    }

    public function test_edit_page_ignores_the_closing_being_edited(): void
    {
        $user = User::factory()->create();
        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'balanced',
            'target_contest_number' => 4,
            'base_numbers' => $this->service()->suggest(21, 12)['numbers'],
        ]);

        $component = Volt::actingAs($user)
            ->test('pages.closings.edit', ['closing' => $closing])
            ->assertSet('target_contest_number', 4)
            ->call('generateStatisticalBaseNumbers', 21, 12, false);

        $this->assertSame(0, $component->get('statisticalReport')['previous_closings']);
    }
}
