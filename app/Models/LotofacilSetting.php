<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LotofacilSetting extends Model
{
    protected $fillable = [
        'numbers_count',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'numbers_count' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    /**
     * Tabela padrão oficial de preços da Lotofácil (Preços da Caixa Econômica Federal).
     *
     * @return array<int, float>
     */
    public static function defaultPrices(): array
    {
        return [
            15 => 3.50,
            16 => 56.00,
            17 => 476.00,
            18 => 2856.00,
            19 => 13566.00,
            20 => 54264.00,
        ];
    }

    /**
     * Retorna a tabela completa de preços (do banco com fallback para o padrão).
     *
     * @return array<int, float>
     */
    public static function getPricesTable(): array
    {
        $defaults = static::defaultPrices();

        try {
            $prices = static::query()->pluck('price', 'numbers_count')->toArray();
        } catch (\Throwable) {
            $prices = [];
        }

        $result = [];
        for ($count = 15; $count <= 20; $count++) {
            $result[$count] = isset($prices[$count]) ? (float) $prices[$count] : $defaults[$count];
        }

        return $result;
    }

    /**
     * Retorna o preço de uma aposta para uma quantidade específica de dezenas.
     */
    public static function getPriceFor(int $numbersCount): float
    {
        $table = static::getPricesTable();

        return $table[$numbersCount] ?? ($table[15] ?? 3.50);
    }
}
