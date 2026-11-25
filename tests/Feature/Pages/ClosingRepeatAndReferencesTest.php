<?php

namespace Tests\Feature\Pages;

use App\Models\Bet;
use App\Models\Closing;
use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\BaseNumbersSuggestionService;
use App\Services\Betting\ClosingGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ClosingRepeatAndReferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /**
     * @param  array<int, int>  $numbers
     */
    private function createContest(int $contestNumber, array $numbers): HistoricalResult
    {
        return HistoricalResult::create([
            'contest_number' => $contestNumber,
            'draw_date' => now()->subDays(50 - $contestNumber)->toDateString(),
            'drawn_numbers' => $numbers,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
            'score' => 500,
        ]);
    }

    /**
     * Fechamento com 3 apostas (dezenas conhecidas) e status/concurso configuráveis.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function closingWithBets(array $attributes = []): Closing
    {
        $closing = Closing::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'name' => 'Meu fechamento',
            'method' => 'balanced',
            'status' => 'completed',
            'base_numbers' => range(1, 18),
            'bet_size' => 15,
            'planned_bets' => 3,
            'parameters' => ['even_odd_balance' => [6, 9], 'spread_bets' => true],
            'generation_warnings' => ['Aviso da geração original'],
            'notes' => 'Observação do fechamento',
        ], $attributes));

        foreach ([range(1, 15), range(2, 16), range(3, 17)] as $index => $numbers) {
            Bet::create([
                'user_id' => $this->user->id,
                'closing_id' => $closing->id,
                'name' => 'Meu fechamento - Aposta '.($index + 1),
                'numbers' => $numbers,
                'source' => 'closing',
                'method' => 'balanced',
                'status' => $closing->status === 'placed' ? 'placed' : 'active',
                'contest_number' => $closing->contest_number,
                'draw_date' => $closing->draw_date,
                'hits' => $closing->status === 'placed' ? 12 : null,
                'notes' => $index === 0 ? 'Filtro relaxado' : null,
            ]);
        }

        return $closing->refresh();
    }

    public function test_repeating_copies_configuration_and_bets_for_another_contest(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 3777]);

        $repeated = app(ClosingGenerator::class)->repeatForContest($original, 3778);

        $this->assertNotSame($original->id, $repeated->id);
        $this->assertSame('Meu fechamento - Concurso 3778', $repeated->name);
        $this->assertSame(3778, $repeated->target_contest_number);
        $this->assertNull($repeated->contest_number);
        $this->assertNull($repeated->draw_date);
        $this->assertSame('completed', $repeated->status);
        $this->assertSame($original->method, $repeated->method);
        $this->assertSame($original->base_numbers, $repeated->base_numbers);
        $this->assertSame($original->parameters, $repeated->parameters);
        $this->assertSame($original->planned_bets, $repeated->planned_bets);
        $this->assertSame($original->notes, $repeated->notes);
        $this->assertSame(['Aviso da geração original'], $repeated->generation_warnings);

        $bets = $repeated->bets()->orderBy('id')->get();
        $this->assertCount(3, $bets);
        $this->assertSame([range(1, 15), range(2, 16), range(3, 17)], $bets->pluck('numbers')->all());
        $this->assertSame('Meu fechamento - Concurso 3778 - Aposta 1', $bets[0]->name);
        $this->assertSame('Filtro relaxado', $bets[0]->notes);

        // O original permanece intacto
        $this->assertSame(3, $original->bets()->count());
        $this->assertSame(3777, $original->fresh()->target_contest_number);
    }

    public function test_repeating_a_placed_closing_starts_clean(): void
    {
        $original = $this->closingWithBets([
            'status' => 'placed',
            'contest_number' => 3777,
            'target_contest_number' => 3777,
            'draw_date' => '2026-09-26',
        ]);
        $original->sharedWith()->attach(User::factory()->create()->id);

        $repeated = app(ClosingGenerator::class)->repeatForContest($original, 3778);

        $this->assertSame('completed', $repeated->status);
        $this->assertNull($repeated->contest_number);
        $this->assertNull($repeated->draw_date);
        $this->assertSame(0, $repeated->sharedWith()->count());

        foreach ($repeated->bets as $bet) {
            $this->assertSame('active', $bet->status);
            $this->assertNull($bet->contest_number);
            $this->assertNull($bet->draw_date);
            $this->assertNull($bet->hits);
        }

        // As apostas do original continuam apostadas e conferidas
        $this->assertSame(3, $original->bets()->where('status', 'placed')->where('hits', 12)->count());
    }

    public function test_repeating_without_bets_creates_a_draft_with_the_same_configuration(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 3777]);

        $repeated = app(ClosingGenerator::class)->repeatForContest($original, 3778, copyBets: false);

        $this->assertSame('draft', $repeated->status);
        $this->assertSame(0, $repeated->bets()->count());
        $this->assertNull($repeated->generation_warnings);
        $this->assertSame($original->base_numbers, $repeated->base_numbers);
        $this->assertSame(3778, $repeated->target_contest_number);
    }

    public function test_repeating_replaces_the_contest_suffix_instead_of_stacking_it(): void
    {
        $original = $this->closingWithBets(['name' => 'Meu fechamento - Concurso 3777', 'target_contest_number' => 3777]);
        $generator = app(ClosingGenerator::class);

        $second = $generator->repeatForContest($original, 3778);
        $third = $generator->repeatForContest($second, 3779);

        $this->assertSame('Meu fechamento - Concurso 3778', $second->name);
        $this->assertSame('Meu fechamento - Concurso 3779', $third->name);
    }

    public function test_repeating_rejects_the_same_or_invalid_contest(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 3777]);

        foreach ([3777, 0, -4] as $contest) {
            try {
                app(ClosingGenerator::class)->repeatForContest($original, $contest);
                $this->fail("Deveria rejeitar o concurso {$contest}.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, Closing::count());
    }

    public function test_repeated_closing_counts_as_previous_group_for_that_contest(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 3777]);

        app(ClosingGenerator::class)->repeatForContest($original, 3778);

        $previous = app(BaseNumbersSuggestionService::class)->previousBasesFor($this->user->id, 3778);

        $this->assertSame([range(1, 18)], $previous);
    }

    public function test_show_page_repeats_closing_and_redirects_to_the_copy(): void
    {
        $this->createContest(3, range(1, 15));
        $original = $this->closingWithBets(['target_contest_number' => 5]);

        $component = Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $original])
            ->assertSee('Repetir para outro concurso')
            ->call('openRepeatModal')
            // Primeiro concurso livre: depois do original (5), não só depois do último sorteado (3)
            ->assertSet('repeatContestNumber', 6)
            ->assertSet('repeatCopyBets', true)
            ->assertSet('showRepeatModal', true)
            ->set('repeatContestNumber', 7)
            ->call('repeatForContest')
            ->assertHasNoErrors();

        $repeated = Closing::where('target_contest_number', 7)->first();

        $this->assertNotNull($repeated);
        $this->assertSame(3, $repeated->bets()->count());
        $component->assertRedirect(route('closings.show', $repeated));
    }

    public function test_show_page_suggests_the_next_unplayed_contest_when_original_has_no_contest(): void
    {
        $this->createContest(3, range(1, 15));
        $original = $this->closingWithBets();

        Volt::actingAs($this->user)
            ->test('pages.closings.show', ['closing' => $original])
            ->call('openRepeatModal')
            ->assertSet('repeatContestNumber', 4);
    }

    public function test_show_page_validates_the_target_contest(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 5]);

        $component = Volt::actingAs($this->user)->test('pages.closings.show', ['closing' => $original]);

        $component->call('openRepeatModal')
            ->set('repeatContestNumber', 5)
            ->call('repeatForContest')
            ->assertHasErrors('repeatContestNumber');

        $component->set('repeatContestNumber', null)
            ->call('repeatForContest')
            ->assertHasErrors('repeatContestNumber');

        $this->assertSame(1, Closing::count());
    }

    public function test_only_the_owner_can_repeat_a_closing(): void
    {
        $original = $this->closingWithBets(['target_contest_number' => 5]);
        $participant = User::factory()->create();
        $original->sharedWith()->attach($participant->id);

        Volt::actingAs($participant)
            ->test('pages.closings.show', ['closing' => $original])
            ->assertDontSee('Repetir para outro concurso')
            ->call('openRepeatModal')
            ->assertForbidden();

        Volt::actingAs($participant)
            ->test('pages.closings.show', ['closing' => $original])
            ->set('repeatContestNumber', 6)
            ->call('repeatForContest')
            ->assertForbidden();

        $this->assertSame(1, Closing::count());
    }

    public function test_new_closing_grid_marks_numbers_drawn_in_the_last_contest(): void
    {
        $this->createContest(1, range(1, 15));
        $this->createContest(2, range(11, 25));
        $this->createContest(3, range(1, 15));

        $html = Volt::actingAs($this->user)->test('pages.closings.create')->html();

        $this->assertSame(15, substr_count($html, 'data-last-drawn="true"'));
        $this->assertStringContainsString('Sorteada no último concurso (3)', $html);

        // A marca fica nas dezenas 1 a 15, não nas demais
        foreach (range(1, 15) as $number) {
            $this->assertMatchesRegularExpression('/wire:click="toggleNumber\('.$number.'\)"\s+data-last-drawn="true"/', $html);
        }
        $this->assertDoesNotMatchRegularExpression('/wire:click="toggleNumber\(16\)"\s+data-last-drawn="true"/', $html);
    }

    public function test_reference_panel_lists_delayed_numbers_with_their_delay(): void
    {
        $this->createContest(1, range(1, 15));
        $this->createContest(2, range(11, 25));
        $this->createContest(3, range(1, 15));
        $this->createContest(4, range(1, 15));

        $html = Volt::actingAs($this->user)->test('pages.closings.create')->html();

        // Dezenas 16 a 20 saíram no concurso 2 (2 sem sair); 21 a 25 também; 1 a 15 saíram no último
        $this->assertSame(10, substr_count($html, 'data-delayed-number='));
        foreach (range(16, 25) as $number) {
            $this->assertStringContainsString('data-delayed-number="'.$number.'"', $html);
        }
        $this->assertStringNotContainsString('data-delayed-number="1"', $html);
        $this->assertStringContainsString('Dezenas com atraso', $html);
        $this->assertStringContainsString('uma dezena atrasada não tem mais chance de sair', $html);
    }

    public function test_new_closing_page_without_history_has_no_marks_or_delay_panel(): void
    {
        $html = Volt::actingAs($this->user)->test('pages.closings.create')->html();

        $this->assertStringNotContainsString('data-last-drawn', $html);
        $this->assertStringNotContainsString('data-delayed-number', $html);
    }
}
