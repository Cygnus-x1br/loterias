<?php

namespace Database\Factories;

use App\Models\Closing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Closing>
 */
class ClosingFactory extends Factory
{
    protected $model = Closing::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'method' => 'integral',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
            'guarantee' => null,
            'budget' => null,
            'notes' => null,
        ];
    }
}
