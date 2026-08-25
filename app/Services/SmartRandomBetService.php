<?php

namespace App\Services;

use App\Models\HistoricalResult;
use Illuminate\Support\Facades\Cache;

class SmartRandomBetService
{
    private const FRAME_NUMBERS = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

    /**
     * Gera uma combinação de 15 dezenas aleatórias que respeitam os padrões estatísticos mais comuns (Rejection Sampling).
     *
     * @return array<int>
     */
    public function generateSmartBet(): array
    {
        $historicalHashes = $this->getHistoricalHashes();
        
        $attempts = 0;
        $maxAttempts = 5000; // Proteção contra loop infinito

        while ($attempts < $maxAttempts) {
            $attempts++;
            
            $numbers = range(1, 25);
            shuffle($numbers);
            $bet = array_slice($numbers, 0, 15);
            sort($bet);

            if ($this->isValidPattern($bet) && !$this->isAlreadyDrawn($bet, $historicalHashes)) {
                return $bet;
            }
        }

        // Fallback se por algum motivo bater no limite de tentativas (muito improvável)
        $numbers = range(1, 25);
        shuffle($numbers);
        $bet = array_slice($numbers, 0, 15);
        sort($bet);
        return $bet;
    }

    /**
     * Gera uma combinação de N dezenas (16 a 25) equilibrada para formar um grupo-base de fechamento.
     * Mantém as proporções da Lotofácil adaptadas para o tamanho escolhido.
     *
     * @param int $size
     * @return array<int>
     */
    public function generateSmartBase(int $size = 20): array
    {
        if ($size < 16 || $size > 25) {
            $size = 20;
        }

        $attempts = 0;
        $maxAttempts = 5000;

        // Limites proporcionais ao tamanho escolhido (baseados nos 25 totais: 13 ímpares, 12 pares, 16 moldura, 9 miolo)
        // Por exemplo, para 20 dezenas, esperamos que corte um pouco de cada.
        $ratio = $size / 25;
        
        $minEvens = (int) floor(12 * $ratio) - 1;
        $maxEvens = (int) ceil(12 * $ratio) + 1;
        
        $minFrame = (int) floor(16 * $ratio) - 1;
        $maxFrame = (int) ceil(16 * $ratio) + 1;

        while ($attempts < $maxAttempts) {
            $attempts++;
            
            $numbers = range(1, 25);
            shuffle($numbers);
            $base = array_slice($numbers, 0, $size);
            sort($base);

            $evens = count(array_filter($base, fn($n) => $n % 2 === 0));
            $frame = count(array_intersect($base, self::FRAME_NUMBERS));

            if ($evens >= $minEvens && $evens <= $maxEvens && $frame >= $minFrame && $frame <= $maxFrame) {
                return $base;
            }
        }

        // Fallback
        $numbers = range(1, 25);
        shuffle($numbers);
        $base = array_slice($numbers, 0, $size);
        sort($base);
        return $base;
    }

    /**
     * Gera múltiplas apostas inteligentes (usado para fechamentos se necessário).
     *
     * @param int $quantity
     * @return array<array<int>>
     */
    public function generateMultipleSmartBets(int $quantity): array
    {
        $bets = [];
        $generatedHashes = [];
        
        while (count($bets) < $quantity) {
            $bet = $this->generateSmartBet();
            $hash = implode('-', $bet);
            
            if (!isset($generatedHashes[$hash])) {
                $generatedHashes[$hash] = true;
                $bets[] = $bet;
            }
        }
        
        return $bets;
    }

    /**
     * Verifica se a aposta cumpre os requisitos estatísticos da "Roleta Viciada".
     */
    private function isValidPattern(array $bet): bool
    {
        // 1. Par/Ímpar (Ideal: 7 pares e 8 ímpares OU 8 pares e 7 ímpares, ou 6 pares e 9 ímpares, ou 9 pares e 6 ímpares)
        $evens = count(array_filter($bet, fn($n) => $n % 2 === 0));
        if ($evens < 6 || $evens > 9) {
            return false;
        }

        // 2. Soma (Ideal: 180 a 220)
        $sum = array_sum($bet);
        if ($sum < 180 || $sum > 220) {
            return false;
        }

        // 3. Moldura (Ideal: 8 a 11 na moldura)
        $frame = count(array_intersect($bet, self::FRAME_NUMBERS));
        if ($frame < 8 || $frame > 11) {
            return false;
        }
        
        // 4. Sequências (Evitar sequências muito longas, ex: 10 dezenas seguidas)
        $maxSequence = 1;
        $currentSequence = 1;
        for ($i = 1; $i < count($bet); $i++) {
            if ($bet[$i] == $bet[$i - 1] + 1) {
                $currentSequence++;
                if ($currentSequence > $maxSequence) {
                    $maxSequence = $currentSequence;
                }
            } else {
                $currentSequence = 1;
            }
        }
        if ($maxSequence > 8) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se a combinação já foi sorteada historicamente.
     */
    private function isAlreadyDrawn(array $bet, array $historicalHashes): bool
    {
        $hash = implode('-', $bet);
        return isset($historicalHashes[$hash]);
    }

    /**
     * Retorna os hashes de todos os sorteios históricos para evitar repetição.
     * Usa cache para não penalizar o banco.
     */
    private function getHistoricalHashes(): array
    {
        return Cache::rememberForever('lotofacil_historical_hashes', function () {
            $results = HistoricalResult::all(['drawn_numbers']);
            $hashes = [];
            
            foreach ($results as $result) {
                $numbers = is_array($result->drawn_numbers) ? $result->drawn_numbers : json_decode((string) $result->drawn_numbers, true);
                if (is_array($numbers)) {
                    $numbers = array_map('intval', $numbers);
                    sort($numbers);
                    $hash = implode('-', $numbers);
                    $hashes[$hash] = true;
                }
            }
            
            return $hashes;
        });
    }
}
