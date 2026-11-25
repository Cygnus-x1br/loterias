<?php

namespace Database\Factories;

use App\Models\NumberIndication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NumberIndication>
 */
class NumberIndicationFactory extends Factory
{
    protected $model = NumberIndication::class;

    public function definition(): array
    {
        $order = range(1, 25);
        shuffle($order);
        $scores = array_combine($order, range(25, 1));
        ksort($scores);

        $ranked = array_keys(collect($scores)->sortDesc()->all());
        $based = fake()->unique()->numberBetween(1, 5000);

        return [
            'target_contest_number' => $based + 1,
            'based_on_contest_number' => $based,
            'scores' => $scores,
            'most_indicated' => array_slice($ranked, 0, 10),
            'least_indicated' => array_slice($ranked, -5),
        ];
    }

    /**
     * Indicação já conferida com o resultado do concurso alvo.
     */
    public function evaluated(int $mostHits = 6, int $leastHits = 3): static
    {
        return $this->state(fn (): array => [
            'most_hits' => $mostHits,
            'least_hits' => $leastHits,
            'evaluated_at' => now(),
        ]);
    }
}
