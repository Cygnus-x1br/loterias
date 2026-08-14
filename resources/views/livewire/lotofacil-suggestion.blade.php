<div class="mx-auto max-w-7xl space-y-6">
    {{-- Cabeçalho --}}
    <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
        <div>
            <div class="mb-2 inline-flex items-center gap-2 rounded-full border border-emerald-100 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                Geração de Fechamentos Inteligentes
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">
                Sugestão de Grupo Base — Lotofácil
            </h1>
            <p class="mt-1 text-sm text-slate-500">
                Gere um grupo estratégico de dezenas combinando o último sorteio com filtros de paridade, moldura/centro, soma e sequências.
            </p>
        </div>

        <div>
            <button
                type="button"
                wire:click="generateSuggestion"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-emerald-600/20 transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 disabled:opacity-50"
            >
                <svg
                    wire:loading
                    wire:target="generateSuggestion"
                    class="h-4 w-4 animate-spin text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg
                    wire:loading.remove
                    wire:target="generateSuggestion"
                    class="h-4 w-4 text-white"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Gerar Novo Grupo
            </button>
        </div>
    </div>

    {{-- Último Sorteio de Referência --}}
    @if ($lastContest)
        <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
            <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-6 items-center rounded-md bg-indigo-100 px-2 text-xs font-bold text-indigo-800">
                        Concurso #{{ $lastContest['contest_number'] }}
                    </span>
                    @if ($lastContest['draw_date'])
                        <span class="text-xs text-slate-500">({{ $lastContest['draw_date'] }})</span>
                    @endif
                    <span class="text-xs font-semibold text-slate-600">— Dezenas Sorteadas:</span>
                </div>
            </div>

            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($lastContest['drawn_numbers'] as $number)
                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white shadow-sm">
                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Painel de Configuração dos Parâmetros --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-slate-100 text-xs text-slate-700 font-bold">⚙</span>
            Parâmetros da Sugestão
        </h2>

        <div class="grid gap-6 md:grid-cols-2">
            {{-- Input Total de Dezenas Base --}}
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <label for="totalDezenasBase" class="text-sm font-semibold text-slate-700">
                        Tamanho do Grupo Base:
                    </label>
                    <span class="rounded-md bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-xs font-bold text-emerald-700">
                        {{ $totalDezenasBase }} dezenas
                    </span>
                </div>
                <input
                    type="range"
                    id="totalDezenasBase"
                    min="15"
                    max="25"
                    step="1"
                    wire:model.live="totalDezenasBase"
                    class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-emerald-600"
                >
                <div class="flex justify-between text-[11px] text-slate-400">
                    <span>15 (Jogo simples)</span>
                    <span>18 (Padrão)</span>
                    <span>25 (Todas)</span>
                </div>
                <p class="text-xs text-slate-500">
                    Quantidade de números que formarão a base para geração dos seus desdobramentos/fechamentos.
                </p>
            </div>

            {{-- Input Repetições do Último Sorteio --}}
            <div class="space-y-2">
                <div class="flex justify-between items-center">
                    <label for="repeticoesUltimoSorteio" class="text-sm font-semibold text-slate-700">
                        Repetições do Último Sorteio:
                    </label>
                    <span class="rounded-md bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-xs font-bold text-indigo-700">
                        {{ $repeticoesUltimoSorteio }} dezenas
                    </span>
                </div>
                <input
                    type="range"
                    id="repeticoesUltimoSorteio"
                    min="0"
                    max="{{ min(15, $totalDezenasBase) }}"
                    step="1"
                    wire:model.live="repeticoesUltimoSorteio"
                    class="w-full h-2 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                >
                <div class="flex justify-between text-[11px] text-slate-400">
                    <span>0</span>
                    <span>9 (Média histórica)</span>
                    <span>{{ min(15, $totalDezenasBase) }}</span>
                </div>
                <p class="text-xs text-slate-500">
                    Quantidade de dezenas do concurso anterior a serem incluídas (historicamente a média é de 8 a 10).
                </p>
            </div>
        </div>
    </div>

    {{-- Grupo Sugerido Exibido --}}
    <div class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/50 via-white to-slate-50 p-6 shadow-sm">
        <div class="flex flex-col justify-between gap-2 border-b border-emerald-100 pb-4 sm:flex-row sm:items-center">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Grupo Base Selecionado</span>
                <h3 class="text-xl font-black text-slate-900">
                    {{ count($suggestedGroup) }} Dezenas Sugeridas
                </h3>
            </div>

            <div class="flex flex-wrap items-center gap-3 text-xs">
                <span class="inline-flex items-center gap-1.5 text-slate-600">
                    <span class="h-3 w-3 rounded-full bg-indigo-600"></span> Repetidas do anterior ({{ $groupMetrics['repeated_count'] ?? 0 }})
                </span>
                <span class="inline-flex items-center gap-1.5 text-slate-600">
                    <span class="h-3 w-3 rounded-full bg-emerald-600"></span> Novas adicionadas ({{ $groupMetrics['new_count'] ?? 0 }})
                </span>
            </div>
        </div>

        {{-- Volante Visual com as Dezenas Sugeridas --}}
        <div class="my-6">
            <div class="grid grid-cols-5 gap-2.5 sm:gap-3 max-w-lg mx-auto">
                @for ($i = 1; $i <= 25; $i++)
                    @php
                        $isSelected = in_array($i, $suggestedGroup);
                        $isRepeated = in_array($i, $groupMetrics['repeated_numbers'] ?? []);
                        $isNew = in_array($i, $groupMetrics['new_numbers'] ?? []);
                    @endphp

                    <div
                        class="relative flex flex-col items-center justify-center rounded-xl p-2.5 sm:p-3 text-center transition font-bold {{ $isSelected ? ($isRepeated ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30 scale-105' : 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30 scale-105') : 'bg-slate-100 text-slate-300 border border-slate-200/60 opacity-60' }}"
                    >
                        <span class="text-base sm:text-lg">
                            {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        @if ($isSelected)
                            <span class="text-[9px] font-medium opacity-90">
                                {{ $isRepeated ? 'Repetida' : 'Nova' }}
                            </span>
                        @endif
                    </div>
                @endfor
            </div>
        </div>

        {{-- Lista Plana das Dezenas Formatadas --}}
        <div class="rounded-xl border border-slate-200 bg-white p-3.5 text-center">
            <span class="text-xs font-semibold text-slate-500 block mb-1">Dezenas em formato de texto para cópia:</span>
            <div class="font-mono text-sm font-bold tracking-wider text-slate-800 selection:bg-emerald-100">
                {{ implode(' - ', array_map(fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT), $suggestedGroup)) }}
            </div>
        </div>
    </div>

    {{-- Métricas e Resumo do Grupo Sugerido --}}
    @if (!empty($groupMetrics))
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Paridade --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-sky-100 text-sm font-bold text-sky-700">
                        ½
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500">Paridade</span>
                        <h4 class="text-lg font-black text-slate-900">
                            {{ $groupMetrics['evens'] }}P / {{ $groupMetrics['odds'] }}I
                        </h4>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-medium">
                        {{ $groupMetrics['evens'] }} Pares
                    </span>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-medium">
                        {{ $groupMetrics['odds'] }} Ímpares
                    </span>
                </div>
            </div>

            {{-- Moldura e Centro --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-sm font-bold text-emerald-700">
                        ⧉
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500">Moldura vs Centro</span>
                        <h4 class="text-lg font-black text-slate-900">
                            {{ $groupMetrics['frame'] }}M / {{ $groupMetrics['center'] }}C
                        </h4>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-medium">
                        {{ $groupMetrics['frame'] }} Moldura
                    </span>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs text-slate-600 font-medium">
                        {{ $groupMetrics['center'] }} Centro
                    </span>
                </div>
            </div>

            {{-- Soma do Grupo & Projeção --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-100 text-sm font-bold text-violet-700">
                        ∑
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500">Soma Total (Projeção 15)</span>
                        <h4 class="text-lg font-black text-slate-900">
                            {{ $groupMetrics['sum'] }} <span class="text-xs font-normal text-slate-400">(Proj: {{ $groupMetrics['projected_15_sum'] }})</span>
                        </h4>
                    </div>
                </div>
                <div class="mt-3">
                    <span class="inline-block rounded-md bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700">
                        {{ $groupMetrics['sum_status'] }}
                    </span>
                </div>
            </div>

            {{-- Repetições vs Novas --}}
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-sm font-bold text-amber-700">
                        ↻
                    </div>
                    <div>
                        <span class="text-xs font-semibold text-slate-500">Composição</span>
                        <h4 class="text-lg font-black text-slate-900">
                            {{ $groupMetrics['repeated_count'] }} Rep / {{ $groupMetrics['new_count'] }} Novas
                        </h4>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700 font-medium">
                        {{ round(($groupMetrics['repeated_count'] / max(1, $groupMetrics['total'])) * 100) }}% do Concurso Anterior
                    </span>
                </div>
            </div>
        </div>
    @endif
</div>
