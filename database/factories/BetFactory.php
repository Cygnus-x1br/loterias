<?php

namespace Database\Factories;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bet>
 */
class BetFactory extends Factory
{
    protected $model = Bet::class;

    public function definition(): array
    {
        $numbers = fake()->randomElements(range(1, 25), 15);
        sort($numbers);

        return [
            'user_id' => User::factory(),
            'closing_id' => Closing::factory(),
            'numbers' => $numbers,
        ];
    }
}
