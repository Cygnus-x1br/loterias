<?php

use App\Models\HistoricalResult;
use App\Services\HistoricalResultService;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Sorteios da Lotofácil'])] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $confirmingDeletion = false;

    public ?int $resultToDeleteId = null;

    public ?int $resultToDeleteContest = null;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->dateFrom !== '' || $this->dateTo !== '';
    }

    public function confirmDelete(int $id, int $contestNumber): void
    {
        $this->resultToDeleteId = $id;
        $this->resultToDeleteContest = $contestNumber;
        $this->confirmingDeletion = true;
    }

    public function cancelDelete(): void
    {
        $this->reset(['confirmingDeletion', 'resultToDeleteId', 'resultToDeleteContest']);
    }

    public function deleteResult(HistoricalResultService $service): void
    {
        if (! $this->resultToDeleteId) {
            return;
        }

        $result = HistoricalResult::find($this->resultToDeleteId);
        if ($result) {
            $contestNumber = $result->contest_number;
            $service->delete($result);
            session()->flash('success', "Concurso #{$contestNumber} excluído com sucesso.");
        }

        $this->cancelDelete();
        $this->resetPage();
    }

    public function with(): array
    {
        $query = HistoricalResult::query();

        if ($this->search !== '') {
            $search = trim($this->search);
            $query->where('contest_number', 'like', "%{$search}%");
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('draw_date', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('draw_date', '<=', $this->dateTo);
        }

        $results = $query->orderByDesc('contest_number')->paginate(15);

        return [
            'results' => $results,
            'totalCount' => HistoricalResult::count(),
            'filteredCount' => $query->count(),
        ];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ route('dashboard') }}" class="transition hover:text-indigo-600">
                    Dashboard
                </a>
                <span>/</span>
                <span class="font-medium text-slate-700">
                    Sorteios
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Histórico de Concursos
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Sorteios da Lotofácil
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Gerencie os resultados dos concursos da Lotofácil, cadastre novos sorteios manualmente ou edite os existentes.
            </p>
        </div>

        <div class="flex flex-col-reverse gap-2 sm:flex-row">
            <a
                href="{{ route('results.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Cadastrar Sorteio
            </a>
        </div>
    </section>

    @if (session('success'))
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-sm font-medium">
                {{ session('success') }}
            </p>
        </div>
    @endif

    {{-- Filtros e Busca --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-3 lg:grid-cols-4 items-end">
            <div class="md:col-span-1 lg:col-span-2">
                <label for="search" class="block text-sm font-semibold text-slate-700">
                    Número do Concurso
                </label>
                <div class="relative mt-2">
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m2.1-5.4a7.5 7.5 0 11-15 0 7.5 7.5 0 0115 0z" />
                    </svg>
                    <input
                        id="search"
                        type="search"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Ex.: 3200"
                        class="block w-full rounded-xl border-slate-300 py-2.5 pl-10 pr-4 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>
            </div>

            <div>
                <label for="dateFrom" class="block text-sm font-semibold text-slate-700">
                    Data Inicial
                </label>
                <input
                    id="dateFrom"
                    type="date"
                    wire:model.live="dateFrom"
                    class="mt-2 block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>

            <div>
                <label for="dateTo" class="block text-sm font-semibold text-slate-700">
                    Data Final
                </label>
                <input
                    id="dateTo"
                    type="date"
                    wire:model.live="dateTo"
                    class="mt-2 block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
            </div>
        </div>

        @if ($this->hasActiveFilters())
            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
                <span class="text-xs text-slate-500">
                    Filtros aplicados ({{ $filteredCount }} resultados encontrados)
                </span>
                <button
                    type="button"
                    wire:click="clearFilters"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-rose-600 hover:text-rose-700"
                >
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Limpar filtros
                </button>
            </div>
        @endif
    </section>

    {{-- Tabela de Concursos --}}
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:flex-row sm:items-center sm:px-6">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Resultados Registrados
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    Total de {{ $totalCount }} concursos no banco de dados.
                </p>
            </div>

            <div wire:loading class="text-sm font-medium text-indigo-600">
                Atualizando...
            </div>
        </div>

        @if ($results->count() > 0)
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-100">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                Concurso / Data
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                15 Dezenas Sorteadas
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
                                15 Acertos
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @foreach ($results as $item)
                            <tr wire:key="result-row-{{ $item->id }}" class="transition hover:bg-slate-50">
                                <td class="whitespace-nowrap px-6 py-4">
                                    <div class="font-bold text-slate-900">
                                        #{{ $item->contest_number }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        {{ $item->draw_date?->format('d/m/Y') ?? '—' }}
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="flex max-w-md flex-wrap gap-1">
                                        @foreach ($item->drawn_numbers ?? [] as $number)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-indigo-50 text-xs font-bold text-indigo-700">
                                                {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                    @if ($item->winners_15_hits !== null)
                                        <div>
                                            <span class="font-semibold text-slate-800">{{ $item->winners_15_hits }}</span> ganhador(es)
                                        </div>
                                        @if ($item->payout_15_hits)
                                            <div class="text-xs text-emerald-600 font-medium">
                                                R$ {{ number_format((float) $item->payout_15_hits, 2, ',', '.') }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-6 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        <a
                                            href="{{ route('results.edit', $item) }}"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-600 transition hover:bg-indigo-50"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                            Editar
                                        </a>

                                        <button
                                            type="button"
                                            wire:click="confirmDelete({{ $item->id }}, {{ $item->contest_number }})"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-50"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10" />
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

            {{-- Versão Mobile (Cards) --}}
            <div class="divide-y divide-slate-100 md:hidden">
                @foreach ($results as $item)
                    <article wire:key="result-card-{{ $item->id }}" class="space-y-3 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="font-bold text-slate-900">
                                    Concurso #{{ $item->contest_number }}
                                </h3>
                                <p class="mt-0.5 text-xs text-slate-500">
                                    Sorteio em {{ $item->draw_date?->format('d/m/Y') ?? '—' }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <a
                                    href="{{ route('results.edit', $item) }}"
                                    class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100"
                                    title="Editar"
                                >
                                    <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                    </svg>
                                </a>
                                <button
                                    type="button"
                                    wire:click="confirmDelete({{ $item->id }}, {{ $item->contest_number }})"
                                    class="rounded-lg p-1.5 text-slate-600 hover:bg-rose-50"
                                    title="Excluir"
                                >
                                    <svg class="h-4 w-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($item->drawn_numbers ?? [] as $number)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        @if ($item->winners_15_hits !== null)
                            <div class="flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                                <span>15 acertos: <strong>{{ $item->winners_15_hits }} ganhador(es)</strong></span>
                                @if ($item->payout_15_hits)
                                    <span class="font-bold text-emerald-600">R$ {{ number_format((float) $item->payout_15_hits, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="border-t border-slate-100 px-5 py-4 sm:px-6">
                {{ $results->links() }}
            </div>
        @else
            <div class="px-5 py-16 text-center sm:px-6">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>

                @if ($this->hasActiveFilters())
                    <h3 class="mt-5 text-lg font-bold text-slate-900">
                        Nenhum sorteio encontrado
                    </h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Não encontramos resultados correspondentes aos filtros aplicados.
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
                        Nenhum sorteio cadastrado
                    </h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                        Comece cadastrando o primeiro sorteio manualmente para alimentar as estatísticas.
                    </p>
                    <a
                        href="{{ route('results.create') }}"
                        class="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
                    >
                        Cadastrar primeiro sorteio
                    </a>
                @endif
            </div>
        @endif
    </section>

    {{-- Modal de Exclusão --}}
    @if ($confirmingDeletion)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/50" wire:click="cancelDelete"></div>

            <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3m-7 0h10" />
                        </svg>
                    </div>

                    <div>
                        <h3 class="text-base font-bold text-slate-900">
                            Excluir Concurso #{{ $resultToDeleteContest }}
                        </h3>
                        <p class="mt-1 text-sm leading-6 text-slate-500">
                            Tem certeza que deseja excluir permanentemente o resultado do concurso #{{ $resultToDeleteContest }}? As estatísticas e dados históricos serão recalculados.
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        wire:click="cancelDelete"
                        class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="deleteResult"
                        class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700"
                    >
                        Sim, excluir
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
