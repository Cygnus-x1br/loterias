<?php

namespace Tests\Feature\Services;

use App\Models\User;
use App\Services\LotofacilSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LotofacilSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private LotofacilSettingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = app(LotofacilSettingService::class);
    }

    public function test_it_returns_default_prices_when_database_is_empty(): void
    {
        $prices = $this->service->getPrices();

        $this->assertCount(6, $prices);
        $this->assertEquals(3.50, $prices[15]);
        $this->assertEquals(56.00, $prices[16]);
        $this->assertEquals(476.00, $prices[17]);
        $this->assertEquals(2856.00, $prices[18]);
        $this->assertEquals(13566.00, $prices[19]);
        $this->assertEquals(54264.00, $prices[20]);
    }

    public function test_it_returns_correct_price_for_specific_numbers_count(): void
    {
        $this->assertEquals(3.50, $this->service->getPriceFor(15));
        $this->assertEquals(56.00, $this->service->getPriceFor(16));
        $this->assertEquals(54264.00, $this->service->getPriceFor(20));
    }

    public function test_it_saves_and_persists_custom_prices(): void
    {
        $customPrices = [
            15 => 4.00,
            16 => 64.00,
            17 => 500.00,
            18 => 3000.00,
            19 => 14000.00,
            20 => 60000.00,
        ];

        $this->service->savePrices($customPrices);

        $prices = $this->service->getPrices();
        $this->assertEquals(4.00, $prices[15]);
        $this->assertEquals(64.00, $prices[16]);
        $this->assertEquals(60000.00, $prices[20]);

        $this->assertDatabaseHas('lotofacil_settings', [
            'numbers_count' => 15,
            'price' => 4.00,
        ]);
        $this->assertDatabaseHas('lotofacil_settings', [
            'numbers_count' => 20,
            'price' => 60000.00,
        ]);
    }

    public function test_it_resets_prices_to_defaults(): void
    {
        $this->service->savePrices([
            15 => 10.00,
            16 => 100.00,
        ]);

        $this->assertEquals(10.00, $this->service->getPriceFor(15));

        $this->service->resetToDefaults();

        $this->assertEquals(3.50, $this->service->getPriceFor(15));
        $this->assertEquals(56.00, $this->service->getPriceFor(16));
    }

    public function test_authenticated_user_can_view_and_update_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Configurações da Lotofácil')
            ->assertSee('3.50');

        Volt::actingAs($user)
            ->test('pages.settings.index')
            ->set('prices.15', '4.50')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Preços das apostas da Lotofácil atualizados com sucesso!');

        $this->assertEquals(4.50, $this->service->getPriceFor(15));
    }
}
