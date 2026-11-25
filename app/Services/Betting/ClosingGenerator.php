<?php

namespace App\Services\Betting;

use App\Jobs\GenerateClosingBetsJob;
use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Services\Betting\Generators\BalancedBetGenerator;
use App\Services\Betting\Generators\BetGeneratorInterface;
use App\Services\Betting\Generators\IntegralBetGenerator;
use App\Services\Betting\Generators\RandomBetGenerator;
use App\Services\Betting\Generators\ReducedBetGenerator;
use App\Services\Betting\Generators\WheelBetGenerator;
use App\Services\Betting\Math\Combinatorics;
use App\Services\LotofacilStatisticsService;
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
     * A partir desta quantidade de apostas a geração roda em segundo plano (fila).
     */
    public const QUEUE_THRESHOLD = 1000;

    /**
     * Quantidade de apostas que a geração deve produzir.
     */
    public function expectedBets(Closing $closing): int
    {
        if ($closing->method === 'integral') {
            $base = count($closing->base_numbers ?? []);
            $size = (int) $closing->bet_size;

            return $base >= $size && $size > 0 ? Combinatorics::countCombinations($base, $size) : 0;
        }

        return (int) ($closing->planned_bets ?? 0);
    }

    public function shouldQueue(Closing $closing): bool
    {
        return $this->expectedBets($closing) >= self::QUEUE_THRESHOLD;
    }

    /**
     * Valida e coloca a geração na fila, devolvendo o fechamento como "processing".
     *
     * As validações rodam antes para que erros de parâmetro apareçam na hora, não no job.
     *
     * @throws InvalidArgumentException
     * @throws LogicException
     */
    public function dispatchGeneration(Closing $closing): void
    {
        $this->validateClosing($closing);
        $this->resolveGenerator($closing->method)->validate($closing);

        $token = ClosingGenerationProgress::begin($closing->id, $this->expectedBets($closing));

        $closing->update(['status' => 'processing', 'generation_warnings' => null]);

        GenerateClosingBetsJob::dispatch($closing, $token);
    }

    /**
     * Cancela uma geração em andamento. Se o job ainda não começou, o fechamento volta a rascunho na hora.
     */
    public function cancelGeneration(Closing $closing): void
    {
        if ($closing->status !== 'processing') {
            return;
        }

        if (ClosingGenerationProgress::isRunning($closing->id)) {
            ClosingGenerationProgress::requestCancel($closing->id);

            return;
        }

        // Sem job em execução (fila parada ou ainda não iniciado): o token apagado torna o job obsoleto
        ClosingGenerationProgress::finish($closing->id);
        $closing->update(['status' => 'draft']);
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
                    'generation_warnings' => null,
                ]);

                $createdBets = 0;
                $createdBetsByNumbers = [];
                $historicalHashes = app(LotofacilStatisticsService::class)->getHistoricalDrawHashes();
                $combinations = $generator->generate($closing);

                foreach ($combinations as $combination) {
                    if (
                        $closing->planned_bets !== null
                        && $createdBets >= $closing->planned_bets
                    ) {
                        break;
                    }

                    // Se a aposta tem 15 dezenas (tamanho padrão de concurso), verifica se já foi sorteada
                    if (count($combination) === 15) {
                        $combinationHash = HistoricalResult::generateDrawnNumbersHash($combination);
                        if (isset($historicalHashes[$combinationHash])) {
                            // Ignora combinação que já saiu em concursos anteriores
                            continue;
                        }
                    }

                    $bet = Bet::create([
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
                        'status' => $closing->status === 'placed' ? 'placed' : 'active',
                        'contest_number' => $closing->contest_number,
                        'draw_date' => $closing->draw_date,
                        'notes' => null,
                    ]);

                    $createdBetsByNumbers[$this->numbersKey($combination)] = $bet;
                    $createdBets++;

                    // Progresso e cancelamento só valem para gerações acompanhadas (em segundo plano)
                    if ($createdBets % ClosingGenerationProgress::REPORT_EVERY === 0 && ClosingGenerationProgress::isTracked($closing->id)) {
                        ClosingGenerationProgress::report($closing->id, $createdBets);

                        if (ClosingGenerationProgress::cancelRequested($closing->id)) {
                            throw new GenerationCancelledException;
                        }
                    }
                }

                if ($createdBets === 0) {
                    throw new LogicException(
                        'Nenhuma aposta foi gerada para este fechamento.'
                    );
                }

                // Relatório devolvido pelo gerador ao terminar (ex: apostas aceitas com filtros relaxados)
                $report = $combinations->valid() ? null : $combinations->getReturn();

                foreach ($report['bet_notes'] ?? [] as $numbersKey => $note) {
                    ($createdBetsByNumbers[$numbersKey] ?? null)?->update(['notes' => $note]);
                }

                // Atualiza o status para 'completed' se tudo ocorreu bem
                $closing->update([
                    'status' => 'completed',
                    'generation_warnings' => ($report['warnings'] ?? []) ?: null,
                ]);

                return $createdBets;
            });
        } catch (GenerationCancelledException $exception) {
            // A transação foi revertida: nenhuma aposta ficou salva
            $closing->refresh();
            $closing->update(['status' => 'draft']);

            throw $exception;
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
     * Reseta as apostas do fechamento e o retorna para o estado de rascunho (draft).
     */
    public function resetToDraft(Closing $closing): void
    {
        if (in_array($closing->status, ['placed', 'checked'], true)) {
            throw new LogicException('Fechamentos marcados como Apostados ou Conferidos não podem ser reiniciados.');
        }

        DB::transaction(function () use ($closing): void {
            $closing->bets()->delete();
            $closing->update([
                'status' => 'draft',
                'generation_warnings' => null,
                'contest_number' => null,
                'draw_date' => null,
            ]);
        });
    }

    /**
     * Renumera as apostas existentes de um fechamento em ordem crescente sequencial (Aposta 1, Aposta 2...).
     */
    public function renumberBets(Closing $closing): void
    {
        $bets = $closing->bets()->orderBy('id')->get();
        $index = 1;
        foreach ($bets as $bet) {
            $bet->update([
                'name' => sprintf('%s - Aposta %d', $closing->name, $index),
            ]);
            $index++;
        }
    }

    /**
     * Repete um fechamento gravado para outro concurso.
     *
     * Cria um novo fechamento com a mesma configuração (método, grupo-base, parâmetros, orçamento
     * e observações) para o concurso informado, sempre em nome do dono do original e sem
     * compartilhamento. Com $copyBets, as apostas são copiadas com as mesmas dezenas e o novo
     * fechamento nasce concluído; sem ele, nasce como rascunho para gerar novas apostas.
     * O novo fechamento nunca herda concurso apostado, data do sorteio nem conferência.
     *
     * @throws InvalidArgumentException
     */
    public function repeatForContest(Closing $closing, int $targetContest, bool $copyBets = true): Closing
    {
        if ($targetContest < 1) {
            throw new InvalidArgumentException('Informe um número de concurso válido.');
        }

        $sourceContest = $closing->contest_number ?? $closing->target_contest_number;
        if ($sourceContest !== null && $targetContest === (int) $sourceContest) {
            throw new InvalidArgumentException('Informe um concurso diferente do fechamento original.');
        }

        return DB::transaction(function () use ($closing, $targetContest, $copyBets): Closing {
            $bets = $copyBets ? $closing->bets()->orderBy('id')->get() : collect();
            $name = $this->nameForContest($closing->name, $targetContest);

            $repeated = Closing::create([
                'user_id' => $closing->user_id,
                'name' => $name,
                'method' => $closing->method,
                'base_numbers' => $closing->base_numbers,
                'bet_size' => $closing->bet_size,
                'planned_bets' => $closing->planned_bets,
                'guarantee' => $closing->guarantee,
                'budget' => $closing->budget,
                'parameters' => $closing->parameters,
                'generation_warnings' => $bets->isNotEmpty() ? $closing->generation_warnings : null,
                'status' => $bets->isNotEmpty() ? 'completed' : 'draft',
                'target_contest_number' => $targetContest,
                'notes' => $closing->notes,
            ]);

            foreach ($bets as $index => $bet) {
                Bet::create([
                    'user_id' => $closing->user_id,
                    'closing_id' => $repeated->id,
                    'name' => sprintf('%s - Aposta %d', $name, $index + 1),
                    'numbers' => $bet->numbers,
                    'source' => $bet->source,
                    'method' => $bet->method,
                    'status' => 'active',
                    'notes' => $bet->notes,
                ]);
            }

            return $repeated;
        });
    }

    /**
     * Nome do fechamento repetido: troca o sufixo " - Concurso N" em vez de acumulá-lo.
     */
    protected function nameForContest(string $name, int $contest): string
    {
        $base = preg_replace('/\s+-\s+Concurso\s+\d+$/u', '', $name) ?? $name;

        return mb_substr($base, 0, 255 - mb_strlen(" - Concurso {$contest}")).' - Concurso '.$contest;
    }

    /**
     * Completa as apostas faltantes até atingir a quantidade planejada ($closing->planned_bets).
     *
     * Prioriza dezenas do grupo-base que ainda não estejam presentes em nenhuma aposta.
     * Garante que não sejam geradas duplicatas nem combinações com 15 dezenas já sorteadas na história.
     *
     * @throws LogicException|InvalidArgumentException
     */
    public function completeBets(Closing $closing): int
    {
        if (in_array($closing->status, ['placed', 'checked'], true)) {
            throw new LogicException('Fechamentos marcados como Apostados ou Conferidos não podem receber novas apostas.');
        }

        $plannedBets = (int) $closing->planned_bets;
        if ($plannedBets < 1) {
            throw new InvalidArgumentException('Este fechamento não possui quantidade planejada de apostas definida.');
        }

        $existingBets = $closing->bets()->orderBy('id')->get();
        $currentCount = $existingBets->count();

        if ($currentCount >= $plannedBets) {
            return 0;
        }

        $neededCount = $plannedBets - $currentCount;
        $baseNumbers = array_values(array_map('intval', $closing->base_numbers ?? []));
        sort($baseNumbers);
        $betSize = (int) $closing->bet_size;

        if (count($baseNumbers) < $betSize) {
            throw new InvalidArgumentException('O grupo-base possui menos dezenas que o tamanho da aposta.');
        }

        // Mapeia combinações já existentes (normalizadas)
        $existingHashes = [];
        $usedNumbers = [];

        foreach ($existingBets as $bet) {
            $numbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            $numbers = array_map('intval', $numbers);
            sort($numbers);
            $existingHashes[implode('-', $numbers)] = true;
            foreach ($numbers as $n) {
                $usedNumbers[$n] = true;
            }
        }

        // Dezenas do grupo-base que ainda não foram contempladas em nenhuma aposta
        $uncoveredNumbers = array_values(array_diff($baseNumbers, array_keys($usedNumbers)));

        $historicalHashes = app(LotofacilStatisticsService::class)->getHistoricalDrawHashes();
        $createdBets = 0;

        // A complementação sorteia dentro do grupo-base sem aplicar os filtros do método equilibrado
        $complementNote = $closing->method === 'balanced'
            ? 'Aposta complementar: gerada sem os filtros do método equilibrado.'
            : null;

        DB::transaction(function () use (
            $closing,
            $baseNumbers,
            $betSize,
            $neededCount,
            $uncoveredNumbers,
            $historicalHashes,
            &$existingHashes,
            &$createdBets,
            $currentCount,
            $complementNote
        ): void {
            $attempts = 0;
            $maxAttempts = $neededCount * 1000 + 2000;

            while ($createdBets < $neededCount && $attempts < $maxAttempts) {
                $attempts++;

                // Monta a combinação priorizando dezenas ainda descobertas
                $combination = [];

                if (! empty($uncoveredNumbers)) {
                    // Pega até ($betSize - 1) dezenas descobertas para garantir que sobre espaço
                    shuffle($uncoveredNumbers);
                    $takeUncovered = min(count($uncoveredNumbers), $betSize);
                    $combination = array_slice($uncoveredNumbers, 0, $takeUncovered);
                }

                // Preenche o restante com dezenas do grupo-base
                $remainingBase = array_values(array_diff($baseNumbers, $combination));
                shuffle($remainingBase);
                $neededRemaining = $betSize - count($combination);
                $combination = array_merge($combination, array_slice($remainingBase, 0, $neededRemaining));

                sort($combination);

                if (count($combination) !== $betSize) {
                    continue;
                }

                $hash = implode('-', $combination);
                if (isset($existingHashes[$hash])) {
                    continue;
                }

                // Se a aposta tem 15 dezenas, verifica se já saiu na história
                if (count($combination) === 15) {
                    $drawnHash = HistoricalResult::generateDrawnNumbersHash($combination);
                    if (isset($historicalHashes[$drawnHash])) {
                        continue;
                    }
                }

                Bet::create([
                    'user_id' => $closing->user_id,
                    'closing_id' => $closing->id,
                    'name' => sprintf(
                        '%s - Aposta %d',
                        $closing->name,
                        $currentCount + $createdBets + 1
                    ),
                    'numbers' => $combination,
                    'source' => 'closing',
                    'method' => $closing->method,
                    'status' => $closing->status === 'placed' ? 'placed' : 'active',
                    'contest_number' => $closing->contest_number,
                    'draw_date' => $closing->draw_date,
                    'notes' => $complementNote,
                ]);

                $existingHashes[$hash] = true;
                $uncoveredNumbers = array_values(array_diff($uncoveredNumbers, $combination));
                $createdBets++;
            }

            if ($createdBets === 0 && $neededCount > 0) {
                throw new LogicException('Não foi possível gerar novas apostas únicas para completar o fechamento.');
            }
        });

        $this->renumberBets($closing);

        if ($complementNote !== null && $createdBets > 0) {
            $closing->update([
                'generation_warnings' => [
                    ...($closing->generation_warnings ?? []),
                    sprintf('%d aposta(s) complementar(es) foram geradas sem os filtros do método equilibrado.', $createdBets),
                ],
            ]);
        }

        return $createdBets;
    }

    /**
     * Chave das dezenas ordenadas (ex: "1-2-3-...") usada para casar apostas com o relatório do gerador.
     *
     * @param  array<int, int|string>  $numbers
     */
    protected function numbersKey(array $numbers): string
    {
        $numbers = array_map('intval', $numbers);
        sort($numbers);

        return implode('-', $numbers);
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
