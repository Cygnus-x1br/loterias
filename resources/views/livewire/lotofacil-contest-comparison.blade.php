<div class="mx-auto max-w-7xl space-y-6">
    <!-- Cabeçalho Principal -->
    <section class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Cálculos Matemáticos & Estatística Comparativa
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Comparativo de Concursos
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Avaliação comparativa e sequencial dos resultados da Lotofácil com matriz de 25 dezenas, classificação de temperatura e métricas completas por jogo.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Seletor de Concursos (10, 25, 50, 100) -->
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                @foreach ([10, 25, 50, 100] as $option)
                    <button
                        type="button"
                        wire:click="setLimit({{ $option }})"
                        @class([
                            'rounded-lg px-3.5 py-1.5 text-xs font-bold transition',
                            'bg-indigo-600 text-white shadow-sm' => $limit === $option,
                            'text-slate-600 hover:text-indigo-600 hover:bg-slate-50' => $limit !== $option,
                        ])
                    >
                        {{ $option }} jogos
                    </button>
                @endforeach
            </div>

            <button
                type="button"
                wire:click="recalculate"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:opacity-50"
            >
                <svg
                    wire:loading
                    wire:target="recalculate"
                    class="h-3.5 w-3.5 animate-spin text-indigo-600"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <svg
                    wire:loading.remove
                    wire:target="recalculate"
                    class="h-3.5 w-3.5 text-indigo-600"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Recalcular
            </button>
        </div>
    </section>

    <!-- Abas de Navegação entre Módulos de Análise -->
    <div class="border-b border-slate-200">
        <nav class="-mb-px flex space-x-6">
            <a
                href="{{ route('lotofacil.analysis') }}"
                class="border-b-2 border-transparent pb-3 text-sm font-medium text-slate-500 hover:border-slate-300 hover:text-slate-700"
            >
                Visão Estatística Geral
            </a>

            <a
                href="{{ route('lotofacil.contest_comparison') }}"
                class="border-b-2 border-indigo-600 pb-3 text-sm font-bold text-indigo-600"
            >
                Comparativo de Concursos (Últimos {{ $limit }})
            </a>
        </nav>
    </div>

    @if ($analysis['total_analyzed'] === 0)
        <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <h3 class="mt-4 text-base font-bold text-slate-900">Nenhum concurso cadastrado</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                Cadastre ou importe os resultados da Lotofácil para visualizar as análises comparativas e sequenciais.
            </p>
            <div class="mt-6 flex justify-center">
                <a
                    href="{{ route('results.create') }}"
                    class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    Cadastrar Sorteio
                </a>
            </div>
        </div>
    @else
        <!-- Card Resumo do Último Concurso Registrado -->
        @if ($analysis['last_contest'])
            @php
                $lc = $analysis['last_contest'];
            @endphp
            <section class="rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50/70 via-white to-slate-50 p-5 shadow-sm sm:p-6">
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-indigo-100/90 px-3 py-1 text-xs font-bold text-indigo-800">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                            Último Concurso Realizado
                        </div>

                        <h2 class="mt-2 text-2xl font-black text-slate-900">
                            Concurso #{{ $lc['contest_number'] }}
                            @if ($lc['draw_date'])
                                <span class="text-sm font-normal text-slate-500">({{ $lc['draw_date'] }})</span>
                            @endif
                        </h2>
                    </div>

                    <div class="flex items-center gap-2 text-xs text-slate-600">
                        <span class="rounded-xl bg-white px-3.5 py-2 font-bold shadow-sm border border-slate-200">
                            Grupo selecionado: <strong>{{ $analysis['total_analyzed'] }}</strong> concursos analisados
                        </span>
                    </div>
                </div>

                <!-- Dezenas do Último Concurso -->
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($lc['drawn_numbers'] as $num)
                        @php
                            $temp = $analysis['temperature_map'][$num]['temperature'] ?? 'neutral';
                            $badgeStyle = match($temp) {
                                'hot' => 'bg-rose-500 text-white shadow-rose-500/30 ring-2 ring-rose-300',
                                'cold' => 'bg-sky-500 text-white shadow-sky-500/30 ring-2 ring-sky-300',
                                default => 'bg-indigo-600 text-white shadow-indigo-600/30 ring-2 ring-indigo-300',
                            };
                        @endphp
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl font-bold text-sm shadow-sm {{ $badgeStyle }}" title="Dezena {{ sprintf('%02d', $num) }} ({{ ucfirst($temp) }})">
                            {{ sprintf('%02d', $num) }}
                        </span>
                    @endforeach
                </div>

                <!-- Métricas Completas do Último Concurso -->
                <div class="mt-5 grid grid-cols-2 gap-3 border-t border-indigo-100/60 pt-4 sm:grid-cols-4 lg:grid-cols-7">
                    <!-- Pares / Ímpares -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Pares / Ímpares</p>
                        <p class="mt-1 text-base font-bold text-slate-800">{{ $lc['evens'] }}P / {{ $lc['odds'] }}I</p>
                    </div>

                    <!-- Soma -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Soma</p>
                        <p class="mt-1 text-base font-bold text-slate-800">{{ $lc['sum'] }}</p>
                    </div>

                    <!-- Primos -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Primos</p>
                        <p class="mt-1 text-base font-bold text-slate-800">{{ $lc['primes'] }}</p>
                    </div>

                    <!-- Fibonacci -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Fibonacci</p>
                        <p class="mt-1 text-base font-bold text-slate-800">{{ $lc['fibonacci'] }}</p>
                    </div>

                    <!-- Moldura / Centro -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Moldura / Centro</p>
                        <p class="mt-1 text-base font-bold text-slate-800">{{ $lc['frame'] }}M / {{ $lc['center'] }}C</p>
                    </div>

                    <!-- Repetições -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Repetições</p>
                        <p class="mt-1 text-base font-bold text-indigo-700">
                            {{ $lc['repeated_from_previous'] ?? '—' }}
                        </p>
                    </div>

                    <!-- Score -->
                    <div class="rounded-xl border border-slate-100 bg-white/80 p-3 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Score</p>
                        <p class="mt-1 text-base font-black text-emerald-600">
                            {{ $lc['score'] ?? '—' }} <span class="text-xs font-medium text-slate-500">/ 1000</span>
                        </p>
                    </div>
                </div>
            </section>
        @endif

        <!-- Quadro de Médias do Período Selecionado -->
        @if (! empty($analysis['averages']))
            @php
                $avg = $analysis['averages'];
            @endphp
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">
                            Médias Estatísticas dos Últimos {{ $limit }} Concursos
                        </h2>
                        <p class="text-xs text-slate-500">
                            Comportamento médio dos sorteios nesta janela amostral.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1 rounded-md bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700">
                            🔥 Quentes: {{ count($analysis['hot_numbers']) }} dezenas
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700">
                            ⚖️ Neutras: {{ count($analysis['neutral_numbers']) }} dezenas
                        </span>
                        <span class="inline-flex items-center gap-1 rounded-md bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700">
                            ❄️ Frias: {{ count($analysis['cold_numbers']) }} dezenas
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:grid-cols-7">
                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Soma</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $avg['avg_sum'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 180 a 220</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Pares</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $avg['avg_evens'] }}P / {{ $avg['avg_odds'] }}I</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 7 a 8 pares</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Primos</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $avg['avg_primes'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 5 a 6</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Fibonacci</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $avg['avg_fibonacci'] }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 4 a 5</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Moldura</p>
                        <p class="mt-1 text-xl font-extrabold text-slate-800">{{ $avg['avg_frame'] }}M / {{ $avg['avg_center'] }}C</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 9 a 10 moldura</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Média Repetições</p>
                        <p class="mt-1 text-xl font-extrabold text-indigo-700">{{ $avg['avg_repeated'] ?? '—' }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Ideal: 8 a 10</p>
                    </div>

                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 p-3">
                        <p class="text-xs font-semibold uppercase text-slate-400">Score Médio</p>
                        <p class="mt-1 text-xl font-extrabold text-emerald-600">{{ $avg['avg_score'] ?? '—' }}</p>
                        <p class="mt-0.5 text-[11px] text-slate-500">Escala de 0 a 1000</p>
                    </div>
                </div>
            </section>
        @endif

        <!-- Tabela Matricial Comparativa com 25 Dezenas Alinhadas em Coluna -->
        <section class="space-y-3">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">
                        Matriz Comparativa dos Sorteios (Colunas 01 a 25)
                    </h2>
                    <p class="text-xs text-slate-500">
                        Visualização matricial das 25 dezenas e métricas comparativas lado a lado.
                    </p>
                </div>

                <!-- Legenda de Cores -->
                <div class="flex flex-wrap items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1">
                        <span class="h-3 w-3 rounded-full bg-rose-500"></span>
                        <strong>Quente</strong> (Frequência alta)
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="h-3 w-3 rounded-full bg-indigo-600"></span>
                        <strong>Neutra</strong> (Frequência média)
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="h-3 w-3 rounded-full bg-sky-500"></span>
                        <strong>Fria</strong> (Frequência baixa)
                    </span>
                    <span class="inline-flex items-center gap-1 text-slate-400">
                        <span class="h-3 w-3 rounded-full bg-slate-100 border border-slate-200"></span>
                        Não sorteada
                    </span>
                </div>
            </div>

            <!-- Tabela com Scroll Horizontal Suave -->
            <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full min-w-[1100px] border-collapse text-center text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-600">
                            <th class="sticky left-0 z-10 bg-slate-50 px-3 py-3 text-left shadow-[2px_0_4px_rgba(0,0,0,0.03)]">
                                Concurso
                            </th>

                            <!-- 25 Colunas das Dezenas -->
                            @for ($i = 1; $i <= 25; $i++)
                                <th class="w-7 px-1 py-3 text-slate-700 {{ in_array($i, [5, 10, 15, 20]) ? 'border-r border-slate-200' : '' }}">
                                    {{ sprintf('%02d', $i) }}
                                </th>
                            @endfor

                            <!-- Colunas de Métricas -->
                            <th class="border-l border-slate-200 px-2.5 py-3">P / I</th>
                            <th class="px-2.5 py-3">Soma</th>
                            <th class="px-2 py-3">Primos</th>
                            <th class="px-2 py-3">Fib.</th>
                            <th class="px-2.5 py-3">Mold.</th>
                            <th class="px-2 py-3">Rep.</th>
                            <th class="px-3 py-3">Score</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        @foreach ($analysis['contests'] as $contest)
                            <tr class="transition hover:bg-slate-50/70">
                                <!-- Concurso & Data (Sticky Column) -->
                                <td class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 py-2 text-left shadow-[2px_0_4px_rgba(0,0,0,0.03)] group-hover:bg-slate-50">
                                    <div class="font-bold text-slate-900">
                                        #{{ $contest['contest_number'] }}
                                    </div>
                                    @if ($contest['draw_date'])
                                        <div class="text-[10px] text-slate-400">
                                            {{ $contest['draw_date'] }}
                                        </div>
                                    @endif
                                </td>

                                <!-- 25 Colunas das Dezenas -->
                                @for ($num = 1; $num <= 25; $num++)
                                    @php
                                        $numData = $contest['numbers_grid'][$num] ?? null;
                                        $isDrawn = $numData['is_drawn'] ?? false;
                                        $temp = $numData['temperature'] ?? 'neutral';

                                        $circleClass = match($temp) {
                                            'hot' => 'bg-rose-500 text-white font-bold shadow-xs',
                                            'cold' => 'bg-sky-500 text-white font-bold shadow-xs',
                                            default => 'bg-indigo-600 text-white font-bold shadow-xs',
                                        };
                                    @endphp

                                    <td class="w-7 px-1 py-1.5 {{ in_array($num, [5, 10, 15, 20]) ? 'border-r border-slate-100' : '' }}">
                                        @if ($isDrawn)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-[11px] {{ $circleClass }}" title="Dezena {{ sprintf('%02d', $num) }} ({{ ucfirst($temp) }})">
                                                {{ sprintf('%02d', $num) }}
                                            </span>
                                        @else
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-50 text-[10px] text-slate-300">
                                                ·
                                            </span>
                                        @endif
                                    </td>
                                @endfor

                                <!-- Métricas -->
                                <td class="border-l border-slate-100 whitespace-nowrap px-2.5 py-2 font-semibold text-slate-700">
                                    {{ $contest['evens'] }}P / {{ $contest['odds'] }}I
                                </td>

                                <td class="whitespace-nowrap px-2.5 py-2 font-bold {{ $contest['sum'] >= 180 && $contest['sum'] <= 220 ? 'text-emerald-700' : 'text-slate-700' }}">
                                    {{ $contest['sum'] }}
                                </td>

                                <td class="whitespace-nowrap px-2 py-2 font-medium text-slate-700">
                                    {{ $contest['primes'] }}
                                </td>

                                <td class="whitespace-nowrap px-2 py-2 font-medium text-slate-700">
                                    {{ $contest['fibonacci'] }}
                                </td>

                                <td class="whitespace-nowrap px-2.5 py-2 font-medium text-slate-700">
                                    {{ $contest['frame'] }}M / {{ $contest['center'] }}C
                                </td>

                                <td class="whitespace-nowrap px-2 py-2 font-bold text-indigo-700">
                                    {{ $contest['repeated_from_previous'] ?? '—' }}
                                </td>

                                <td class="whitespace-nowrap px-3 py-2">
                                    @if ($contest['score'] !== null)
                                        <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold {{ $contest['score'] >= 600 ? 'bg-emerald-50 text-emerald-700' : ($contest['score'] >= 400 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                                            {{ $contest['score'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
