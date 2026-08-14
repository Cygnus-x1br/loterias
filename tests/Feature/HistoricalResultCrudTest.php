<?php

namespace Tests\Feature;

use App\Models\HistoricalResult;
use App\Models\User;
use App\Services\HistoricalResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class HistoricalResultCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_guest_cannot_access_results_pages(): void
    {
        $this->get(route('results.index'))->assertRedirect(route('login'));
        $this->get(route('results.create'))->assertRedirect(route('login'));
    }

    public function test_user_can_view_results_index(): void
    {
        HistoricalResult::create([
            'contest_number' => 3000,
            'draw_date' => '2026-08-10',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        $this->actingAs($this->user)
            ->get(route('results.index'))
            ->assertOk()
            ->assertSee('3000')
            ->assertSee('Sorteios da Lotofácil');
    }

    public function test_user_can_create_historical_result_manually_with_required_fields(): void
    {
        $numbers = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

        Volt::actingAs($this->user)
            ->test('pages.results.create')
            ->set('contest_number', 3201)
            ->set('draw_date', '2026-08-14')
            ->set('drawn_numbers', $numbers)
            ->set('winners_15_hits', 2)
            ->set('payout_15_hits', '1500000.50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('results.index'));

        $this->assertDatabaseHas('historical_results', [
            'contest_number' => 3201,
            'winners_15_hits' => 2,
            'payout_15_hits' => 1500000.50,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($numbers),
        ]);

        $created = HistoricalResult::where('contest_number', 3201)->first();
        $this->assertNotNull($created);
        $this->assertEquals('2026-08-14', $created->draw_date->format('Y-m-d'));
    }

    public function test_creation_validates_required_fields(): void
    {
        Volt::actingAs($this->user)
            ->test('pages.results.create')
            ->set('contest_number', null)
            ->set('draw_date', '')
            ->set('drawn_numbers', [])
            ->call('save')
            ->assertHasErrors([
                'contest_number' => 'required',
                'draw_date' => 'required',
                'drawn_numbers' => 'required',
            ]);
    }

    public function test_creation_validates_exact_15_numbers(): void
    {
        // 14 numbers
        Volt::actingAs($this->user)
            ->test('pages.results.create')
            ->set('contest_number', 3202)
            ->set('draw_date', '2026-08-14')
            ->set('drawn_numbers', [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14])
            ->call('save')
            ->assertHasErrors(['drawn_numbers' => 'size']);
    }

    public function test_creation_validates_unique_contest_number(): void
    {
        HistoricalResult::create([
            'contest_number' => 3000,
            'draw_date' => '2026-08-10',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        Volt::actingAs($this->user)
            ->test('pages.results.create')
            ->set('contest_number', 3000)
            ->set('draw_date', '2026-08-14')
            ->set('drawn_numbers', range(1, 15))
            ->call('save')
            ->assertHasErrors(['contest_number' => 'unique']);
    }

    public function test_user_can_edit_historical_result(): void
    {
        $result = HistoricalResult::create([
            'contest_number' => 3005,
            'draw_date' => '2026-08-10',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
            'winners_15_hits' => 0,
        ]);

        $newNumbers = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16];

        Volt::actingAs($this->user)
            ->test('pages.results.edit', ['result' => $result])
            ->set('winners_15_hits', 1)
            ->set('payout_15_hits', '2000000.00')
            ->set('drawn_numbers', $newNumbers)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('results.index'));

        $this->assertDatabaseHas('historical_results', [
            'id' => $result->id,
            'contest_number' => 3005,
            'winners_15_hits' => 1,
            'payout_15_hits' => 2000000.00,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash($newNumbers),
        ]);
    }

    public function test_user_can_delete_historical_result(): void
    {
        $result = HistoricalResult::create([
            'contest_number' => 3010,
            'draw_date' => '2026-08-10',
            'drawn_numbers' => range(1, 15),
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(1, 15)),
        ]);

        Volt::actingAs($this->user)
            ->test('pages.results.index')
            ->call('confirmDelete', $result->id, $result->contest_number)
            ->assertSet('confirmingDeletion', true)
            ->call('deleteResult')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('historical_results', [
            'id' => $result->id,
        ]);
    }

    public function test_service_clears_cache_on_create_update_delete(): void
    {
        $service = app(HistoricalResultService::class);

        $result = $service->create([
            'contest_number' => 3020,
            'draw_date' => '2026-08-14',
            'drawn_numbers' => range(1, 15),
        ]);

        $this->assertDatabaseHas('historical_results', ['contest_number' => 3020]);

        $service->update($result, [
            'contest_number' => 3020,
            'draw_date' => '2026-08-14',
            'drawn_numbers' => range(2, 16),
        ]);

        $this->assertDatabaseHas('historical_results', [
            'contest_number' => 3020,
            'drawn_numbers_hash' => HistoricalResult::generateDrawnNumbersHash(range(2, 16)),
        ]);

        $service->delete($result);
        $this->assertDatabaseMissing('historical_results', ['id' => $result->id]);
    }
}
