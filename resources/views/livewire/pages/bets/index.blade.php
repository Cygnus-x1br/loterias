<?php

use App\Models\Bet;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Apostas'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public bool $confirmingAllBetsDeletion = false;

    public string $deleteAllConfirmationInput = '';

    /**
     * Palavra-chave exigida para confirmar a exclusão em massa.
     */
    protected string $deleteAllConfirmationKeyword = 'EXCLUIR';

    /**
     * Reinicia a paginação ao alterar a busca.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reinicia a paginação ao alterar o status.
     */
    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Limpa os filtros aplicados.
     */
    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'status',
        ]);

        $this->resetPage();
    }

    /**
     * Exclui uma aposta pertencente ao usuário autenticado.
     */
    public function delete(int $betId): void
    {
        $bet = Bet::query()
            ->where('id', $betId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $bet) {
            session()->flash(
                'error',
                'Aposta não encontrada ou você não tem permissão para excluí-la.'
            );

            return;
        }

        $bet->delete();

        session()->flash(
            'success',
            'Aposta excluída com sucesso.'
        );
    }

    /**
     * Indica se algum filtro de busca ou status está ativo.
     */
    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->status !== '';
    }

    /**
     * Retorna o nome amigável do status de filtro selecionado.
     */
    public function statusFilterLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Ativas',
            'inactive' => 'Inativas',
            'checked' => 'Conferidas',
            default => $status,
        };
    }

    /**
     * Query das apostas do usuário autenticado, respeitando
     * os filtros de busca e status atualmente aplicados.
     */
    protected function filteredBetsQuery()
    {
        return Bet::query()
            ->where('user_id', Auth::id())
            ->when(
                $this->search !== '',
                function ($query): void {
                    $search = trim($this->search);

                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('method', 'like', "%{$search}%")
                            ->orWhere('source', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                $this->status !== '',
                fn ($query) => $query->where('status', $this->status)
            );
    }

    /**
     * Abre o modal de confirmação para exclusão em massa,
     * considerando os filtros atualmente aplicados.
     */
    public function confirmAllBetsDeletion(): void
    {
        $this->deleteAllConfirmationInput = '';
        $this->resetErrorBag('deleteAllConfirmationInput');
        $this->confirmingAllBetsDeletion = true;
    }

    /**
     * Fecha o modal sem executar a exclusão em massa.
     */
    public function cancelAllBetsDeletion(): void
    {
        $this->reset([
            'confirmingAllBetsDeletion',
            'deleteAllConfirmationInput',
        ]);

        $this->resetErrorBag('deleteAllConfirmationInput');
    }

    /**
     * Exclui as apostas do usuário autenticado que correspondem
     * aos filtros atualmente aplicados (ou todas, se nenhum
     * filtro estiver ativo).
     *
     * Exige que o usuário digite a palavra-chave de confirmação,
     * dado o impacto irreversível desta ação.
     */
    public function deleteAllBets(): void
    {
        $confirmationInput = mb_strtoupper(trim($this->deleteAllConfirmationInput));

        if ($confirmationInput !== $this->deleteAllConfirmationKeyword) {
            $this->addError(
                'deleteAllConfirmationInput',
                'Digite "'.$this->deleteAllConfirmationKeyword.'" para confirmar a exclusão.'
            );

            return;
        }

        $deletedCount = $this->filteredBetsQuery()->count();

        $this->filteredBetsQuery()->delete();

        session()->flash(
            'success',
            $deletedCount === 1
                ? '1 aposta excluída com sucesso.'
                : "{$deletedCount} apostas excluídas com sucesso."
        );

        $this->cancelAllBetsDeletion();
        $this->resetPage();
    }

    /**
     * Consulta paginada das apostas do usuário autenticado.
     */
    public function with(): array
    {
        $bets = $this->filteredBetsQuery()
            ->latest()
            ->paginate(10);

        return [
            'bets' => $bets,
            'filteredBetsCount' => $this->filteredBetsQuery()->count(),
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

                <span class="font-medium text-slate-700">
                    Apostas
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Controle de apostas
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Minhas apostas
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Consulte e gerencie as apostas cadastradas na sua conta.
            </p>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row">
            @if ($filteredBetsCount > 0)
                <button
                    type="button"
                    wire:click="confirmAllBetsDeletion"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 py-2.5 text-sm font-semibold text-rose-600 shadow-sm transition hover:bg-rose-50"
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
                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
                        />
                    </svg>

                    {{ $this->hasActiveFilters() ? 'Excluir apostas filtradas' : 'Excluir todas as apostas' }}
                </button>
            @endif

            <a
                href="{{ route('bets.create') }}"
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

                Nova aposta
            </a>
        </div>
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
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex-1">
                <label
                    for="search"
                    class="block text-sm font-semibold text-slate-700"
                >
                    Buscar apostas
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
                        placeholder="Pesquise por nome, método ou origem"
                        class="block w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
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
                    <option value="active">Ativas</option>
                    <option value="inactive">Inativas</option>
                    <option value="checked">Conferidas</option>
                </select>
            </div>

            @if ($search !== '' || $status !== '')
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
                    Apostas cadastradas
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Gerencie suas seleções de dezenas.
                </p>
            </div>

            <div wire:loading class="text-sm font-medium text-indigo-600">
                Atualizando...
            </div>
        </div>

        @if ($bets->count() > 0)
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Aposta
                            </th>

                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Dezenas
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
                        @foreach ($bets as $bet)
                            <tr wire:key="bet-row-{{ $bet->id }}" class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-semibold text-slate-900">
                                        {{ $bet->name ?: 'Aposta #' . $bet->id }}
                                    </div>

                                    <div class="mt-1 text-xs text-slate-500">
                                        #{{ $bet->id }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex max-w-xs flex-wrap gap-1.5">
                                        @foreach ($bet->numbers ?? [] as $number)
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    <span class="text-sm font-medium text-slate-700">
                                        {{ match ($bet->method) {
                                            'manual' => 'Manual',
                                            'integral' => 'Integral',
                                            'reduced' => 'Reduzido',
                                            'wheel' => 'Sistema de roda',
                                            'random' => 'Aleatório',
                                            'balanced' => 'Equilibrado',
                                            default => ucfirst((string) $bet->method),
                                        } }}
                                    </span>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4">
                                    @if ($bet->status === 'active')
                                        <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            Ativa
                                        </span>
                                    @elseif ($bet->status === 'checked')
                                        <span class="rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                            Conferida
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                            {{ ucfirst((string) $bet->status) }}
                                        </span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">
                                    {{ $bet->created_at?->format('d/m/Y H:i') }}
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <button
                                        type="button"
                                        wire:click="delete({{ $bet->id }})"
                                        wire:confirm="Tem certeza que deseja excluir esta aposta?"
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
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
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
                @foreach ($bets as $bet)
                    <article
                        wire:key="bet-card-{{ $bet->id }}"
                        class="space-y-4 p-5"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-900">
                                    {{ $bet->name ?: 'Aposta #' . $bet->id }}
                                </h3>

                                <p class="mt-1 text-xs text-slate-500">
                                    Cadastrada em {{ $bet->created_at?->format('d/m/Y H:i') }}
                                </p>
                            </div>

                            @if ($bet->status === 'active')
                                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                    Ativa
                                </span>
                            @elseif ($bet->status === 'checked')
                                <span class="shrink-0 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                    Conferida
                                </span>
                            @else
                                <span class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    {{ ucfirst((string) $bet->status) }}
                                </span>
                            @endif
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-400">
                                Dezenas
                            </p>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($bet->numbers ?? [] as $number)
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-between border-t border-slate-100 pt-4">
                            <div class="text-sm text-slate-500">
                                Método:
                                <span class="font-semibold text-slate-700">
                                    {{ match ($bet->method) {
                                        'manual' => 'Manual',
                                        'integral' => 'Integral',
                                        'reduced' => 'Reduzido',
                                        'wheel' => 'Sistema de roda',
                                        'random' => 'Aleatório',
                                        'balanced' => 'Equilibrado',
                                        default => ucfirst((string) $bet->method),
                                    } }}
                                </span>
                            </div>

                            <button
                                type="button"
                                wire:click="delete({{ $bet->id }})"
                                wire:confirm="Tem certeza que deseja excluir esta aposta?"
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
                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10"
                                    />
                                </svg>

                                Excluir
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                {{ $bets->links() }}
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

                @if ($search !== '' || $status !== '')
                    <h3 class="mt-5 text-lg font-bold text-slate-900">
                        Nenhuma aposta encontrada
                    </h3>

                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Não encontramos apostas correspondentes aos filtros aplicados.
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
                        Você ainda não possui apostas
                    </h3>

                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Cadastre sua primeira aposta selecionando 15 dezenas entre 1 e 25.
                    </p>

                    <a
                        href="{{ route('bets.create') }}"
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

                        Cadastrar primeira aposta
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
                    d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 0L13.7 3.7a2 2 0 00-3.4 0z"
                />
            </svg>

            <p class="text-sm leading-6 text-amber-800">
                Nesta etapa, a tela apenas gerencia as apostas cadastradas.
                A conferência de resultados e os cálculos matemáticos serão implementados posteriormente.
            </p>
        </div>
    </section>

    @if ($confirmingAllBetsDeletion)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="all-bets-deletion-title"
        >
            <div
                class="fixed inset-0 bg-slate-900/50"
                wire:click="cancelAllBetsDeletion"
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
                                d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7-3L13.7 3.7a2 2 0 00-3.4 0z"
                            />
                        </svg>
                    </div>

                    <div>
                        <h3
                            id="all-bets-deletion-title"
                            class="text-base font-bold text-slate-900"
                        >
                            {{ $this->hasActiveFilters() ? 'Excluir apostas filtradas' : 'Excluir todas as apostas' }}
                        </h3>

                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            Esta ação excluirá permanentemente
                            <strong class="text-slate-700">
                                {{ $filteredBetsCount }}
                                {{ $filteredBetsCount === 1 ? 'aposta' : 'apostas' }}
                            </strong>
                            @if ($this->hasActiveFilters())
                                correspondentes aos filtros aplicados atualmente, incluindo apostas vinculadas a fechamentos.
                            @else
                                da sua conta, incluindo apostas vinculadas a fechamentos.
                            @endif
                            Esta ação não pode ser desfeita.
                        </p>
                    </div>
                </div>

                @if ($this->hasActiveFilters())
                    <div class="mt-3 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
                        <span class="font-semibold text-slate-700">Filtros aplicados:</span>
                        @if ($search !== '')
                            busca "{{ $search }}"
                        @endif
                        @if ($search !== '' && $status !== '')
                            &middot;
                        @endif
                        @if ($status !== '')
                            status "{{ $this->statusFilterLabel($status) }}"
                        @endif
                    </div>
                @endif

                <div class="mt-5">
                    <label
                        for="deleteAllConfirmationInput"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Para confirmar, digite <strong>EXCLUIR</strong> abaixo
                    </label>

                    <input
                        id="deleteAllConfirmationInput"
                        type="text"
                        wire:model="deleteAllConfirmationInput"
                        placeholder="EXCLUIR"
                        autocomplete="off"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                    >

                    @error('deleteAllConfirmationInput')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        wire:click="cancelAllBetsDeletion"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        wire:click="deleteAllBets"
                        wire:loading.attr="disabled"
                        wire:target="deleteAllBets"
                        class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-rose-600/20 transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ $this->hasActiveFilters() ? 'Excluir apostas filtradas' : 'Excluir todas as apostas' }}
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
