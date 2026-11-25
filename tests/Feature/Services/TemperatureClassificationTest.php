<?php

namespace Tests\Feature\Services;

use App\Models\HistoricalResult;
use App\Services\LotofacilStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemperatureClassificationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LotofacilStatisticsService
    {
        return app(LotofacilStatisticsService::class);
    }

    public function test_classifies_by_frequency_eight_hot_nine_neutral_eight_cold(): void
    {
        // Frequência decrescente da dezena 1 (25) até a 25 (1); sem empates
        $frequencies = array_combine(range(1, 25), range(25, 1));
        $delays = array_fill(1, 25, 0);

        $temperatures = $this->service()->classifyTemperatures($frequencies, $delays);

        $this->assertSame(range(1, 8), array_keys(array_filter($temperatures, fn (string $t): bool => $t === 'hot')));
        $this->assertSame(range(9, 17), array_keys(array_filter($temperatures, fn (string $t): bool => $t === 'neutral')));
        $this->assertSame(range(18, 25), array_keys(array_filter($temperatures, fn (string $t): bool => $t === 'cold')));
    }

    public function test_delay_never_makes_a_number_hotter(): void
    {
        $frequencies = array_combine(range(1, 25), range(25, 1));
        $delays = array_fill(1, 25, 0);

        // A dezena 25 é a menos frequente; um atraso enorme não pode tirá-la das frias
        $delays[25] = 30;

        $this->assertSame('cold', $this->service()->classifyTemperatures($frequencies, $delays)[25]);
    }

    public function test_ties_are_broken_by_most_recent_appearance(): void
    {
        $frequencies = array_fill(1, 25, 12);
        $delays = array_combine(range(1, 25), range(24, 0)); // a dezena 25 saiu no último concurso

        $temperatures = $this->service()->classifyTemperatures($frequencies, $delays);

        $this->assertSame('hot', $temperatures[25]);
        $this->assertSame('cold', $temperatures[1]);
    }

    public function test_generator_rule_and_comparison_page_agree_for_the_same_window(): void
    {
        mt_srand(11);
        for ($contest = 1; $contest <= 30; $contest++) {
            $numbers = range(1, 25);
            shuffle($numbers);
            $numbers = array_slice($numbers, 0, 15);
            sort($numbers);

            HistoricalResult::create([
                'contest_number' => $contest,
                'draw_date' => now()->subDays(40 - $contest)->toDateString(),
                'drawn_numbers' => $numbers,
                'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
                'score' => 500,
            ]);
        }

        $classification = $this->service()->getNumberTemperatureClassification(25);
        $comparisonMap = $this->service()->getContestsComparisonAnalysis(25)['temperature_map'];

        foreach (range(1, 25) as $number) {
            $this->assertSame(
                $classification[$number]['temperature'],
                $comparisonMap[$number]['temperature'],
                "Dezena {$number}"
            );
        }
    }
}
