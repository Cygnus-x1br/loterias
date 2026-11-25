<?php

namespace Tests\Feature\Services\Betting;

use App\Services\Betting\ClosingProbabilityService;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class ClosingProbabilityServiceTest extends TestCase
{
    private function service(): ClosingProbabilityService
    {
        return app(ClosingProbabilityService::class);
    }

    public function test_single_bet_matches_the_hypergeometric_distribution(): void
    {
        $result = $this->service()->calculate([range(1, 15)]);

        // C(15,k)·C(10,15−k) acumulado de 15 até k
        $this->assertSame(1, $result['at_least'][15]['favorable']);
        $this->assertSame(1 + 150, $result['at_least'][14]['favorable']);
        $this->assertSame(1 + 150 + 4725, $result['at_least'][13]['favorable']);
        $this->assertSame(1 + 150 + 4725 + 54600, $result['at_least'][12]['favorable']);
        $this->assertSame(1 + 150 + 4725 + 54600 + 286650, $result['at_least'][11]['favorable']);
        $this->assertEqualsWithDelta(0.10589, $result['at_least'][11]['probability'], 0.00001);
        $this->assertEqualsWithDelta(346126 / 3268760, $result['expected_prized_bets'], 1e-12);
        $this->assertNull($result['average_overlap']);
    }

    public function test_real_closing_matches_independent_calculation(): void
    {
        // Fechamento real de 10 apostas em base de 21 dezenas; valores conferidos por enumeração em Python/numpy
        $bets = [
            [2, 6, 7, 8, 9, 10, 11, 13, 15, 18, 19, 22, 23, 24, 25],
            [1, 6, 7, 8, 10, 11, 12, 13, 15, 17, 19, 21, 22, 23, 24],
            [2, 6, 7, 8, 9, 10, 13, 15, 17, 18, 19, 21, 22, 23, 24],
            [2, 3, 5, 6, 9, 11, 12, 13, 15, 17, 18, 21, 22, 23, 24],
            [2, 3, 6, 7, 8, 10, 11, 13, 15, 17, 18, 21, 22, 23, 25],
            [1, 2, 3, 6, 8, 9, 10, 15, 17, 19, 21, 22, 23, 24, 25],
            [1, 2, 6, 7, 9, 10, 11, 12, 13, 19, 21, 22, 23, 24, 25],
            [2, 3, 5, 7, 9, 10, 11, 12, 13, 15, 18, 21, 22, 23, 24],
            [1, 2, 6, 7, 8, 9, 11, 12, 13, 15, 17, 21, 22, 23, 24],
            [2, 6, 7, 8, 9, 10, 11, 12, 13, 18, 19, 21, 22, 23, 25],
        ];

        $result = $this->service()->calculate($bets);

        $this->assertSame(1526633, $result['at_least'][11]['favorable']);
        $this->assertSame(409800, $result['at_least'][12]['favorable']);
        $this->assertSame(43641, $result['at_least'][13]['favorable']);
        $this->assertSame(1490, $result['at_least'][14]['favorable']);
        $this->assertSame(10, $result['at_least'][15]['favorable']);
        $this->assertEqualsWithDelta(3461260 / 3268760, $result['expected_prized_bets'], 1e-12);

        // Com dezenas em comum, a chance de pelo menos um prêmio fica abaixo da referência sem sobreposição
        $this->assertLessThan($result['at_least'][11]['independent_probability'], $result['at_least'][11]['probability']);
    }

    public function test_multiple_bets_count_every_drawn_number_inside_the_bet(): void
    {
        // Aposta de 16 dezenas acerta 15 em C(16,15) = 16 sorteios
        $result = $this->service()->calculate([range(1, 16)]);

        $this->assertSame(16, $result['at_least'][15]['favorable']);
        $this->assertNull($result['at_least'][15]['independent_probability']);
    }

    public function test_result_is_cached_and_independent_of_bet_order(): void
    {
        Cache::flush();
        $bets = [range(1, 15), range(11, 25)];

        $this->assertNull($this->service()->cached($bets));

        $first = $this->service()->calculate($bets);

        $this->assertSame($first, $this->service()->cached(array_reverse($bets)));
        $this->assertSame(5.0, $first['average_overlap']);
    }

    public function test_rejects_invalid_bets(): void
    {
        foreach ([[], [range(1, 14)], [range(1, 21)], [array_fill(0, 15, 1)], array_fill(0, 151, range(1, 15))] as $bets) {
            try {
                $this->service()->calculate($bets);
                $this->fail('Deveria rejeitar as apostas informadas.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
