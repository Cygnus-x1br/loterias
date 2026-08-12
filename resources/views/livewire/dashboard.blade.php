<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <div class="mb-3 inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Dados da sua conta
            </div>

            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                Dashboard
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Organize, analise e otimize seus jogos da Lotofácil utilizando métodos combinatórios e estatísticos.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a
                href="{{ route('bets.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14" />
                </svg>
                Gerar apostas
            </a>

            <a
                href="{{ route('closings.create') }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Novo fechamento
            </a>
        </div>
    </section>

    <section class="flex flex-col gap-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:flex-row sm:items-start">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 4h.01M10.3 3.7L2.8 17a2 2 0 001.7 3h15a2 2 0 001.7 3L13.7 3.7a2 2 0 00-3.4 0z" />
            </svg>
        </div>

        <div>
            <h2 class="text-sm font-bold text-amber-900">
                Aviso metodológico
            </h2>

            <p class="mt-1 text-sm leading-6 text-amber-800">
                Os métodos apresentados organizam combinações e calculam coberturas matemáticas.
                Eles não preveem os números sorteados nem garantem premiações.
            </p>
        </div>
    </section>

    <section>
        <div class="mb-4 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-900">
                    Visão geral
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Indicadores atualizados com os dados da sua conta.
                </p>
            </div>

            <button
                type="button"
                wire:click="loadDashboardData"
                class="hidden rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-700 sm:inline-flex"
            >
                Atualizar dados
            </button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($metrics as $metric)
                <x-metric-card
                    :label="$metric['label']"
                    :value="$metric['value']"
                    :description="$metric['description']"
                    :trend="$metric['trend']"
                    :icon="$metric['icon']"
                    :color="$metric['color']"
                />
            @endforeach
        </div>
    </section>

    <section>
        <div class="mb-4">
            <h2 class="text-base font-bold text-slate-900">
                Ações rápidas
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                Acesse os principais módulos da plataforma.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a
                href="{{ route('closings.create') }}"
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-xl text-indigo-600">
                        +
                    </div>

                    <span class="text-slate-300 transition group-hover:translate-x-1 group-hover:text-indigo-500">
                        →
                    </span>
                </div>

                <h3 class="mt-4 text-sm font-bold text-slate-800">
                    Criar fechamento
                </h3>

                <p class="mt-1 text-sm leading-5 text-slate-500">
                    Organize um novo grupo de apostas por método e orçamento.
                </p>
            </a>

            <a
                href="{{ route('bets.create') }}"
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-violet-200 hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-50 text-xl text-violet-600">
                        ⠿
                    </div>

                    <span class="text-slate-300 transition group-hover:translate-x-1 group-hover:text-violet-500">
                        →
                    </span>
                </div>

                <h3 class="mt-4 text-sm font-bold text-slate-800">
                    Gerar combinações
                </h3>

                <p class="mt-1 text-sm leading-5 text-slate-500">
                    Prepare novas apostas a partir dos parâmetros escolhidos.
                </p>
            </a>

            <a
                href="{{ route('closings.index') }}"
                class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-200 hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-xl text-emerald-600">
                        ∑
                    </div>

                    <span class="text-slate-300 transition group-hover:translate-x-1 group-hover:text-emerald-500">
                        →
                    </span>
                </div>

                <h3 class="mt-4 text-sm font-bold text-slate-800">
                    Analisar fechamentos
                </h3>

                <p class="mt-1 text-sm leading-5 text-slate-500">
                    Consulte os fechamentos já criados e suas apostas.
                </p>
            </a>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-5">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Distribuição por método
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Percentual dos seus fechamentos
                    </p>
                </div>

                @if (count($distribution) > 0)
                    <span class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                        Dados reais
                    </span>
                @endif
            </div>

            @if (count($distribution) > 0)
                <div class="mt-6 space-y-5">
                    @foreach ($distribution as $item)
                        <div>
                            <div class="mb-2 flex items-center justify-between gap-4">
                                <span class="text-sm font-medium text-slate-600">
                                    {{ $item['label'] }}
                                </span>

                                <span class="text-sm font-bold text-slate-800">
                                    {{ $item['value'] }}%
                                </span>
                            </div>

                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    class="{{ $item['color'] }} h-full rounded-full transition-all"
                                    style="width: {{ $item['value'] }}%"
                                ></div>
                            </div>

                            <p class="mt-1 text-xs text-slate-400">
                                {{ $item['count'] }} fechamento(s)
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-6 rounded-xl bg-slate-50 p-5 text-center">
                    <p class="text-sm font-semibold text-slate-700">
                        Nenhum fechamento encontrado
                    </p>

                    <p class="mt-1 text-sm leading-5 text-slate-500">
                        Crie seu primeiro fechamento para visualizar a distribuição por método.
                    </p>

                    <a
                        href="{{ route('closings.create') }}"
                        class="mt-4 inline-flex rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        Criar fechamento
                    </a>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-3">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Atividade recente
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Últimos registros da sua conta
                    </p>
                </div>

                <a
                    href="{{ route('closings.index') }}"
                    class="text-sm font-semibold text-indigo-600 hover:text-indigo-700"
                >
                    Ver tudo
                </a>
            </div>

            @if (count($activities) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left">
                        <thead class="bg-slate-50">
                            <tr class="text-xs font-bold uppercase tracking-wide text-slate-400">
                                <th class="px-5 py-3">Tipo</th>
                                <th class="px-5 py-3">Nome</th>
                                <th class="px-5 py-3">Data</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Ação</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            @foreach ($activities as $activity)
                                @php
                                    $statusClasses = [
                                        'emerald' => 'bg-emerald-50 text-emerald-700',
                                        'amber' => 'bg-amber-50 text-amber-700',
                                        'sky' => 'bg-sky-50 text-sky-700',
                                        'violet' => 'bg-violet-50 text-violet-700',
                                        'rose' => 'bg-rose-50 text-rose-700',
                                    ];
                                @endphp

                                <tr class="transition hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <span class="text-sm font-semibold text-slate-700">
                                            {{ $activity['type'] }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="text-sm text-slate-600">
                                            {{ $activity['name'] }}
                                        </span>
                                    </td>

                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">
                                        {{ $activity['date'] }}
                                    </td>

                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$activity['statusColor']] ?? 'bg-slate-100 text-slate-700' }}">
                                            {{ $activity['status'] }}
                                        </span>
                                    </td>

                                    <td class="px-5 py-4 text-right">
                                        <a
                                            href="{{ $activity['url'] }}"
                                            class="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
                                        >
                                            Visualizar
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-8 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-xl text-slate-400">
                        —
                    </div>

                    <h3 class="mt-4 text-sm font-bold text-slate-800">
                        Nenhuma atividade recente
                    </h3>

                    <p class="mx-auto mt-1 max-w-sm text-sm leading-5 text-slate-500">
                        Seus fechamentos e apostas aparecerão aqui assim que forem criados.
                    </p>

                    <a
                        href="{{ route('closings.create') }}"
                        class="mt-4 inline-flex rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                    >
                        Criar primeiro fechamento
                    </a>
                </div>
            @endif
        </div>
    </section>

    <section class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-indigo-600 shadow-sm">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <div>
                <h2 class="text-sm font-bold text-indigo-900">
                    Recursos analíticos em desenvolvimento
                </h2>

                <p class="mt-1 text-sm leading-6 text-indigo-800">
                    Os indicadores de cobertura média e cenários analisados serão calculados quando o módulo de análise combinatória estiver disponível.
                </p>
            </div>
        </div>
    </section>
</div>
