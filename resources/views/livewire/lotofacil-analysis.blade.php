<div class="mx-auto max-w-7xl space-y-6">
    {{-- Cabeçalho Principal --}}
    <section class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Cálculos Matemáticos & Estatística Avançada
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Análises da Lotofácil
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Estudo probabilístico detalhado sobre repetições consecutivas, somas, moldura, centro, paridade e sequências.
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="recalculate"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:opacity-50"
            >
                <svg
                    wire:loading
                    wire:target="recalculate"
                    class="h-4 w-4 animate-spin text-indigo-600"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg
                    wire:loading.remove
                    wire:target="recalculate"
                    class="h-4 w-4 text-indigo-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Recalcular Métricas
            </button>
        </div>
    </section>

    @if ($totalContests === 0)
        <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-xl text-slate-400">
                ◷
            </div>
            <h3 class="mt-4 text-base font-bold text-slate-800">
                Nenhum sorteio cadastrado
            </h3>
            <p class="mx-auto mt-1 max-w-md text-sm text-slate-500">
                Cadastre os resultados históricos ou importe a planilha Lotofácil para visualizar as análises estatísticas completas.
            </p>
            <a
                href="{{ route('results.create') }}"
                class="mt-4 inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
            >
                Cadastrar Sorteio
            </a>
        </div>
    @else
        {{-- Card Resumo do Último Sorteio --}}
        @if ($lastContest)
            <section class="rounded-2xl border border-indigo-100 bg-gradient-to-r from-indigo-50/70 via-white to-slate-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-indigo-100/80 px-2.5 py-0.5 text-xs font-bold text-indigo-800">
                            Último Concurso Registrado
                        </div>
                        <h2 class="mt-1 text-xl font-black text-slate-900">
                            Concurso #{{ $lastContest['contest_number'] }}
                            @if ($lastContest['draw_date'])
                                <span class="text-sm font-normal text-slate-500">({{ $lastContest['draw_date'] }})</span>
                            @endif
                        </h2>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="rounded-lg bg-white px-3 py-1.5 font-bold shadow-sm border border-slate-200">
                            Base: <strong>{{ number_format($totalContests, 0, ',', '.') }}</strong> concursos analisados
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($lastContest['drawn_numbers'] as $number)
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-sm font-bold text-white shadow-sm shadow-indigo-600/30">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </span>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 1. Repetição de Dezenas do Sorteio Anterior --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20 font-bold">
                        ↻
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            1. Repetição de Dezenas do Último Sorteio
                        </h2>
                        <p class="text-xs text-slate-500">
                            Frequência e médias de repetição de números entre sorteios consecutivos (concurso atual vs anterior).
                        </p>
                    </div>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 border border-indigo-100">
                    Média Histórica: {{ $consecutiveRepetitionAnalysis['historical_average'] ?? 0 }} dezenas
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Repetições no Último Sorteio</span>
                    <p class="mt-1 text-2xl font-black text-indigo-600">
                        {{ $consecutiveRepetitionAnalysis['last_draw_repetitions_count'] ?? 0 }} dezenas
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @forelse ($consecutiveRepetitionAnalysis['last_draw_repeated_numbers'] ?? [] as $repNum)
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-md bg-indigo-100 text-[11px] font-bold text-indigo-800">
                                {{ str_pad($repNum, 2, '0', STR_PAD_LEFT) }}
                            </span>
                        @empty
                            <span class="text-xs text-slate-400">Nenhuma</span>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Faixa Mais Comum</span>
                    <p class="mt-1 text-2xl font-black text-emerald-600">
                        {{ $consecutiveRepetitionAnalysis['most_common_range'] ?? '8 a 10' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600">
                        Ocorre em <strong>{{ $consecutiveRepetitionAnalysis['most_common_range_percentage'] ?? 0 }}%</strong> de todos os concursos da história.
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Média Geral de Repetições</span>
                    <p class="mt-1 text-2xl font-black text-slate-900">
                        {{ $consecutiveRepetitionAnalysis['historical_average'] ?? 0 }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Em torno de 9 dezenas do sorteio anterior se repetem no próximo.
                    </p>
                </div>
            </div>

            {{-- Tabela de Distribuição de Repetições --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    Distribuição Histórica de Repetições Consecutivas
                </h3>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 md:grid-cols-7">
                    @foreach ($consecutiveRepetitionAnalysis['distribution'] ?? [] as $item)
                        <div class="rounded-xl border border-slate-200 bg-white p-2.5 text-center {{ in_array($item['repeated_count'], [8, 9, 10]) ? 'ring-2 ring-indigo-500/30 bg-indigo-50/30' : '' }}">
                            <span class="text-xs font-bold text-slate-700">{{ $item['repeated_count'] }} dezenas</span>
                            <p class="mt-0.5 text-base font-extrabold text-slate-900">{{ $item['percentage'] }}%</p>
                            <span class="text-[10px] text-slate-400">{{ $item['frequency'] }} sorteios</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 2. Soma das Dezenas Sorteadas --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-600 text-white shadow-md shadow-violet-600/20 font-bold">
                        ∑
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            2. Soma das Dezenas Sorteadas
                        </h2>
                        <p class="text-xs text-slate-500">
                            Soma total dos 15 números sorteados em cada concurso.
                        </p>
                    </div>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700 border border-violet-100">
                    Faixa Ideal: {{ $sumAnalysis['most_common_range'] ?? '180 a 220' }}
                </span>
            </div>

            <div class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Soma no Último Sorteio</span>
                    <p class="mt-1 text-2xl font-black text-violet-600">
                        {{ $sumAnalysis['last_draw_sum'] ?? 0 }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ ($sumAnalysis['last_draw_sum'] >= 180 && $sumAnalysis['last_draw_sum'] <= 220) ? '✓ Dentro da faixa padrão' : '⚠ Fora da faixa mais comum' }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Faixa Mais Frequente</span>
                    <p class="mt-1 text-2xl font-black text-emerald-600">
                        {{ $sumAnalysis['most_common_range'] ?? '180 a 220' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-600">
                        Representa <strong>{{ $sumAnalysis['most_common_range_percentage'] ?? 0 }}%</strong> dos sorteios
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Média Histórica</span>
                    <p class="mt-1 text-2xl font-black text-slate-900">
                        {{ $sumAnalysis['average_sum'] ?? 0 }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Centro equilibrado da curva
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Mínima e Máxima</span>
                    <p class="mt-1 text-xl font-bold text-slate-800">
                        {{ $sumAnalysis['min_sum'] ?? 0 }} <span class="text-slate-400 text-sm">a</span> {{ $sumAnalysis['max_sum'] ?? 0 }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Extremos históricos registrados
                    </p>
                </div>
            </div>

            {{-- Barras de Distribuição das Faixas de Soma --}}
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">
                    Frequência por Faixas de Soma
                </h3>
                <div class="space-y-2">
                    @foreach ($sumAnalysis['ranges_distribution'] ?? [] as $range)
                        <div>
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-700">{{ $range['label'] }}</span>
                                <span class="text-slate-900 font-bold">{{ $range['percentage'] }}% ({{ $range['count'] }} concursos)</span>
                            </div>
                            <div class="h-2.5 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="h-full rounded-full {{ in_array($range['label'], ['180 a 199', '200 a 219']) ? 'bg-violet-600' : 'bg-slate-400' }}"
                                    style="width: {{ $range['percentage'] }}%"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- 3 e 4: Pares/Ímpares & Moldura/Centro em Grade --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- 3. Proporção de Pares e Ímpares --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-600 text-white shadow-md shadow-sky-600/20 font-bold">
                            ½
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                3. Dezenas Pares e Ímpares
                            </h2>
                            <p class="text-xs text-slate-500">
                                Proporção de equilíbrio par/ímpar
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-sky-100 bg-sky-50/60 p-4">
                    <span class="text-xs font-bold text-sky-900 uppercase">Último Sorteio Registrado</span>
                    <div class="mt-2 flex items-center justify-between">
                        <div>
                            <p class="text-xl font-extrabold text-sky-950">
                                {{ $evenOddAnalysis['last_draw_pattern'] ?? '—' }}
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5">
                                {{ $evenOddAnalysis['last_draw_evens'] }} pares / {{ $evenOddAnalysis['last_draw_odds'] }} ímpares
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <span class="rounded-lg bg-sky-200/80 px-2.5 py-1 text-xs font-bold text-sky-900">
                                {{ $evenOddAnalysis['last_draw_evens'] }}P
                            </span>
                            <span class="rounded-lg bg-sky-200/80 px-2.5 py-1 text-xs font-bold text-sky-900">
                                {{ $evenOddAnalysis['last_draw_odds'] }}I
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Padrões Mais Frequentes da História
                    </h3>
                    <div class="space-y-2.5">
                        @foreach (array_slice($evenOddAnalysis['patterns'] ?? [], 0, 5) as $idx => $p)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2.5 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-200 font-bold text-[10px] text-slate-700">
                                        {{ $idx + 1 }}
                                    </span>
                                    <span class="font-bold text-slate-800">{{ $p['pattern'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-sky-700">{{ $p['percentage'] }}%</span>
                                    <span class="text-[11px] text-slate-400 ml-1">({{ $p['frequency'] }}x)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- 4. Moldura e Centro --}}
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20 font-bold">
                            ⧉
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">
                                4. Moldura e Centro
                            </h2>
                            <p class="text-xs text-slate-500">
                                16 dezenas na moldura vs 9 no centro
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-4">
                    <span class="text-xs font-bold text-emerald-900 uppercase">Último Sorteio Registrado</span>
                    <div class="mt-2 flex items-center justify-between">
                        <div>
                            <p class="text-xl font-extrabold text-emerald-950">
                                {{ $frameCenterAnalysis['last_draw_pattern'] ?? '—' }}
                            </p>
                            <p class="text-xs text-slate-600 mt-0.5">
                                {{ $frameCenterAnalysis['last_draw_frame'] }} na moldura / {{ $frameCenterAnalysis['last_draw_center'] }} no centro
                            </p>
                        </div>
                        <div class="flex gap-1.5">
                            <span class="rounded-lg bg-emerald-200/80 px-2.5 py-1 text-xs font-bold text-emerald-900">
                                {{ $frameCenterAnalysis['last_draw_frame'] }}M
                            </span>
                            <span class="rounded-lg bg-emerald-200/80 px-2.5 py-1 text-xs font-bold text-emerald-900">
                                {{ $frameCenterAnalysis['last_draw_center'] }}C
                            </span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Padrões Mais Frequentes da História
                    </h3>
                    <div class="space-y-2.5">
                        @foreach (array_slice($frameCenterAnalysis['patterns'] ?? [], 0, 5) as $idx => $p)
                            <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2.5 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="flex h-5 w-5 items-center justify-center rounded-full bg-slate-200 font-bold text-[10px] text-slate-700">
                                        {{ $idx + 1 }}
                                    </span>
                                    <span class="font-bold text-slate-800">{{ $p['pattern'] }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-bold text-emerald-700">{{ $p['percentage'] }}%</span>
                                    <span class="text-[11px] text-slate-400 ml-1">({{ $p['frequency'] }}x)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>

        {{-- 5. Sequências de Dezenas Consecutivas --}}
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
            <div class="flex flex-col justify-between gap-2 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/20 font-bold">
                        ⋯
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">
                            5. Sequências de Dezenas Consecutivas
                        </h2>
                        <p class="text-xs text-slate-500">
                            Presença e frequência de blocos sequenciais (2, 3 e 4+ dezenas seguidas) nos sorteios.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Cards de Ocorrência por Tamanho de Sequência --}}
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Sorteios com Sequência de 2+</span>
                    <p class="mt-1 text-2xl font-black text-amber-600">
                        {{ $consecutiveSequencesAnalysis['summary']['seq2_percentage'] ?? 0 }}%
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $consecutiveSequencesAnalysis['summary']['seq2_count'] ?? 0 }} sorteios (praticamente 100%)
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Sorteios com Sequência de 3+</span>
                    <p class="mt-1 text-2xl font-black text-amber-600">
                        {{ $consecutiveSequencesAnalysis['summary']['seq3_percentage'] ?? 0 }}%
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $consecutiveSequencesAnalysis['summary']['seq3_count'] ?? 0 }} sorteios continham trincas consecutivas
                    </p>
                </div>

                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                    <span class="text-xs font-semibold text-slate-500">Sorteios com Sequência de 4+</span>
                    <p class="mt-1 text-2xl font-black text-amber-600">
                        {{ $consecutiveSequencesAnalysis['summary']['seq4_percentage'] ?? 0 }}%
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ $consecutiveSequencesAnalysis['summary']['seq4_count'] ?? 0 }} sorteios continham 4 ou mais seguidas
                    </p>
                </div>
            </div>

            {{-- Listas das Sequências Mais Frequentes --}}
            <div class="grid gap-4 sm:grid-cols-3">
                {{-- Pares Consecutivos --}}
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Pares Consecutivos (Top)
                    </h3>
                    <div class="space-y-2">
                        @foreach ($consecutiveSequencesAnalysis['top_pairs_consecutive'] ?? [] as $seq => $freq)
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs">
                                <span class="font-bold text-slate-800">{{ $seq }}</span>
                                <span class="font-semibold text-amber-600">{{ $freq }}x</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Trios Consecutivos --}}
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Trios Consecutivos (Top)
                    </h3>
                    <div class="space-y-2">
                        @foreach ($consecutiveSequencesAnalysis['top_trios_consecutive'] ?? [] as $seq => $freq)
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs">
                                <span class="font-bold text-slate-800">{{ $seq }}</span>
                                <span class="font-semibold text-amber-600">{{ $freq }}x</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Quadras Consecutivas --}}
                <div class="rounded-xl border border-slate-200 bg-white p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3">
                        Quadras Consecutivas (Top)
                    </h3>
                    <div class="space-y-2">
                        @foreach ($consecutiveSequencesAnalysis['top_quads_consecutive'] ?? [] as $seq => $freq)
                            <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-xs">
                                <span class="font-bold text-slate-800">{{ $seq }}</span>
                                <span class="font-semibold text-amber-600">{{ $freq }}x</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>
