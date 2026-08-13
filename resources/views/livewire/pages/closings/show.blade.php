<?php

use App\Models\Closing;
use App\Services\Betting\ClosingGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Detalhes do fechamento'])] class extends Component
{
    public Closing $closing;

    public ?string $generationError = null;

    public ?string $generationSuccess = null;

    public function mount(Closing $closing): void
    {
        // Garante que apenas o proprietário pode ver o fechamento
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        $this->closing = $closing;
    }

    /**
     * Retorna o nome amigável do método.
     */
    public function methodLabel(?string $method): string
    {
        return match ($method) {
            'integral' => 'Combinação integral',
            'reduced' => 'Fechamento reduzido',
            'wheel' => 'Sistema de roda',
            'random' => 'Geração aleatória',
            'balanced' => 'Geração equilibrada',
            default => ucfirst((string) $method),
        };
    }

    /**
     * Retorna o nome amigável do status.
     */
    public function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Rascunho',
            'processing' => 'Em processamento',
            'completed' => 'Concluído',
            'failed' => 'Falhou',
            default => ucfirst((string) $status),
        };
    }

    /**
     * Retorna as classes CSS para o status.
     */
    public function statusClasses(?string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-100 text-slate-700',
            'processing' => 'bg-sky-100 text-sky-700',
            'completed' => 'bg-emerald-100 text-emerald-700',
            'failed' => 'bg-rose-100 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Indica se o método de geração do fechamento está implementado.
     */
    public function isMethodImplemented(): bool
    {
        return in_array($this->closing->method, ClosingGenerator::implementedMethods(), true);
    }

    /**
     * Dispara a geração de apostas para o fechamento.
     */
    public function generateBets(): void
    {
        $this->generationError = null;
        $this->generationSuccess = null;

        if ($this->closing->status !== 'draft' && $this->closing->status !== 'failed') {
            $this->generationError = 'A geração só pode ser disparada para fechamentos em rascunho ou que falharam.';
            return;
        }

        if (! $this->isMethodImplemented()) {
            $this->generationError = 'Este método de fechamento ainda não possui geração implementada.';
            return;
        }

        try {
            $createdBets = app(ClosingGenerator::class)->generate($this->closing);
            $this->generationSuccess = "{$createdBets} aposta(s) gerada(s) com sucesso.";
        } catch (\InvalidArgumentException|\LogicException $exception) {
            $this->generationError = $exception->getMessage();
        } catch (\Throwable $exception) {
            report($exception);
            $this->generationError = 'Ocorreu um erro inesperado ao gerar as apostas. Tente novamente.';
        } finally {
            // Recarrega o fechamento para atualizar o status e a lista de apostas
            $this->closing->refresh();
        }
    }

    /**
     * Consulta paginada das apostas vinculadas ao fechamento.
     */
    public function with(): array
    {
        return [
            'bets' => $this->closing->bets()->paginate(10),
        ];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <main class="lg:col-span-2">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
                            <a
                                href="{{ route('dashboard') }}"
                                class="transition hover:text-indigo-600"
                            >
                                Dashboard
                            </a>

                            <span>/</span>

                            <a
                                href="{{ route('closings.index') }}"
                                class="transition hover:text-indigo-600"
                            >
                                Fechamentos
                            </a>

                            <span>/</span>

                            <span class="font-medium text-slate-700">
                                Detalhes
                            </span>
                        </div>

                        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">
                            {{ $closing->name }}
                        </h1>

                        <span @class([
                            'mt-3 inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                            $this->statusClasses($closing->status),
                        ])>
                            {{ $this->statusLabel($closing->status) }}
                        </span>
                    </div>

                    <a
                        href="{{ route('closings.index') }}"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                    >
                        <svg
                            class="h-4 w-4"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M10 19l-7-7m0 0l7-7m-7 7h18"
                            />
                        </svg>

                        Voltar
                    </a>
                </div>

                @if ($closing->notes)
                    <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="whitespace-pre-line text-sm leading-6 text-slate-600">
                            {{ $closing->notes }}
                        </p>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Geração de apostas
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Dispare a geração de apostas para este fechamento.
                        </p>
                    </div>

                    @if ($closing->status === 'completed')
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                            <p class="font-medium">
                                Apostas geradas com sucesso!
                            </p>
                        </div>
                    @elseif ($closing->status === 'processing')
                        <div class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                            <p class="font-medium">
                                A geração deste fechamento está em processamento.
                            </p>
                        </div>
                    @elseif (! $this->isMethodImplemented())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                            <p class="font-medium">
                                O método "{{ $this->methodLabel($closing->method) }}" ainda não possui um gerador implementado.
                            </p>
                        </div>
                    @else
                        <button
                            type="button"
                            wire:click="generateBets"
                            wire:loading.attr="disabled"
                            wire:target="generateBets"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 10V3L4 14h7v7l9-11h-7z"
                                />
                            </svg>

                            <span wire:loading.remove wire:target="generateBets">
                                Gerar apostas
                            </span>

                            <span wire:loading wire:target="generateBets">
                                Gerando...
                            </span>
                        </button>
                    @endif
                </div>

                @if ($closing->status === 'processing')
                    <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-sky-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z"
                                />
                            </svg>

                            <p class="text-sm leading-6 text-sky-800">
                                A geração deste fechamento está em processamento.
                            </p>
                        </div>
                    </div>
                @endif

                @if ($generationError)
                    <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v3m0 4h.01M12 21a9 9 0 100-18 9 9 0 000 18z"
                                />
                            </svg>

                            <p class="text-sm leading-6 text-rose-800">
                                {{ $generationError }}
                            </p>
                        </div>
                    </div>
                @endif

                @if ($generationSuccess)
                    <div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex items-start gap-3">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>

                            <p class="text-sm leading-6 text-emerald-800">
                                {{ $generationSuccess }}
                            </p>
                        </div>
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Apostas vinculadas
                        </h2>

                        <p class="mt-1 text-sm text-slate-500">
                            Apostas geradas por este fechamento.
                        </p>
                    </div>

                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                        {{ $closing->bets()->count() }} apostas
                    </span>
                </div>

                @if ($bets->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Aposta
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Dezenas
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Status
                                    </th>

                                    <th class="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 sm:px-6">
                                        Cadastro
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-slate-100 bg-white">
                                @foreach ($bets as $bet)
                                    <tr
                                        wire:key="closing-bet-{{ $bet->id }}"
                                        class="transition hover:bg-slate-50"
                                    >
                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            <div class="font-semibold text-slate-900">
                                                {{ $bet->name ?: 'Aposta #' . $bet->id }}
                                            </div>

                                            <div class="mt-1 text-xs text-slate-500">
                                                #{{ $bet->id }}
                                            </div>
                                        </td>

                                        <td class="px-5 py-4 sm:px-6">
                                            <div class="flex max-w-xl flex-wrap gap-1.5">
                                                @foreach ($bet->numbers ?? [] as $number)
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 sm:px-6">
                                            @if ($bet->status === 'active')
                                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    Ativa
                                                </span>
                                            @else
                                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                    {{ ucfirst((string) $bet->status) }}
                                                </span>
                                            @endif
                                        </td>

                                        <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500 sm:px-6">
                                            {{ $bet->created_at?->format('d/m/Y H:i') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                        {{ $bets->links() }}
                    </div>
                @else
                    <div class="px-5 py-16 text-center sm:px-6">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                            <svg
                                class="h-8 w-8"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586A2 2 0 0119 8.414V19a2 2 0 01-2 2z"
                                />
                            </svg>
                        </div>

                        <h3 class="mt-5 text-lg font-bold text-slate-900">
                            Nenhuma aposta vinculada
                        </h3>

                        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                            Este fechamento ainda não possui apostas geradas.
                        </p>
                    </div>
                @endif
            </section>
        </main>

        <aside class="lg:col-span-1 space-y-6">
            <dl class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-4">
                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Método
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $this->methodLabel($closing->method) }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Grupo-base
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        <div class="flex flex-wrap gap-1">
                            @foreach ($closing->base_numbers ?? [] as $number)
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-700">
                                    {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endforeach
                        </div>
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Tamanho da aposta
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->bet_size }} dezenas
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Apostas planejadas
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->planned_bets }}
                    </dd>
                </div>

                {{-- Parâmetros de Geração Equilibrada --}}
                @if ($closing->method === 'balanced' && ! empty($closing->parameters))
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <h3 class="text-sm font-bold text-slate-700">Parâmetros de Equilíbrio</h3>
                        @if (isset($closing->parameters['even_odd_balance']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Pares/Ímpares
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    {{ $closing->parameters['even_odd_balance'][0] }} - {{ $closing->parameters['even_odd_balance'][1] }}
                                </dd>
                            </div>
                        @endif
                        @if (isset($closing->parameters['sum_range']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Soma das Dezenas
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    {{ $closing->parameters['sum_range'][0] }} - {{ $closing->parameters['sum_range'][1] }}
                                </dd>
                            </div>
                        @endif
                        @if (isset($closing->parameters['primes_count']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Dezenas Primas
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    {{ $closing->parameters['primes_count'][0] }} - {{ $closing->parameters['primes_count'][1] }}
                                </dd>
                            </div>
                        @endif
                        @if (isset($closing->parameters['fibonacci_count']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Dezenas Fibonacci
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    {{ $closing->parameters['fibonacci_count'][0] }} - {{ $closing->parameters['fibonacci_count'][1] }}
                                </dd>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Parâmetros do Sistema de Roda --}}
                @if ($closing->method === 'wheel' && ! empty($closing->parameters))
                    <div class="space-y-3 pt-3 border-t border-slate-100">
                        <h3 class="text-sm font-bold text-slate-700">Parâmetros do Sistema de Roda</h3>
                        @if (isset($closing->parameters['fixed_numbers']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Dezenas Fixas
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($closing->parameters['fixed_numbers'] ?? [] as $number)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-700">
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </dd>
                            </div>
                        @endif
                        @if (isset($closing->parameters['variable_numbers']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Dezenas Variáveis
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($closing->parameters['variable_numbers'] ?? [] as $number)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-700">
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </dd>
                            </div>
                        @endif
                        @if (isset($closing->parameters['wheel_size']))
                            <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                                <dt class="text-sm text-slate-500">
                                    Tamanho da Roda
                                </dt>
                                <dd class="text-sm font-bold text-slate-800">
                                    {{ $closing->parameters['wheel_size'] }} dezenas
                                </dd>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                    <dt class="text-sm text-slate-500">
                        Garantia
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->guarantee ? $closing->guarantee . ' acertos' : 'Não informada' }}
                    </dd>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <dt class="text-sm text-slate-500">
                        Orçamento
                    </dt>

                    <dd class="text-sm font-bold text-slate-800">
                        {{ $closing->budget !== null ? 'R$ ' . number_format((float) $closing->budget, 2, ',', '.') : 'Não informado' }}
                    </dd>
                </div>
            </dl>
        </aside>
    </div>
</div>
