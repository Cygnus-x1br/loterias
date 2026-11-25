<?php

namespace Tests\Feature\Services;

use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\BaseNumbersSuggestionService;
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
}
