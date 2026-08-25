<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Cobertura Combinatória'])] class extends Component
{
    // Apenas view
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
                    Biblioteca Técnica
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Base de Conhecimento
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Cobertura Combinatória
            </h1>

            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500 sm:text-base">
                Entenda a base matemática por trás dos métodos de geração de apostas. As coberturas combinatórias, também conhecidas como desdobramentos ou "Wheeling Systems", são estratégias matemáticas para cobrir um conjunto maior de dezenas com o menor número possível de bilhetes.
            </p>
        </div>
        
        <a
            href="{{ route('closings.create') }}"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700"
        >
            Aplicar Conhecimento
        </a>
    </section>

    <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <!-- Combinação Integral -->
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Combinação Integral</h3>
                <p class="mt-2 flex-1 text-sm text-slate-500 leading-relaxed">
                    Gera todas as combinações matemáticas possíveis para o grupo de dezenas escolhido. É a forma 100% segura de garantir um prêmio máximo se as dezenas sorteadas estiverem no seu grupo-base. 
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Quando usar</h4>
                    <p class="mt-1 text-xs text-slate-600">Quando o orçamento não for problema (como em Bolões) e a certeza matemática for o foco primário. Possui custo altíssimo para grandes conjuntos.</p>
                </div>
            </div>
        </article>

        <!-- Fechamento Reduzido -->
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Fechamento Reduzido</h3>
                <p class="mt-2 flex-1 text-sm text-slate-500 leading-relaxed">
                    Aplica uma matriz condicional para remover redundâncias. Ele garante matematicamente um prêmio menor (ex: 14 pontos) se a condição for atendida (ex: acertar 15 entre 18), com uma drástica redução de custo.
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Quando usar</h4>
                    <p class="mt-1 text-xs text-slate-600">A melhor relação custo/benefício. Ideal para apostadores individuais ou grupos médios que querem maximizar probabilidades com orçamento controlado.</p>
                </div>
            </div>
        </article>

        <!-- Sistema de Roda -->
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Sistema de Roda (Wheeling)</h3>
                <p class="mt-2 flex-1 text-sm text-slate-500 leading-relaxed">
                    Baseia-se em fixar algumas dezenas centrais e rotacionar as restantes de forma cíclica pelas apostas. Ele garante que qualquer dezena do seu grupo-base vai aparecer em pelo menos um bilhete de forma homogênea.
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Quando usar</h4>
                    <p class="mt-1 text-xs text-slate-600">Excelente quando você tem grande convicção em algumas "dezenas fixas" (que estarão presentes em quase todos os volantes) mas dúvidas sobre as variáveis.</p>
                </div>
            </div>
        </article>

        <!-- Geração Equilibrada -->
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-sky-50 text-sky-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Geração Equilibrada</h3>
                <p class="mt-2 flex-1 text-sm text-slate-500 leading-relaxed">
                    Força algoritmicamente que todas as apostas geradas respeitem padrões reais que costumam ocorrer, como proporções exatas de pares e ímpares, faixas históricas de soma, e presença de primos e fibonacci.
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Quando usar</h4>
                    <p class="mt-1 text-xs text-slate-600">Aconselhado para "filtrar lixo matemático", removendo da cobertura aquelas combinações extremas (ex: tudo ímpar) que, embora possíveis, têm incidência quase nula nos sorteios.</p>
                </div>
            </div>
        </article>

        <!-- Geração Aleatória -->
        <article class="flex flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-600">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-900">Geração Aleatória (Surpresinha)</h3>
                <p class="mt-2 flex-1 text-sm text-slate-500 leading-relaxed">
                    Sorteia as dezenas de maneira puramente randômica usando geradores computacionais de entropia (PRNG). É a técnica menos estruturada, sem garantias matemáticas e vulnerável às anomalias estatísticas de curtos períodos.
                </p>
                <div class="mt-6 rounded-xl bg-slate-50 p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-slate-700">Quando usar</h4>
                    <p class="mt-1 text-xs text-slate-600">Apenas para jogos despretensiosos e rápidos, sem nenhum rigor técnico ou intenção de cerco (cobertura) ao grupo-base.</p>
                </div>
            </div>
        </article>
        
        <!-- Otimização -->
        <article class="flex flex-col rounded-2xl border border-indigo-200 bg-indigo-50 shadow-sm transition hover:shadow-md">
            <div class="flex flex-col flex-1 p-6">
                <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-white text-indigo-700 shadow-sm">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-indigo-900">Otimização (Novidade)</h3>
                <p class="mt-2 flex-1 text-sm text-indigo-700 leading-relaxed">
                    É a evolução das coberturas tradicionais. Funciona como um <strong>pós-processador</strong>: você gera as apostas por um método convencional e submete o resultado ao Motor de Otimização, que filtra, pontua (Score) e elimina redundâncias focando na variância.
                </p>
                <div class="mt-6 rounded-xl bg-white p-4">
                    <h4 class="text-xs font-bold uppercase tracking-wide text-indigo-900">Como usar</h4>
                    <p class="mt-1 text-xs text-indigo-700">Crie um fechamento e, na tela principal, clique em "Otimizar". Defina seu orçamento (quantas apostas quer cortar) e deixe o Score escolher as de maior potencial.</p>
                </div>
            </div>
        </article>
    </div>
</div>
