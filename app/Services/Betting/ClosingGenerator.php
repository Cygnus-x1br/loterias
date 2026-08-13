<?php

namespace App\Services\Betting;

use App\Models\Bet;
use App\Models\Closing;
use App\Services\Betting\Generators\BalancedBetGenerator;
use App\Services\Betting\Generators\BetGeneratorInterface;
use App\Services\Betting\Generators\IntegralBetGenerator;
use App\Services\Betting\Generators\RandomBetGenerator;
use App\Services\Betting\Generators\ReducedBetGenerator;
use App\Services\Betting\Generators\WheelBetGenerator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use Throwable;

class ClosingGenerator
{
    /**
     * Geradores disponíveis por método.
     *
     * @var array<string, class-string<BetGeneratorInterface>>
     */
    protected static array $generators = [
        'integral' => IntegralBetGenerator::class,
        'random' => RandomBetGenerator::class,
        'balanced' => BalancedBetGenerator::class,
        'wheel' => WheelBetGenerator::class,
        'reduced' => ReducedBetGenerator::class,
    ];

    /**
     * Retorna os métodos de fechamento que já possuem
     * geração implementada nesta versão da plataforma.
     *
     * @return array<int, string>
     */
    public static function implementedMethods(): array
    {
        return array_keys(self::$generators);
    }

    /**
     * Resolve e retorna a instância do gerador para o método especificado.
     *
     * @throws InvalidArgumentException
     */
    protected function resolveGenerator(string $method): BetGeneratorInterface
    {
        if (! isset(self::$generators[$method])) {
            throw new InvalidArgumentException("Gerador para o método '{$method}' não encontrado.");
        }

        return app(self::$generators[$method]);
    }

    /**
     * Executa a geração de apostas de um fechamento.
     *
     * Retorna a quantidade de apostas criadas.
     *
     * @throws Throwable
     */
    public function generate(Closing $closing): int
    {
        try {
            // Validações gerais do fechamento
            $this->validateClosing($closing);

            // Resolve o gerador específico para o método
            $generator = $this->resolveGenerator($closing->method);

            // Validações específicas do gerador (chamado antes da transação)
            $generator->validate($closing);

            return DB::transaction(function () use ($closing, $generator): int {
                // Atualiza o status para 'processing' dentro da transação
                $closing->update([
                    'status' => 'processing',
                ]);

                $createdBets = 0;
                // A responsabilidade de gerar apostas únicas é do BetGenerator específico.
                // Não é necessário um $uniqueBets aqui se o gerador já garante isso.

                foreach ($generator->generate($closing) as $combination) {
                    if (
                        $closing->planned_bets !== null
                        && $createdBets >= $closing->planned_bets
                    ) {
                        break;
                    }

                    // A combinação já deve vir ordenada e única do gerador.
                    // Se houver necessidade de garantir unicidade global aqui,
                    // um mecanismo mais robusto (ex: hash da combinação) seria necessário,
                    // mas por enquanto confiamos no gerador.

                    Bet::create([
                        'user_id' => $closing->user_id,
                        'closing_id' => $closing->id,
                        'name' => sprintf(
                            '%s - Aposta %d',
                            $closing->name,
                            $createdBets + 1
                        ),
                        'numbers' => $combination,
                        'source' => 'closing',
                        'method' => $closing->method,
                        'status' => 'active',
                        'notes' => null,
                    ]);

                    $createdBets++;
                }

                if ($createdBets === 0) {
                    throw new LogicException(
                        'Nenhuma aposta foi gerada para este fechamento.'
                    );
                }

                // Atualiza o status para 'completed' se tudo ocorreu bem
                $closing->update([
                    'status' => 'completed',
                ]);

                return $createdBets;
            });
        } catch (Throwable $exception) {
            // Se uma exceção ocorrer, a transação já foi revertida.
            // Precisamos recarregar o modelo para garantir que ele reflita o estado atual do DB
            // antes de tentar atualizá-lo novamente.
            $closing->refresh(); // <--- LINHA CRÍTICA ADICIONADA AQUI
            $closing->update([
                'status' => 'failed',
            ]);

            throw $exception;
        }
    }

    /**
     * Valida os parâmetros gerais do fechamento.
     */
    protected function validateClosing(Closing $closing): void
    {
        $baseNumbers = $closing->base_numbers ?? [];
        $betSize = (int) $closing->bet_size;

        if (! is_array($baseNumbers)) {
            throw new InvalidArgumentException(
                'O grupo-base do fechamento deve ser uma lista de dezenas.'
            );
        }

        if (count($baseNumbers) < 15 || count($baseNumbers) > 25) {
            throw new InvalidArgumentException(
                'O grupo-base deve conter entre 15 e 25 dezenas.'
            );
        }

        $normalizedNumbers = array_map(
            static fn ($number): int => (int) $number,
            $baseNumbers
        );

        if (count($normalizedNumbers) !== count(array_unique($normalizedNumbers))) {
            throw new InvalidArgumentException(
                'O grupo-base não pode conter dezenas repetidas.'
            );
        }

        foreach ($normalizedNumbers as $number) {
            if ($number < 1 || $number > 25) {
                throw new InvalidArgumentException(
                    'As dezenas do grupo-base devem estar entre 1 e 25.'
                );
            }
        }

        if ($betSize < 15 || $betSize > 25) {
            throw new InvalidArgumentException(
                'O tamanho da aposta deve estar entre 15 e 25 dezenas.'
            );
        }

        // Esta validação é geral e deve permanecer aqui.
        if ($betSize > count($normalizedNumbers)) {
            throw new InvalidArgumentException(
                'O tamanho da aposta não pode ser maior que o grupo-base.'
            );
        }

        if (
            $closing->planned_bets !== null
            && (int) $closing->planned_bets < 1
        ) {
            throw new InvalidArgumentException(
                'A quantidade planejada de apostas deve ser maior que zero.'
            );
        }

        // Garante que as dezenas do grupo-base estejam sempre ordenadas
        sort($normalizedNumbers);
        $closing->base_numbers = $normalizedNumbers;
    }
}
