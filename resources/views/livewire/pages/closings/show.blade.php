<?php

use App\Models\Closing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Detalhes do fechamento'])] class extends Component
{
    use WithPagination;

    public Closing $closing;

    public function mount(Closing $closing): void
    {
        abort_unless(
            $closing->user_id === Auth::id(),
            404
        );

        $this->closing = $closing;
    }

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

    public function statusClasses(?string $status): string
    {
        return match ($status) {
            'draft' => 'bg-amber-50 text-amber-700',
            'processing' => 'bg-sky-50 text-sky-700',
            'completed' => 'bg-emerald-50 text-emerald-700',
            'failed' => 'bg-rose-50 text-rose-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    public function with(): array
    {
        return [
            'bets' => $this->closing
                ->bets()
                ->latest()
                ->paginate(15),
        ];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
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

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Detalhes do fechamento
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                {{ $closing->name }}
            </h1>

            <p class="mt-2 text-sm leading-6 text-slate-500 sm:text-base">
                Consulte os parâmetros e as apostas geradas por este fechamento.
            </p>
        </div>

        <a
            href="{{ route('closings.index') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
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

            Voltar para fechamentos
        </a>
    </section>

    <section class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                Status
            </p>

            <span class="mt-3 inline-flex rounded-full px-3 py-1.5 text-sm font-semibold {{ $this->statusClasses($closing->status) }}">
                {{ $this->statusLabel($closing->status) }}
            </span>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                Método
            </p>

            <p class="mt-3 text-base font-bold text-slate-900">
                {{ $this->methodLabel($closing->method) }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                Apostas geradas
            </p>

            <p class="mt-3 text-2xl font-extrabold text-indigo-600">
                {{ $closing->bets()->count() }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">
                Cadastro
            </p>

            <p class="mt-3 text-base font-bold text-slate-900">
                {{ $closing->created_at?->format('d/m/Y') }}
            </p>

            <p class="mt-1 text-xs text-slate-500">
                {{ $closing->created_at?->format('H:i') }}
            </p>
        </div>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Grupo-base
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ count($closing->base_numbers ?? []) }} dezenas selecionadas
                    </p>
                </div>

                <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                    {{ count($closing->base_numbers ?? []) }}/25
                </span>
            </div>

            @if (count($closing->base_numbers ?? []) > 0)
                <div class="mt-6 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                    @foreach ($closing->base_numbers ?? [] as $number)
                        <span class="flex aspect-square items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white shadow-md shadow-indigo-600/20">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </span>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-xl bg-slate-50 p-5 text-center text-sm text-slate-500">
                    Nenhuma dezena foi informada neste fechamento.
                </div>
            @endif
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l4.414 4.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Configuração
                    </h2>

                    <p class="text-sm text-slate-500">
                        Parâmetros utilizados
                    </p>
                </div>
            </div>

            <dl class="mt-6 space-y-4">
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

    @if ($closing->notes)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-base font-bold text-slate-900">
                Observações
            </h2>

            <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">
                {{ $closing->notes }}
            </p>
        </section>
    @endif

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

    <section class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <div class="flex items-start gap-3">
            <svg
                class="mt-0.5 h-5 w-5 shrink-0 text-amber-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 3h-15a2 2 0 01-1.7-3l7.5-13.3a2 2 0 013.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                As garantias exibidas são apenas os parâmetros informados no cadastro.
                O sistema ainda não calcula garantias matemáticas automaticamente.
            </p>
        </div>
    </section>
</div>
