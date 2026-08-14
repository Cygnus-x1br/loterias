<?php

use App\Models\Closing;
use App\Services\Betting\ClosingGenerator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Fechamentos'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $method = '';

    public bool $confirmingClosingDeletion = false;

    public ?int $closingToDelete = null;

    public string $closingToDeleteName = '';

    public int $closingToDeleteBetsCount = 0;

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
     * Abre o modal de confirmação de exclusão de um fechamento.
     */
    public function confirmClosingDeletion(int $closingId): void
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

        $this->closingToDelete = $closing->id;
        $this->closingToDeleteName = $closing->name;
        $this->closingToDeleteBetsCount = $closing->bets()->count();
        $this->confirmingClosingDeletion = true;
    }

    /**
     * Fecha o modal de confirmação sem executar nenhuma exclusão.
     */
    public function cancelClosingDeletion(): void
    {
        $this->reset([
            'confirmingClosingDeletion',
            'closingToDelete',
            'closingToDeleteName',
            'closingToDeleteBetsCount',
        ]);
    }

    /**
     * Exclui somente um fechamento pertencente ao usuário autenticado.
     *
     * Quando $deleteBets é verdadeiro, as apostas vinculadas também
     * são excluídas. Caso contrário, elas são apenas desvinculadas
     * (comportamento padrão definido pela migration, via nullOnDelete).
     */
    public function deleteClosing(bool $deleteBets = false): void
    {
        $closing = Closing::query()
            ->where('id', $this->closingToDelete)
            ->where('user_id', Auth::id())
            ->first();

        if (! $closing) {
            session()->flash(
                'error',
                'Fechamento não encontrado ou você não tem permissão para excluí-lo.'
            );

            $this->cancelClosingDeletion();

            return;
        }

        if ($deleteBets) {
            $closing->bets()->delete();
        }

        $closing->delete();

        session()->flash(
            'success',
            $deleteBets
                ? 'Fechamento e apostas vinculadas excluídos com sucesso.'
                : 'Fechamento excluído com sucesso. As apostas vinculadas foram mantidas e desvinculadas.'
        );

        $this->cancelClosingDeletion();
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
            'placed' => 'Apostado',
            'checked' => 'Conferido',
            'failed' => 'Falhou',
            default => ucfirst((string) $status),
        };
    }

    /**
     * Indica se o fechamento pode ser executado nesta etapa.
     */
    public function canGenerate(Closing $closing): bool
    {
        return $closing->status === 'draft'
            && in_array($closing->method, ClosingGenerator::implementedMethods(), true);
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
     * Gera as apostas de um fechamento.
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

        if (! $this->canGenerate($closing)) {
            session()->flash(
                'error',
                'Este método de fechamento ainda não possui geração implementada.'
            );

            return;
        }

        try {
            $createdBets = app(ClosingGenerator::class)->generate($closing);

            session()->flash(
                'success',
                "{$createdBets} aposta(s) gerada(s) com sucesso."
            );
        } catch (\InvalidArgumentException|\LogicException $exception) {
            session()->flash('error', $exception->getMessage());
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
                    <option value="integral">Combinação integral</option>
                    <option value="reduced">Fechamento reduzido</option>
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
                    <option value="draft">Rascunho</option>
                    <option value="processing">Em processamento</option>
                    <option value="completed">Concluído</option>
                    <option value="placed">Apostado</option>
                    <option value="checked">Conferido</option>
                    <option value="failed">Falhou</option>
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
                    Gerencie seus planejamentos de jogos.
                </p>
            </div>

            <div wire:loading class="text-sm font-medium text-indigo-600">
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
                                wire:key="closing-{{ $closing->id }}"
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
                                    <div class="flex max-w-xs flex-wrap gap-1"> {{-- Ajustado aqui --}}
                                        @foreach ($closing->base_numbers ?? [] as $number)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-50 text-xs font-bold text-indigo-700"> {{-- Ajustado aqui --}}
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                    {{ $this->methodLabel($closing->method) }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        match ($closing->status) {
                                            'draft' => 'bg-amber-50 text-amber-700',
                                            'processing' => 'bg-sky-50 text-sky-700',
                                            'completed' => 'bg-indigo-50 text-indigo-700',
                                            'placed' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                            'checked' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                            'failed' => 'bg-rose-50 text-rose-700',
                                            default => 'bg-slate-100 text-slate-700',
                                        },
                                    ])>
                                        {{ $this->statusLabel($closing->status) }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                    {{ $closing->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        @if ($this->canGenerate($closing))
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
                                            wire:click="confirmClosingDeletion({{ $closing->id }})"
                                            class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
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
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Mobile --}}
            <div class="grid divide-y divide-slate-100 md:hidden">
                @foreach ($closings as $closing)
                    <article
                        wire:key="closing-mobile-{{ $closing->id }}"
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

                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                match ($closing->status) {
                                    'draft' => 'bg-amber-50 text-amber-700',
                                    'processing' => 'bg-sky-50 text-sky-700',
                                    'completed' => 'bg-indigo-50 text-indigo-700',
                                    'placed' => 'bg-amber-100 text-amber-800 border border-amber-200',
                                    'checked' => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                    'failed' => 'bg-rose-50 text-rose-700',
                                    default => 'bg-slate-100 text-slate-700',
                                },
                            ])>
                                {{ $this->statusLabel($closing->status) }}
                            </span>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                Grupo-base
                            </p>

                            <div class="flex flex-wrap gap-1"> {{-- Ajustado aqui --}}
                                @foreach ($closing->base_numbers ?? [] as $number)
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700"> {{-- Ajustado aqui --}}
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                            <div class="text-sm text-slate-500">
                                Método:
                                <span class="font-semibold text-slate-700">
                                    {{ $this->methodLabel($closing->method) }}
                                </span>
                            </div>
                        </div>

                        <div class="flex flex-wrap justify-end gap-2 border-t border-slate-100 pt-4">
                            @if ($this->canGenerate($closing))
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
                                wire:click="confirmClosingDeletion({{ $closing->id }})"
                                class="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
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
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 3h-15a2 2 0 01-1.7-3l7.5-13.3a2 2 0 013.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                Nesta etapa, os fechamentos são apenas cadastrados e organizados.
                A geração de combinações, o cálculo de garantias e a criação automática de apostas serão implementados posteriormente.
            </p>
        </div>
    </section>

    @if ($confirmingClosingDeletion)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="closing-deletion-title"
        >
            <div
                class="fixed inset-0 bg-slate-900/50"
                wire:click="cancelClosingDeletion"
            ></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
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
                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3
                            id="closing-deletion-title"
                            class="text-base font-bold text-slate-900"
                        >
                            Excluir fechamento
                        </h3>

                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            Tem certeza que deseja excluir
                            <strong class="text-slate-700">{{ $closingToDeleteName }}</strong>?
                            Esta ação não pode ser desfeita.
                        </p>
                    </div>
                </div>

                @if ($closingToDeleteBetsCount > 0)
                    <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm leading-6 text-amber-800">
                            Este fechamento possui
                            <strong>{{ $closingToDeleteBetsCount }}</strong>
                            {{ $closingToDeleteBetsCount === 1 ? 'aposta vinculada' : 'apostas vinculadas' }}.
                            Escolha se deseja excluí-las também ou apenas desvinculá-las deste fechamento.
                        </p>
                    </div>
                @endif

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="cancelClosingDeletion"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancelar
                    </button>

                    @if ($closingToDeleteBetsCount > 0)
                        <button
                            type="button"
                            wire:click="deleteClosing(false)"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Excluir apenas o fechamento
                        </button>

                        <button
                            type="button"
                            wire:click="deleteClosing(true)"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Excluir fechamento e apostas
                        </button>
                    @else
                        <button
                            type="button"
                            wire:click="deleteClosing(false)"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            Excluir fechamento
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
