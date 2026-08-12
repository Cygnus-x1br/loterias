<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard;
use App\Models\Bet;
use App\Models\Closing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_exibe_apenas_dados_do_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Closing::factory()->create([
            'user_id' => $user->id,
            'name' => 'Fechamento do usuário',
            'method' => 'integral',
            'budget' => 20,
            'status' => 'completed',
        ]);

        Closing::factory()->create([
            'user_id' => $otherUser->id,
            'name' => 'Fechamento de outro usuário',
            'method' => 'reduzido',
            'budget' => 999,
            'status' => 'completed',
        ]);

        Bet::factory()->create([
            'user_id' => $user->id,
        ]);

        Bet::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('metrics.0.value', '1')
            ->assertSet('metrics.1.value', '1')
            ->assertSet('metrics.3.value', 'R$ 20,00')
            ->assertSee('Fechamento do usuário')
            ->assertDontSee('Fechamento de outro usuário');
    }

    public function test_dashboard_exibe_estado_vazio_para_usuario_sem_registros(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Dashboard::class)
            ->assertSet('metrics.0.value', '0')
            ->assertSet('metrics.1.value', '0')
            ->assertSet('metrics.3.value', 'R$ 0,00')
            ->assertSet('distribution', [])
            ->assertSet('activities', [])
            ->assertSee('Nenhum fechamento encontrado')
            ->assertSee('Nenhuma atividade recente');
    }
}
