<?php

namespace App\Services;

use App\Models\LotofacilSetting;
use Illuminate\Support\Facades\Cache;

class LotofacilSettingService
{
    public const CACHE_KEY = 'lotofacil_bet_prices';

    /**
     * Retorna o mapa de preços (15 a 20 dezenas).
     *
     * @return array<int, float>
     */
    public function getPrices(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDays(7), function (): array {
            return LotofacilSetting::getPricesTable();
        });
    }

    /**
     * Retorna o preço unitário para a quantidade de dezenas informada.
     */
    public function getPriceFor(int $numbersCount): float
    {
        $prices = $this->getPrices();

        return $prices[$numbersCount] ?? ($prices[15] ?? 3.50);
    }

    /**
     * Salva ou atualiza os preços das apostas.
     *
     * @param  array<int|string, float|int|string>  $prices
     */
    public function savePrices(array $prices): void
    {
        foreach ($prices as $count => $price) {
            $count = (int) $count;
            if ($count >= 15 && $count <= 20) {
                LotofacilSetting::updateOrCreate(
                    ['numbers_count' => $count],
                    ['price' => (float) $price]
                );
            }
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Restaura os preços padrões da Caixa Econômica Federal.
     */
    public function resetToDefaults(): void
    {
        $defaults = LotofacilSetting::defaultPrices();

        foreach ($defaults as $count => $price) {
            LotofacilSetting::updateOrCreate(
                ['numbers_count' => $count],
                ['price' => $price]
            );
        }

        Cache::forget(self::CACHE_KEY);
    }
}
