<?php

namespace App\Services\Betting;

use App\Models\HistoricalResult;
use App\Services\LotofacilStatisticsService;

/**
 * Identifica combinações de 15 dezenas que já saíram em algum concurso.
 *
 * Usado pelos geradores para descartar e substituir essas combinações
 * antes de contá-las como apostas do fechamento.
 */
class HistoricalDrawFilter
{
    /**
     * @var array<string, bool>|null
     */
    protected ?array $pastDrawHashes = null;

    public function __construct(
        protected LotofacilStatisticsService $statisticsService
    ) {}

    /**
     * Indica se a combinação é exatamente igual a um sorteio passado.
     * Apostas com tamanho diferente de 15 dezenas nunca são consideradas sorteios passados.
     *
     * @param  array<int, int|string>  $numbers
     */
    public function isPastDraw(array $numbers): bool
    {
        if (count($numbers) !== 15) {
            return false;
        }

        $this->pastDrawHashes ??= $this->statisticsService->getHistoricalDrawHashes();

        $normalized = array_map('intval', $numbers);

        return isset($this->pastDrawHashes[HistoricalResult::generateDrawnNumbersHash($normalized)]);
    }
}
