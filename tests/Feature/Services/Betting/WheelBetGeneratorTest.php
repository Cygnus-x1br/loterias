<?php

namespace Tests\Feature\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\Generators\WheelBetGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class WheelBetGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_wheel_bets_with_valid_parameters(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento Roda',
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 20), // Grupo-base maior
            'bet_size' => 15,
            'planned_bets' => 10, // Pedir 10 apostas
            'parameters' => [
                'fixed_numbers' => [1, 2, 3, 4, 5], // 5 dezenas fixas
                'variable_numbers' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20], // 15 dezenas variáveis
                'wheel_size' => 10, // 10 dezenas variáveis por aposta (5 fixas + 10 variáveis = 15)
            ],
        ]);

        $createdBets = app(ClosingGenerator::class)->generate($closing);

        $this->assertSame(10, $createdBets); // Espera 10 apostas geradas

        $this->assertDatabaseHas('closings', [
            'id' => $closing->id,
            'status' => 'completed',
        ]);

        $bets = Bet::query()->where('closing_id', $closing->id)->get();
        $this->assertCount(10, $bets);

        foreach ($bets as $bet) {
            $this->assertCount(15, $bet->numbers);
            $this->assertCount(15, array_unique($bet->numbers));
            foreach ($bet->numbers as $number) {
                $this->assertGreaterThanOrEqual(1, $number);
                $this->assertLessThanOrEqual(25, $number);
            }

            // Verifica se as dezenas fixas estão presentes
            foreach ($closing->parameters['fixed_numbers'] as $fixedNumber) {
                $this->assertContains($fixedNumber, $bet->numbers);
            }

            // Verifica se as dezenas variáveis são do conjunto correto
            $variablePart = array_diff($bet->numbers, $closing->parameters['fixed_numbers']);
            $this->assertCount(10, $variablePart); // Deve ter 10 dezenas variáveis
            foreach ($variablePart as $varNumber) {
                $this->assertContains($varNumber, $closing->parameters['variable_numbers']);
            }
        }
    }

    public function test_rejects_when_planned_bets_is_missing(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => null, // Faltando
            'parameters' => [
                'fixed_numbers' => [1, 2, 3, 4, 5],
                'variable_numbers' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
                'wheel_size' => 10,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A quantidade planejada de apostas é obrigatória para o sistema de roda.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_fixed_number_not_in_base_numbers(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fixed_numbers' => [1, 2, 20], // 20 não está no grupo-base
                'variable_numbers' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15],
                'wheel_size' => 12,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A dezena fixa 20 não está no grupo-base.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_variable_number_not_in_base_numbers(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 15),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fixed_numbers' => [1, 2, 3],
                'variable_numbers' => [4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 20], // 20 não está no grupo-base
                'wheel_size' => 12,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A dezena variável 20 não está no grupo-base.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_variable_number_also_in_fixed_numbers(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fixed_numbers' => [1, 2, 3],
                'variable_numbers' => [3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15], // 3 está nas fixas
                'wheel_size' => 12,
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A dezena variável 3 também está nas dezenas fixas.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_invalid_wheel_size(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fixed_numbers' => [1, 2, 3, 4, 5],
                'variable_numbers' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
                'wheel_size' => 25, // Tamanho da roda inválido
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O tamanho da roda (wheel_size) deve ser um número válido entre 1 e o total de dezenas variáveis.');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_rejects_incorrect_sum_of_fixed_and_wheel_size(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 1,
            'parameters' => [
                'fixed_numbers' => [1, 2, 3, 4, 5], // 5 fixas
                'variable_numbers' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20],
                'wheel_size' => 9, // 9 variáveis. 5 + 9 = 14, não 15
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A soma das dezenas fixas (5) e o tamanho da roda (9) deve ser igual ao tamanho da aposta (15).');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
        }
    }

    public function test_fails_if_cannot_generate_enough_unique_wheel_bets(): void
    {
        $user = User::factory()->create();

        $closing = Closing::factory()->create([
            'user_id' => $user->id,
            'method' => 'wheel',
            'status' => 'draft',
            'base_numbers' => range(1, 15), // Grupo-base com 15 dezenas
            'bet_size' => 15,
            'planned_bets' => 2, // Pedindo 2 apostas
            'parameters' => [
                'fixed_numbers' => [1, 2, 3, 4, 5], // 5 fixas
                'variable_numbers' => [6, 7, 8, 9, 10, 11, 12, 13, 14, 15], // 10 variáveis
                'wheel_size' => 10, // 5 fixas + 10 variáveis = 15. Só há 1 combinação possível de 10 dezenas de 10.
            ],
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Não foi possível gerar \d+ apostas únicas com os parâmetros fornecidos\. Foram geradas \d+\./');

        try {
            app(ClosingGenerator::class)->generate($closing);
        } finally {
            $this->assertDatabaseHas('closings', [
                'id' => $closing->id,
                'status' => 'failed',
            ]);
            $this->assertDatabaseCount('bets', 0); // <--- ALTERADO AQUI
        }
    }

}
