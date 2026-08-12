<?php

use App\Models\Closing;
use App\Services\Betting\ClosingGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;
// use Throwable;

new #[Layout('layouts.app', ['title' => 'Fechamentos'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $method = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMethod(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'status',
            'method',
        ]);

        $this->resetPage();
    }

    /**
     * Exclui somente um fechamento pertencente ao usuário autenticado.
     */
    public function delete(int $closingId): void
    {
        $closing = Closing::query()
            ->where('id', $closingId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $closing) {
            session()->flash(
                'error',
                'Fechamento não encontrado ou você não tem permissão para excluí-lo.'
            );

            return;
        }

        $closing->delete();

        session()->flash(
            'success',
            'Fechamento excluído com sucesso.'
        );

        $this->resetPage();
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
     * Consulta paginada dos fechamentos do usuário.
     */
    public function with(): array
    {
        $closings = Closing::query()
            ->where('user_id', Auth::id())
            ->when(
                trim($this->search) !== '',
                function ($query): void {
                    $search = trim($this->search);

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('method', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->status !== '',
                fn ($query) => $query->where('status', $this->status)
            )
            ->when(
                $this->method !== '',
                fn ($query) => $query->where('method', $this->method)
            )
            ->latest()
            ->paginate(10);

        return [
            'closings' => $closings,
        ];
    }
    /**
     * Gera as apostas de um fechamento integral.
     */
    public function generate(int $closingId): void
    {
        $closing = Closing::query()
            ->where('id', $closingId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $closing) {
            session()->flash(
                'error',
                'Fechamento não encontrado ou você não tem permissão para executá-lo.'
            );

            return;
        }

        if ($closing->status !== 'draft') {
            session()->flash(
                'error',
                'Somente fechamentos em rascunho podem ser executados.'
            );

            return;
        }

        try {
            $createdBets = app(ClosingGenerator::class)->generate($closing);

            session()->flash(
                'success',
                "{$createdBets} aposta(s) gerada(s) com sucesso."
            );
        } catch (\Throwable $exception) {
            report($exception);

            session()->flash(
                'error',
                'Não foi possível gerar as apostas deste fechamento.'
            );
        }
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

                <span class="font-medium text-slate-700">
                    Fechamentos
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Planejamento de fechamentos
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Meus fechamentos
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Consulte e gerencie os fechamentos configurados na sua conta.
            </p>
        </div>

        <a
            href="{{ route('closings.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
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
                    d="M12 4v16m8-8H4"
                />
            </svg>

            Novo fechamento
        </a>
    </section>

    @if (session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <svg
                class="mt-0.5 h-5 w-5 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M5 13l4 4L19 7"
                />
            </svg>

            <p class="text-sm font-medium">
                {{ session('success') }}
            </p>
        </div>
    @endif

    @if (session('error'))
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
            <svg
                class="mt-0.5 h-5 w-5 shrink-0"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                />
            </svg>

            <p class="text-sm font-medium">
                {{ session('error') }}
            </p>
        </div>
    @endif

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
            <div class="flex-1">
                <label
                    for="search"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Buscar fechamentos
                </label>

                <div class="relative mt-2">
                    <svg
                        class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M21 21l-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z"
                        />
                    </svg>

                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Pesquise por nome, método ou status"
                        class="block w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div class="w-full lg:max-w-xs">
                <label
                    for="method"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Método
                </label>

                <select
                    id="method"
                    wire:model.live="method"
                    class="mt-2 block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Todos os métodos</option>
                    <option value="reduced">Fechamento reduzido</option>
                    <option value="integral">Combinação integral</option>
                    <option value="wheel">Sistema de roda</option>
                    <option value="random">Geração aleatória</option>
                    <option value="balanced">Geração equilibrada</option>
                </select>
            </div>

            <div class="w-full lg:max-w-xs">
                <label
                    for="status"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Status
                </label>

                <select
                    id="status"
                    wire:model.live="status"
                    class="mt-2 block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Todos os status</option>
                    <option value="draft">Rascunhos</option>
                    <option value="processing">Em processamento</option>
                    <option value="completed">Concluídos</option>
                    <option value="failed">Falhos</option>
                </select>
            </div>

            @if ($search !== '' || $status !== '' || $method !== '')
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700"
                >
                    Limpar filtros
                </button>
            @endif
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Fechamentos cadastrados
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Acompanhe as configurações salvas para uso futuro.
                </p>
            </div>

            <div
                wire:loading
                class="text-sm font-medium text-indigo-600"
            >
                Atualizando...
            </div>
        </div>

        @if ($closings->count() > 0)
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Fechamento
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Grupo-base
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Configuração
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Método
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Status
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Cadastro
                            </th>

                            <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                                Ações
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($closings as $closing)
                            <tr
                                wire:key="closing-row-{{ $closing->id }}"
                                class="transition hover:bg-slate-50"
                            >
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-semibold text-slate-900">
                                        {{ $closing->name }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        #{{ $closing->id }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex max-w-xs flex-wrap gap-1.5">
                                        @foreach ($closing->base_numbers ?? [] as $number)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>

                                    <p class="mt-2 text-xs text-slate-500">
                                        {{ count($closing->base_numbers ?? []) }} dezenas no grupo-base
                                    </p>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="text-sm font-semibold text-slate-700">
                                        {{ $closing->bet_size }} dezenas por aposta
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $closing->planned_bets ?: 'Não informado' }} apostas planejadas
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="text-sm font-medium text-slate-700">
                                        {{ $this->methodLabel($closing->method) }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($closing->status === 'draft')
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                            Rascunho
                                        </span>
                                    @elseif ($closing->status === 'processing')
                                        <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                            Em processamento
                                        </span>
                                    @elseif ($closing->status === 'completed')
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            Concluído
                                        </span>
                                    @else
                                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                            {{ $this->statusLabel($closing->status) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                    {{ $closing->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    @if ($closing->status === 'draft' && $closing->method === 'integral')
                                        <button
                                            type="button"
                                            wire:click="generate({{ $closing->id }})"
                                            wire:confirm="Tem certeza que deseja gerar as apostas deste fechamento?"
                                            wire:loading.attr="disabled"
                                            wire:target="generate({{ $closing->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
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

                                            Gerar apostas
                                        </button>
                                    @endif
<a
    href="{{ route('closings.show', $closing) }}"
    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50"
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
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
        />

        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
        />
    </svg>

    Visualizar
</a>
                                    <button
                                        type="button"
                                        wire:click="delete({{ $closing->id }})"
                                        wire:confirm="Tem certeza que deseja excluir este fechamento?"
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
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
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
                                            />
                                        </svg>

                                        Excluir
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($closings as $closing)
                    <article
                        wire:key="closing-card-{{ $closing->id }}"
                        class="space-y-4 p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-900">
                                    {{ $closing->name }}
                                </h3>

                                <p class="mt-1 text-xs text-slate-500">
                                    Cadastrado em {{ $closing->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            @if ($closing->status === 'draft')
                                <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                    Rascunho
                                </span>
                            @elseif ($closing->status === 'processing')
                                <span class="shrink-0 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                    Em processamento
                                </span>
                            @elseif ($closing->status === 'completed')
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    Concluído
                                </span>
                            @else
                                <span class="shrink-0 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700">
                                    {{ $this->statusLabel($closing->status) }}
                                </span>
                            @endif
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                Grupo-base
                            </p>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($closing->base_numbers ?? [] as $number)
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                            <div>
                                <p class="text-xs text-slate-500">
                                    Método
                                </p>

                                <p class="mt-1 text-sm font-semibold text-slate-700">
                                    {{ $this->methodLabel($closing->method) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-500">
                                    Apostas planejadas
                                </p>

                                <p class="mt-1 text-sm font-semibold text-slate-700">
                                    {{ $closing->planned_bets ?: 'Não informado' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-500">
                                    Tamanho da aposta
                                </p>

                                <p class="mt-1 text-sm font-semibold text-slate-700">
                                    {{ $closing->bet_size }} dezenas
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-slate-500">
                                    Garantia
                                </p>

                                <p class="mt-1 text-sm font-semibold text-slate-700">
                                    {{ $closing->guarantee ? $closing->guarantee . ' acertos' : 'Não informada' }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                            @if ($closing->status === 'draft' && $closing->method === 'integral')
                                <button
                                    type="button"
                                    wire:click="generate({{ $closing->id }})"
                                    wire:confirm="Tem certeza que deseja gerar as apostas deste fechamento?"
                                    wire:loading.attr="disabled"
                                    wire:target="generate({{ $closing->id }})"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50"
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

                                    Gerar apostas
                                </button>
                            @endif
                            <a
    href="{{ route('closings.show', $closing) }}"
    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50"
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
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
        />

        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
        />
    </svg>

    Visualizar
</a>
                            <button
                                type="button"
                                wire:click="delete({{ $closing->id }})"
                                wire:confirm="Tem certeza que deseja excluir este fechamento?"
                                wire:loading.attr="disabled"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50"
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
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
                                    />
                                </svg>

                                Excluir
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                {{ $closings->links() }}
            </div>
        @else
            <div class="px-5 py-16 text-center sm:px-6">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
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
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l4.414 4.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                @if ($search !== '' || $status !== '' || $method !== '')
                    <h3 class="mt-5 text-lg font-bold text-slate-900">
                        Nenhum fechamento encontrado
                    </h3>

                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Não encontramos fechamentos correspondentes aos filtros aplicados.
                    </p>

                    <button
                        type="button"
                        wire:click="clearFilters"
                        class="mt-6 inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
                    >
                        Limpar filtros
                    </button>
                @else
                    <h3 class="mt-5 text-lg font-bold text-slate-900">
                        Você ainda não possui fechamentos
                    </h3>

                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Crie seu primeiro fechamento definindo um grupo-base e seus parâmetros.
                    </p>

                    <a
                        href="{{ route('closings.create') }}"
                        class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
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
                                d="M12 4v16m8-8H4"
                            />
                        </svg>

                        Criar primeiro fechamento
                    </a>
                @endif
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
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 0h-15a2 2 0 01-1.7-3l7.5-13.3a2 2 0 013.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                Nesta etapa, os fechamentos são apenas cadastrados e organizados.
                A geração de combinações, o cálculo de garantias e a criação automática de apostas serão implementados posteriormente.
            </p>
        </div>
    </section>
</div>
