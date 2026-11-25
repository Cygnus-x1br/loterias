<?php

namespace App\Services\Betting;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Estado de uma geração em segundo plano (progresso, cancelamento e erro).
 *
 * Fica no cache e não no banco porque a geração roda dentro de uma transação:
 * o que ela grava só fica visível para as outras requisições ao terminar.
 */
class ClosingGenerationProgress
{
    private const TTL_SECONDS = 7200;

    /**
     * Quantas apostas entre uma atualização de progresso (e checagem de cancelamento) e outra.
     */
    public const REPORT_EVERY = 25;

    private static function key(int $closingId, string $name): string
    {
        return "closing_generation:{$closingId}:{$name}";
    }

    /**
     * Abre o acompanhamento de uma nova geração e devolve o token que identifica o job.
     */
    public static function begin(int $closingId, int $expectedBets): string
    {
        self::clear($closingId);

        $token = (string) Str::uuid();
        Cache::put(self::key($closingId, 'token'), $token, self::TTL_SECONDS);
        Cache::put(self::key($closingId, 'expected'), $expectedBets, self::TTL_SECONDS);
        Cache::put(self::key($closingId, 'created'), 0, self::TTL_SECONDS);

        return $token;
    }

    public static function isTracked(int $closingId): bool
    {
        return self::token($closingId) !== null;
    }

    public static function token(int $closingId): ?string
    {
        return Cache::get(self::key($closingId, 'token'));
    }

    public static function markRunning(int $closingId): void
    {
        Cache::put(self::key($closingId, 'running'), true, self::TTL_SECONDS);
    }

    public static function isRunning(int $closingId): bool
    {
        return (bool) Cache::get(self::key($closingId, 'running'), false);
    }

    public static function report(int $closingId, int $created): void
    {
        Cache::put(self::key($closingId, 'created'), $created, self::TTL_SECONDS);
    }

    public static function requestCancel(int $closingId): void
    {
        Cache::put(self::key($closingId, 'cancel'), true, self::TTL_SECONDS);
    }

    public static function cancelRequested(int $closingId): bool
    {
        return (bool) Cache::get(self::key($closingId, 'cancel'), false);
    }

    public static function fail(int $closingId, string $message): void
    {
        Cache::put(self::key($closingId, 'error'), $message, self::TTL_SECONDS);
    }

    public static function error(int $closingId): ?string
    {
        return Cache::get(self::key($closingId, 'error'));
    }

    /**
     * @return array{created: int, expected: int, percent: int, running: bool, cancelling: bool}
     */
    public static function snapshot(int $closingId): array
    {
        $created = (int) Cache::get(self::key($closingId, 'created'), 0);
        $expected = (int) Cache::get(self::key($closingId, 'expected'), 0);

        return [
            'created' => $created,
            'expected' => $expected,
            'percent' => $expected > 0 ? min(100, (int) floor($created / $expected * 100)) : 0,
            'running' => self::isRunning($closingId),
            'cancelling' => self::cancelRequested($closingId),
        ];
    }

    /**
     * Encerra o acompanhamento, mantendo a mensagem de erro (se houver) para a tela.
     */
    public static function finish(int $closingId): void
    {
        foreach (['token', 'expected', 'created', 'running', 'cancel'] as $name) {
            Cache::forget(self::key($closingId, $name));
        }
    }

    public static function clear(int $closingId): void
    {
        self::finish($closingId);
        Cache::forget(self::key($closingId, 'error'));
    }
}
