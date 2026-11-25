<?php

namespace App\Services;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Cache das estatísticas derivadas da série histórica.
 *
 * Todas as chaves recebem o número de uma "versão". Quando a série histórica muda,
 * flush() incrementa a versão e todas as estatísticas passam a ser recalculadas,
 * inclusive chaves dinâmicas (por janela ou por concurso) que não precisam ser listadas.
 * As entradas antigas expiram sozinhas pelo TTL.
 */
class StatisticsCache
{
    public const VERSION_KEY = 'lotofacil_statistics_cache_version';

    /**
     * @template TCacheValue
     *
     * @param  Closure(): TCacheValue  $callback
     * @return TCacheValue
     */
    public function remember(string $key, DateTimeInterface|DateInterval|int $ttl, Closure $callback): mixed
    {
        return Cache::remember($this->key($key), $ttl, $callback);
    }

    public function forget(string $key): void
    {
        Cache::forget($this->key($key));
    }

    /**
     * Invalida todas as estatísticas de uma só vez.
     */
    public function flush(): void
    {
        Cache::memo()->forever(self::VERSION_KEY, $this->version() + 1);
    }

    public function key(string $key): string
    {
        return $key.':v'.$this->version();
    }

    protected function version(): int
    {
        return (int) Cache::memo()->get(self::VERSION_KEY, 1);
    }
}
