<?php

namespace App\Livewire;

use App\Models\HistoricalResult;
use App\Services\LotofacilStatisticsService;
use Livewire\Component;

class LotofacilSuggestion extends Component
{
    /**
     * Dezenas que formam a Moldura e o Centro no volante de 25 números da Lotofácil.
     */
    public const FRAME_NUMBERS = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];

    public const CENTER_NUMBERS = [7, 8, 9, 12, 13, 14, 17, 18, 19];

    /**
     * Parâmetros configuráveis pelo usuário.
     */
    public int $totalDezenasBase = 18;

    public int $repeticoesUltimoSorteio = 9;

    /**
     * Dados do último sorteio e do grupo sugerido.
     */
    public ?array $lastContest = null;

    public array $suggestedGroup = [];

    public array $groupMetrics = [];

    /**
     * Frequência histórica geral de cada dezena (1 a 25).
     */
    public array $historicalFrequencies = [];

    /**
     * Inicializa os valores padrão, carrega estatísticas e gera a primeira sugestão.
     */
    public function mount(LotofacilStatisticsService $statisticsService): void
    {
        $this->loadHistoricalData($statisticsService);
        $this->generateSuggestion();
    }

    /**
     * Carrega as dezenas do último concurso e as frequências históricas das dezenas.
     */
    public function loadHistoricalData(LotofacilStatisticsService $statisticsService): void
    {
        $lastResult = HistoricalResult::query()
            ->orderByDesc('contest_number')
            ->first(['id', 'contest_number', 'draw_date', 'drawn_numbers']);

        if ($lastResult) {
            $numbers = is_array($lastResult->drawn_numbers)
                ? $lastResult->drawn_numbers
                : (json_decode((string) $lastResult->drawn_numbers, true) ?? []);

            $numbers = array_map('intval', $numbers);
            sort($numbers);

            $this->lastContest = [
                'contest_number' => $lastResult->contest_number,
                'draw_date' => $lastResult->draw_date ? $lastResult->draw_date->format('d/m/Y') : null,
                'drawn_numbers' => $numbers,
            ];
        }

        $frequencies = $statisticsService->getNumberFrequencies();
        $this->historicalFrequencies = $frequencies->toArray();
    }

    /**
     * Atualização reativa quando os inputs mudam.
     */
    public function updatedTotalDezenasBase(): void
    {
        $this->sanitizeParameters();
        $this->generateSuggestion();
    }

    public function updatedRepeticoesUltimoSorteio(): void
    {
        $this->sanitizeParameters();
        $this->generateSuggestion();
    }

    /**
     * Garante que os limites das propriedades estejam respeitados.
     */
    private function sanitizeParameters(): void
    {
        if ($this->totalDezenasBase < 15) {
            $this->totalDezenasBase = 15;
        } elseif ($this->totalDezenasBase > 25) {
            $this->totalDezenasBase = 25;
        }

        if ($this->repeticoesUltimoSorteio < 0) {
            $this->repeticoesUltimoSorteio = 0;
        } elseif ($this->repeticoesUltimoSorteio > 15) {
            $this->repeticoesUltimoSorteio = 15;
        }

        if ($this->repeticoesUltimoSorteio > $this->totalDezenasBase) {
            $this->repeticoesUltimoSorteio = $this->totalDezenasBase;
        }
    }

    /**
     * Gera o Grupo Base de dezenas combinando repetições e novas dezenas com filtros estatísticos.
     */
    public function generateSuggestion(): void
    {
        $this->sanitizeParameters();

        $lastDrawn = $this->lastContest['drawn_numbers'] ?? [];

        // 1. Inclusão de Dezenas Repetidas do último sorteio
        $selectedRepeated = $this->selectRepeatedNumbers($lastDrawn, $this->repeticoesUltimoSorteio);

        // 2. Preenchimento com Novas Dezenas (não sorteadas no último sorteio)
        $neededNewCount = $this->totalDezenasBase - count($selectedRepeated);
        $allNumbers = range(1, 25);
        $nonDrawnNumbers = array_values(array_diff($allNumbers, $lastDrawn));

        $selectedNew = $this->selectNewNumbers($nonDrawnNumbers, $selectedRepeated, $neededNewCount);

        // Grupo consolidado e ordenado
        $group = array_merge($selectedRepeated, $selectedNew);
        sort($group);

        $this->suggestedGroup = $group;
        $this->groupMetrics = $this->calculateMetrics($this->suggestedGroup, $selectedRepeated, $selectedNew);
    }

    /**
     * Seleciona as dezenas do último sorteio priorizando as mais quentes historicamente com variação.
     */
    private function selectRepeatedNumbers(array $lastDrawn, int $count): array
    {
        if ($count <= 0 || empty($lastDrawn)) {
            return [];
        }

        if ($count >= count($lastDrawn)) {
            return $lastDrawn;
        }

        // Ordena as dezenas do último sorteio pela frequência histórica decrescente
        $ranked = $lastDrawn;
        usort($ranked, function ($a, $b) {
            $freqA = $this->historicalFrequencies[$a] ?? 0;
            $freqB = $this->historicalFrequencies[$b] ?? 0;

            if ($freqA === $freqB) {
                return random_int(-1, 1);
            }

            return $freqB <=> $freqA;
        });

        // Adiciona aleatoriedade ponderada entre as melhores opções para gerar combinações variadas
        $pool = array_slice($ranked, 0, min(count($ranked), $count + 4));
        shuffle($pool);

        return array_slice($pool, 0, $count);
    }

    /**
     * Seleciona as novas dezenas buscando equilíbrio de Par/Ímpar, Moldura/Centro, Soma e Sequências.
     */
    private function selectNewNumbers(array $availableNumbers, array $currentGroup, int $count): array
    {
        if ($count <= 0 || empty($availableNumbers)) {
            return [];
        }

        if ($count >= count($availableNumbers)) {
            return $availableNumbers;
        }

        // Pontua e ordena as dezenas candidatas
        $scoredCandidates = [];
        $frameSet = array_flip(self::FRAME_NUMBERS);

        foreach ($availableNumbers as $num) {
            $score = 0;

            // 1. Frequência histórica
            $freq = $this->historicalFrequencies[$num] ?? 0;
            $score += $freq * 0.1;

            // 2. Pontuação por Paridade (almejando ~50% de pares e ímpares no grupo base)
            $currentEvens = count(array_filter($currentGroup, fn ($n) => $n % 2 === 0));
            $currentOdds = count($currentGroup) - $currentEvens;
            $isEven = ($num % 2 === 0);

            if ($currentEvens < $currentOdds && $isEven) {
                $score += 15;
            } elseif ($currentOdds <= $currentEvens && ! $isEven) {
                $score += 15;
            }

            // 3. Distribuição Moldura/Centro (proporção ideal aproximada 10 moldura para 5 ou 6 centro)
            $currentFrame = count(array_filter($currentGroup, fn ($n) => isset($frameSet[$n])));
            $currentCenter = count($currentGroup) - $currentFrame;
            $isFrame = isset($frameSet[$num]);

            $targetFrameRatio = 10 / 15;
            $currentFrameRatio = count($currentGroup) > 0 ? $currentFrame / count($currentGroup) : 0.66;

            if ($currentFrameRatio < $targetFrameRatio && $isFrame) {
                $score += 12;
            } elseif ($currentFrameRatio >= $targetFrameRatio && ! $isFrame) {
                $score += 12;
            }

            // 4. Penalidade para evitar sequências excessivas (4+ dezenas consecutivas)
            $tempGroup = array_merge($currentGroup, [$num]);
            sort($tempGroup);
            if ($this->hasLongConsecutiveSequence($tempGroup, 4)) {
                $score -= 25;
            }

            $scoredCandidates[] = [
                'number' => $num,
                'score' => $score + mt_rand(0, 5), // Leve aleatoriedade
            ];
        }

        usort($scoredCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice(array_column($scoredCandidates, 'number'), 0, $count);
    }

    /**
     * Verifica se um conjunto de números contém uma sequência contínua de tamanho mínimo especificado.
     */
    private function hasLongConsecutiveSequence(array $numbers, int $minConsecutive = 4): bool
    {
        $count = count($numbers);
        if ($count < $minConsecutive) {
            return false;
        }

        $currentRun = 1;
        for ($i = 1; $i < $count; $i++) {
            if ($numbers[$i] === $numbers[$i - 1] + 1) {
                $currentRun++;
                if ($currentRun >= $minConsecutive) {
                    return true;
                }
            } else {
                $currentRun = 1;
            }
        }

        return false;
    }

    /**
     * Calcula as métricas e estatísticas de distribuição do grupo sugerido.
     */
    private function calculateMetrics(array $group, array $repeated, array $new): array
    {
        if (empty($group)) {
            return [];
        }

        $evens = count(array_filter($group, fn ($n) => $n % 2 === 0));
        $odds = count($group) - $evens;

        $frameSet = array_flip(self::FRAME_NUMBERS);
        $frame = count(array_filter($group, fn ($n) => isset($frameSet[$n])));
        $center = count($group) - $frame;

        $sum = array_sum($group);

        // Projeção média da soma para um fechamento padrão de 15 dezenas
        $projected15Sum = count($group) > 0 ? round(($sum / count($group)) * 15) : 0;

        return [
            'total' => count($group),
            'repeated_count' => count($repeated),
            'new_count' => count($new),
            'repeated_numbers' => $repeated,
            'new_numbers' => $new,
            'evens' => $evens,
            'odds' => $odds,
            'frame' => $frame,
            'center' => $center,
            'sum' => $sum,
            'projected_15_sum' => $projected15Sum,
            'sum_status' => ($projected15Sum >= 180 && $projected15Sum <= 220) ? 'Faixa Ideal (180-220)' : 'Fora da Faixa Ideal',
        ];
    }

    public function render()
    {
        return view('livewire.lotofacil-suggestion');
    }
}
