<div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center">
        <div>
            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Estatísticas & Análise Histórica
            </div>
            <h3 class="mt-2 text-xl font-extrabold tracking-tight text-slate-900">
                Estatísticas da Lotofácil
            </h3>
            <p class="text-sm text-slate-500">
                Métricas calculadas a partir de todos os resultados registrados no sistema.
            </p>
        </div>

        <button
            type="button"
            wire:click="recalculateRepeatedDraws"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:opacity-50"
        >
            <svg
                wire:loading
                wire:target="recalculateRepeatedDraws"
                class="h-3.5 w-3.5 animate-spin text-indigo-600"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            <svg
                wire:loading.remove
                wire:target="recalculateRepeatedDraws"
                class="h-3.5 w-3.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Recalcular Análises
        </button>
    </div>

    {{-- Seção: Análise de Repetição de 15 Dezenas --}}
    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-base font-bold text-slate-900">
                        Repetição de Resultados Anteriores (15 dezenas idênticas)
                    </h4>
                    <p class="mt-0.5 text-xs text-slate-500 leading-relaxed">
                        Avaliação de todos os concursos cadastrados para verificar se alguma sequência completa de 15 números já se repetiu na história da Lotofácil.
                    </p>
                </div>
            </div>

            @if ($repeatedDrawsAnalysis)
                <div class="shrink-0">
                    @if ($repeatedDrawsAnalysis['has_repeated'])
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-700 border border-rose-200">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            {{ $repeatedDrawsAnalysis['repeated_groups_count'] }} repetição(ões) encontrada(s)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 border border-emerald-200">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                            0 repetições de 15 dezenas
                        </span>
                    @endif
                </div>
            @endif
        </div>

        @if ($repeatedDrawsAnalysis)
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Concursos Analisados</span>
                    <p class="mt-1 text-xl font-extrabold text-slate-900">
                        {{ number_format($repeatedDrawsAnalysis['total_contests'], 0, ',', '.') }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Sequências Repetidas</span>
                    <p class="mt-1 text-xl font-extrabold {{ $repeatedDrawsAnalysis['has_repeated'] ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $repeatedDrawsAnalysis['repeated_groups_count'] }}
                    </p>
                </div>

                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Conclusão Estratégica</span>
                    <p class="mt-1 text-xs font-medium text-slate-700 leading-snug">
                        @if ($repeatedDrawsAnalysis['has_repeated'])
                            Houve repetição em sorteios passados. Avalie filtros com cautela.
                        @else
                            Nenhum sorteio de 15 dezenas se repetiu em {{ $repeatedDrawsAnalysis['total_contests'] }} concursos.
                        @endif
                    </p>
                </div>
            </div>

            @if ($repeatedDrawsAnalysis['has_repeated'])
                <div class="mt-4 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        Detalhes dos Sorteios com 15 Dezenas Repetidas:
                    </p>
                    @foreach ($repeatedDrawsAnalysis['repetitions'] as $rep)
                        <div class="rounded-xl border border-rose-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                <span class="text-xs font-bold text-rose-700">
                                    Ocorrido {{ $rep['total_occurrences'] }} vezes nos concursos:
                                </span>
                                <div class="flex flex-wrap gap-2 text-xs">
                                    @foreach ($rep['contests'] as $c)
                                        <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">
                                            #{{ $c['contest_number'] }} ({{ $c['draw_date'] ?? '—' }})
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1">
                                @foreach ($rep['drawn_numbers'] as $num)
                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-600 text-xs font-bold text-white shadow-sm">
                                        {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-emerald-100 bg-emerald-50/60 p-3.5 text-xs text-emerald-900 leading-relaxed">
                    <svg class="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" />
                    </svg>
                    <span>
                        <strong>Dica para Fechamentos:</strong> Como <strong>nunca houve repetição de todas as 15 dezenas</strong> em toda a história dos {{ $repeatedDrawsAnalysis['total_contests'] }} concursos avaliados, a exclusão de sequências completas já sorteadas pode ser uma estratégia válida para otimização de custo/combinações sem perda estatisticamente observada de coberturas passadas.
                    </span>
                </div>
            @endif
        @endif
    </div>

    {{-- Seção: Média de Score Histórica --}}
    @if ($averageScoreData && ($averageScoreData['total_contests'] ?? 0) > 0)
        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-5">
            <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-white shadow-md shadow-amber-500/20">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-base font-bold text-slate-900">
                            Média de Score dos Concursos (0 a 1.000 pts)
                        </h4>
                        <p class="mt-0.5 text-xs text-slate-500 leading-relaxed">
                            Média calculada sobre todos os {{ number_format($averageScoreData['total_contests'], 0, ',', '.') }} resultados históricos avaliados pelo algoritmo de pontuação da plataforma.
                        </p>
                    </div>
                </div>

                <div class="shrink-0">
                    <span @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold border',
                        'bg-emerald-50 text-emerald-700 border-emerald-200' => $averageScoreData['color'] === 'emerald',
                        'bg-amber-50 text-amber-700 border-amber-200' => $averageScoreData['color'] === 'amber',
                        'bg-orange-50 text-orange-700 border-orange-200' => $averageScoreData['color'] === 'orange',
                        'bg-rose-50 text-rose-700 border-rose-200' => $averageScoreData['color'] === 'rose',
                        'bg-slate-50 text-slate-700 border-slate-200' => $averageScoreData['color'] === 'slate',
                    ])>
                        <span @class([
                            'h-2 w-2 rounded-full',
                            'bg-emerald-500' => $averageScoreData['color'] === 'emerald',
                            'bg-amber-500' => $averageScoreData['color'] === 'amber',
                            'bg-orange-500' => $averageScoreData['color'] === 'orange',
                            'bg-rose-500' => $averageScoreData['color'] === 'rose',
                            'bg-slate-400' => $averageScoreData['color'] === 'slate',
                        ])></span>
                        Classificação: {{ $averageScoreData['classification'] }}
                    </span>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Média Geral de Score</span>
                    <p class="mt-1 text-2xl font-extrabold text-slate-900">
                        {{ number_format($averageScoreData['average_score'], 1, ',', '.') }}
                        <span class="text-xs font-normal text-slate-400">/ 1.000 pts</span>
                    </p>
                </div>

                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Faixa Histórica (Mín - Máx)</span>
                    <p class="mt-1 text-xl font-extrabold text-slate-800">
                        {{ $averageScoreData['min_score'] }} <span class="text-sm font-normal text-slate-400">a</span> {{ $averageScoreData['max_score'] }} <span class="text-xs font-normal text-slate-400">pts</span>
                    </p>
                </div>

                <div class="rounded-xl bg-white p-3.5 border border-slate-200 shadow-sm">
                    <span class="text-xs font-semibold text-slate-500">Critério & Eficiência</span>
                    <p class="mt-1 text-xs font-medium text-slate-700 leading-snug">
                        Sorteios oficiais pontuam em média {{ number_format($averageScoreData['average_score'], 0, ',', '.') }} pts. Jogos bem equilibrados devem buscar esta faixa.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- Último Concurso --}}
    @if ($lastContest)
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400">
                        Último Sorteio Cadastrado
                    </span>
                    <h4 class="mt-1 text-lg font-bold text-slate-900">
                        Concurso #{{ $lastContest['result']['contest_number'] }}
                        <span class="text-sm font-normal text-slate-500">
                            ({{ \Carbon\Carbon::parse($lastContest['result']['draw_date'])->format('d/m/Y') }})
                        </span>
                    </h4>
                    <p class="mt-0.5 text-xs text-slate-600">
                        Soma das dezenas: <strong class="text-slate-800">{{ $lastContest['sum'] }}</strong>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    {{-- Badges de contagem de temperatura no último sorteio --}}
                    <div class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs font-semibold text-amber-800 shadow-xs" title="Dezenas Quentes no último sorteio">
                        <svg class="h-3.5 w-3.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.527.82-1.124 1.93-1.64 3.12a20.08 20.08 0 01-1.393 2.748c-.5.845-.964 1.57-1.353 2.052a5.75 5.75 0 00-.737 1.258A6.002 6.002 0 0010 18a6.002 6.002 0 005.894-4.873c.07-.37.106-.75.106-1.127 0-1.197-.333-2.316-.913-3.268a15.733 15.733 0 00-1.89-2.544 19.86 19.86 0 00-.802-.835zM10 16a4 4 0 01-3.92-3.178c.036-.08.08-.16.13-.24.32-.51.72-1.17 1.18-1.95A18.09 18.09 0 008.66 8.01c.42-.98.88-1.87 1.34-2.58.3-.06.6.01.83.21.36.31.75.7 1.15 1.17.48.56.96 1.23 1.38 1.99.45.81.79 1.69.79 2.61A4.002 4.002 0 0110 16z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $lastContestTemperatures['hot_count'] }} Quentes</span>
                    </div>

                    <div class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-xs" title="Dezenas Neutras/Médias no último sorteio">
                        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v1.07A7.002 7.002 0 0116.93 11H18a1 1 0 110 2h-1.07A7.002 7.002 0 0111 18.93V20a1 1 0 11-2 0v-1.07A7.002 7.002 0 013.07 13H2a1 1 0 110-2h1.07A7.002 7.002 0 019 5.07V4a1 1 0 011-1zm0 4a5 5 0 100 10 5 5 0 000-10z" clip-rule="evenodd" />
                        </svg>
                        <span>{{ $lastContestTemperatures['neutral_count'] }} Neutras</span>
                    </div>

                    <div class="inline-flex items-center gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-800 shadow-xs" title="Dezenas Frias no último sorteio">
                        <svg class="h-3.5 w-3.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="2" x2="12" y2="22"></line>
                            <line x1="2" y1="12" x2="22" y2="12"></line>
                            <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                            <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                        </svg>
                        <span>{{ $lastContestTemperatures['cold_count'] }} Frias</span>
                    </div>

                    <button
                        wire:click="useLastResultNumbers"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Usar para Fechamento
                    </button>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($lastContest['result']['drawn_numbers'] as $number)
                    @php
                        $tempInfo = $numberTemperatures[$number] ?? ['temperature' => 'neutral', 'recent_count' => 0];
                        $temp = $tempInfo['temperature'] ?? 'neutral';
                    @endphp
                    <div
                        class="relative flex flex-col items-center justify-center rounded-xl bg-indigo-600 text-white shadow-sm px-2.5 py-1.5 min-w-[42px]"
                        title="Dezena {{ sprintf('%02d', $number) }}: {{ $temp === 'hot' ? 'Quente (🔥 mais frequente nos últimos 20 concursos)' : ($temp === 'cold' ? 'Fria (❄️ menos frequente/atrasada)' : 'Neutra (⚖️ frequência média)') }}"
                    >
                        <span class="text-sm font-extrabold leading-tight">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </span>

                        {{-- Ícone indicativo de temperatura sob o número --}}
                        <div class="mt-0.5 flex items-center justify-center">
                            @if ($temp === 'hot')
                                <svg class="h-3 w-3 text-amber-300 drop-shadow-xs" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.527.82-1.124 1.93-1.64 3.12a20.08 20.08 0 01-1.393 2.748c-.5.845-.964 1.57-1.353 2.052a5.75 5.75 0 00-.737 1.258A6.002 6.002 0 0010 18a6.002 6.002 0 005.894-4.873c.07-.37.106-.75.106-1.127 0-1.197-.333-2.316-.913-3.268a15.733 15.733 0 00-1.89-2.544 19.86 19.86 0 00-.802-.835zM10 16a4 4 0 01-3.92-3.178c.036-.08.08-.16.13-.24.32-.51.72-1.17 1.18-1.95A18.09 18.09 0 008.66 8.01c.42-.98.88-1.87 1.34-2.58.3-.06.6.01.83.21.36.31.75.7 1.15 1.17.48.56.96 1.23 1.38 1.99.45.81.79 1.69.79 2.61A4.002 4.002 0 0110 16z" clip-rule="evenodd" />
                                </svg>
                            @elseif ($temp === 'cold')
                                <svg class="h-3 w-3 text-sky-200 drop-shadow-xs" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="2" x2="12" y2="22"></line>
                                    <line x1="2" y1="12" x2="22" y2="12"></line>
                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                    <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                                </svg>
                            @else
                                <span class="h-1 w-2 rounded-full bg-indigo-200/80"></span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <p class="text-sm text-slate-500">Nenhum resultado histórico encontrado para exibir.</p>
    @endif

    {{-- Dezenas Mais e Menos Sorteadas --}}
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Mais Sorteadas --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h4 class="text-sm font-bold text-slate-900">
                Dezenas Mais Sorteadas (Top 10)
            </h4>
            <p class="mt-0.5 text-xs text-slate-500">
                Frequência de aparições nos sorteios
            </p>

            <div class="mt-4 grid grid-cols-5 gap-2">
                @foreach ($mostDrawnNumbers as $number => $frequency)
                    <div class="flex flex-col items-center rounded-xl bg-slate-50 p-2 border border-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-white {{ in_array($number, $lastContest['result']['drawn_numbers'] ?? []) ? 'bg-indigo-600' : 'bg-slate-700' }}">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="mt-1 text-[11px] font-semibold text-slate-600">
                            {{ $frequency }}x
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Menos Sorteadas --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h4 class="text-sm font-bold text-slate-900">
                Dezenas Menos Sorteadas (Top 10)
            </h4>
            <p class="mt-0.5 text-xs text-slate-500">
                Menores frequências registradas
            </p>

            <div class="mt-4 grid grid-cols-5 gap-2">
                @foreach ($leastDrawnNumbers as $number => $frequency)
                    <div class="flex flex-col items-center rounded-xl bg-slate-50 p-2 border border-slate-100">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-white {{ in_array($number, $lastContest['result']['drawn_numbers'] ?? []) ? 'bg-indigo-600' : 'bg-slate-500' }}">
                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                        </span>
                        <span class="mt-1 text-[11px] font-semibold text-slate-600">
                            {{ $frequency }}x
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Pares e Trios Mais Frequentes --}}
    <div class="grid gap-6 md:grid-cols-2">
        {{-- Pares --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h4 class="text-sm font-bold text-slate-900">
                Pares Mais Frequentes (Top 10)
            </h4>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($mostFrequentPairs as $pair => $frequency)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 border border-slate-100 text-xs">
                        <span class="font-bold text-slate-800">{{ $pair }}</span>
                        <span class="font-semibold text-indigo-600">{{ $frequency }}x</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Trios --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5">
            <h4 class="text-sm font-bold text-slate-900">
                Trios Mais Frequentes (Top 10)
            </h4>
            <div class="mt-3 grid grid-cols-2 gap-2">
                @foreach ($mostFrequentTrios as $trio => $frequency)
                    <div class="flex items-center justify-between rounded-xl bg-slate-50 px-3 py-2 border border-slate-100 text-xs">
                        <span class="font-bold text-slate-800">{{ $trio }}</span>
                        <span class="font-semibold text-indigo-600">{{ $frequency }}x</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
