<?php

namespace Tests\Feature\Services\Betting;

use App\Jobs\GenerateClosingBetsJob;
use App\Models\Closing;
use App\Models\User;
use App\Services\Betting\ClosingGenerationProgress;
use App\Services\Betting\ClosingGenerator;
use App\Services\Betting\GenerationCancelledException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Volt\Volt;
use Tests\TestCase;

class BackgroundGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function closing(array $attributes = []): Closing
    {
        return Closing::factory()->create(array_merge([
            'user_id' => User::factory()->create()->id,
            'method' => 'random',
            'status' => 'draft',
            'base_numbers' => range(1, 20),
            'bet_size' => 15,
            'planned_bets' => 1500,
            'parameters' => null,
        ], $attributes));
    }

    public function test_expected_bets_and_queue_threshold(): void
    {
        $generator = app(ClosingGenerator::class);

        $this->assertSame(1500, $generator->expectedBets($this->closing()));
        $this->assertTrue($generator->shouldQueue($this->closing()));
        $this->assertFalse($generator->shouldQueue($this->closing(['planned_bets' => 999])));

        // Integral: C(20,15) = 15.504 apostas, mesmo sem planned_bets
        $integral = $this->closing(['method' => 'integral', 'planned_bets' => null]);
        $this->assertSame(15504, $generator->expectedBets($integral));
        $this->assertTrue($generator->shouldQueue($integral));
        $this->assertFalse($generator->shouldQueue($this->closing(['method' => 'integral', 'base_numbers' => range(1, 16), 'planned_bets' => null])));
    }

    public function test_dispatch_validates_first_marks_processing_and_queues_the_job(): void
    {
        Queue::fake();
        $closing = $this->closing();

        app(ClosingGenerator::class)->dispatchGeneration($closing);

        $this->assertSame('processing', $closing->fresh()->status);
        $this->assertSame(0, $closing->bets()->count());
        Queue::assertPushed(GenerateClosingBetsJob::class, fn (GenerateClosingBetsJob $job): bool => $job->closing->is($closing));
        $this->assertSame(1500, ClosingGenerationProgress::snapshot($closing->id)['expected']);
        $this->assertFalse(ClosingGenerationProgress::snapshot($closing->id)['running']);
    }

    public function test_dispatch_rejects_invalid_closing_without_queueing(): void
    {
        Queue::fake();
        $closing = $this->closing(['base_numbers' => range(1, 10)]);

        try {
            app(ClosingGenerator::class)->dispatchGeneration($closing);
            $this->fail('Deveria validar antes de enfileirar.');
        } catch (\InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        Queue::assertNothingPushed();
        $this->assertSame('draft', $closing->fresh()->status);
    }

    public function test_job_generates_bets_and_clears_tracking(): void
    {
        $closing = $this->closing(['planned_bets' => 1000, 'method' => 'random']);
        $token = ClosingGenerationProgress::begin($closing->id, 1000);

        (new GenerateClosingBetsJob($closing, $token))->handle(app(ClosingGenerator::class));

        $this->assertSame('completed', $closing->fresh()->status);
        $this->assertSame(1000, $closing->bets()->count());
        $this->assertFalse(ClosingGenerationProgress::isTracked($closing->id));
    }

    public function test_cancel_while_running_rolls_back_and_returns_to_draft(): void
    {
        $closing = $this->closing(['planned_bets' => 1000]);
        $token = ClosingGenerationProgress::begin($closing->id, 1000);
        $closing->update(['status' => 'processing']);
        ClosingGenerationProgress::markRunning($closing->id);
        ClosingGenerationProgress::requestCancel($closing->id);

        (new GenerateClosingBetsJob($closing, $token))->handle(app(ClosingGenerator::class));

        $this->assertSame('draft', $closing->fresh()->status);
        $this->assertSame(0, $closing->bets()->count());
        $this->assertFalse(ClosingGenerationProgress::isTracked($closing->id));
    }

    public function test_generator_raises_cancellation_once_requested(): void
    {
        $closing = $this->closing(['planned_bets' => 1000]);
        ClosingGenerationProgress::begin($closing->id, 1000);
        ClosingGenerationProgress::requestCancel($closing->id);

        $this->expectException(GenerationCancelledException::class);

        app(ClosingGenerator::class)->generate($closing);
    }

    public function test_cancel_before_the_job_starts_returns_to_draft_and_makes_job_obsolete(): void
    {
        Queue::fake();
        $closing = $this->closing();
        $generator = app(ClosingGenerator::class);
        $generator->dispatchGeneration($closing);
        $token = ClosingGenerationProgress::token($closing->id);

        $generator->cancelGeneration($closing->fresh());

        $this->assertSame('draft', $closing->fresh()->status);

        // O job antigo, que ainda estava na fila, não faz nada
        (new GenerateClosingBetsJob($closing->fresh(), $token))->handle($generator);
        $this->assertSame(0, $closing->bets()->count());
        $this->assertSame('draft', $closing->fresh()->status);
    }

    public function test_job_failure_is_reported_to_the_user(): void
    {
        $closing = $this->closing(['planned_bets' => 1000, 'method' => 'balanced', 'parameters' => ['repeated_last_draw' => [20, 25]]]);
        $token = ClosingGenerationProgress::begin($closing->id, 1000);

        (new GenerateClosingBetsJob($closing, $token))->handle(app(ClosingGenerator::class));

        $this->assertSame('failed', $closing->fresh()->status);
        $this->assertNotNull(ClosingGenerationProgress::error($closing->id));
    }

    public function test_job_failed_hook_marks_a_stuck_closing_as_failed(): void
    {
        $closing = $this->closing(['status' => 'processing']);

        (new GenerateClosingBetsJob($closing, 'x'))->failed(new \RuntimeException('timeout'));

        $this->assertSame('failed', $closing->fresh()->status);
        $this->assertStringContainsString('interrompida', (string) ClosingGenerationProgress::error($closing->id));
    }

    public function test_show_page_queues_large_closing_and_shows_progress_and_cancel(): void
    {
        Queue::fake();
        $closing = $this->closing();

        $component = Volt::actingAs($closing->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('generateBets')
            ->assertSee('Geração iniciada em segundo plano')
            ->assertSee('Na fila, aguardando o processamento')
            ->assertSee('0 de 1.500 apostas')
            ->assertSee('Cancelar geração');

        $component->call('cancelGeneration')->assertDontSee('Na fila, aguardando');
        $this->assertSame('draft', $closing->fresh()->status);
    }

    public function test_small_closings_still_generate_inline(): void
    {
        Queue::fake();
        $closing = $this->closing(['planned_bets' => 5]);

        Volt::actingAs($closing->user)
            ->test('pages.closings.show', ['closing' => $closing])
            ->call('generateBets')
            ->assertSee('5 aposta(s) gerada(s) com sucesso');

        Queue::assertNothingPushed();
        $this->assertSame(5, $closing->bets()->count());
    }

    public function test_only_the_owner_can_cancel(): void
    {
        $closing = $this->closing(['status' => 'processing']);

        Volt::actingAs(User::factory()->create())
            ->test('pages.closings.show', ['closing' => $closing])
            ->assertForbidden();
    }
}
