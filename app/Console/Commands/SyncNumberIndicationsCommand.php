<?php

namespace App\Console\Commands;

use App\Services\NumberIndicationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lotofacil:indications')]
#[Description('Confere as indicações de dezenas pendentes e registra a do próximo concurso')]
class SyncNumberIndicationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NumberIndicationService $indicationService): int
    {
        $result = $indicationService->sync();

        $this->info(sprintf('Indicações conferidas: %d.', $result['evaluated']));

        if ($result['registered_for'] !== null) {
            $this->info(sprintf('Indicação registrada para o concurso %d.', $result['registered_for']));
        } else {
            $this->warn('Nenhum concurso cadastrado: nada a registrar.');
        }

        $summary = $indicationService->summary();
        $this->line($summary['verdict']);

        return self::SUCCESS;
    }
}
