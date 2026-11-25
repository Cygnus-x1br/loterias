<?php

namespace App\Jobs;

use App\Models\Closing;
use App\Services\Betting\ClosingGenerationProgress;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\GenerationCancelledException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;
use LogicException;
use Throwable;

/**
 * Gera em segundo plano as apostas de fechamentos grandes, com progresso e cancelamento.
 */
class GenerateClosingBetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public Closing $closing,
        public string $token,
    ) {}

    public function handle(ClosingGenerator $generator): void
    {
        $closingId = $this->closing->id;

        // Geração cancelada (ou refeita) antes de o job começar: este job ficou obsoleto
        if (ClosingGenerationProgress::token($closingId) !== $this->token) {
            return;
        }

        ClosingGenerationProgress::markRunning($closingId);

        try {
            $generator->generate($this->closing);
        } catch (GenerationCancelledException) {
            // O gerador já devolveu o fechamento para rascunho
        } catch (InvalidArgumentException|LogicException $exception) {
            ClosingGenerationProgress::fail($closingId, $exception->getMessage());
        } catch (Throwable $exception) {
            report($exception);
            ClosingGenerationProgress::fail($closingId, 'Ocorreu um erro inesperado ao gerar as apostas. Tente novamente.');
        } finally {
            ClosingGenerationProgress::finish($closingId);
        }
    }

    /**
     * Falha fora do controle do gerador (timeout, processo encerrado).
     */
    public function failed(?Throwable $exception): void
    {
        $this->closing->refresh();

        if ($this->closing->status === 'processing') {
            $this->closing->update(['status' => 'failed']);
        }

        ClosingGenerationProgress::fail($this->closing->id, 'A geração foi interrompida antes de terminar. Tente novamente.');
        ClosingGenerationProgress::finish($this->closing->id);
    }
}
