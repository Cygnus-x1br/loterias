<aside
    x-show="sidebarOpen || window.innerWidth >= 1024"
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="-translate-x-full"
    x-transition:enter-end="translate-x-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="translate-x-0"
    x-transition:leave-end="-translate-x-full"
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white lg:translate-x-0"
    style="display: none;"
>
    <div class="flex h-16 items-center justify-between border-b border-slate-200 px-6">
        <a
            href="{{ route('dashboard') }}"
            class="flex items-center gap-3"
        >
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
                <svg
                    class="h-6 w-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M4 7h16M4 12h16M4 17h16"
                    />
                </svg>
            </div>

            <div>
                <p class="text-sm font-extrabold tracking-tight text-slate-900">
                    Lotofácil
                </p>
                <p class="text-xs font-medium text-indigo-600">
                    Analytics
                </p>
            </div>
        </a>

        <button
            type="button"
            @click="sidebarOpen = false"
            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 lg:hidden"
            aria-label="Fechar menu"
        >
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <div class="flex-1 overflow-y-auto px-4 py-5">
        <nav class="space-y-7">
            <div>
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Visão geral
                </p>

                <a
                    href="{{ route('dashboard') }}"
                    class="flex items-center gap-3 rounded-xl bg-indigo-50 px-3 py-2.5 text-sm font-semibold text-indigo-700"
                >
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l9-9 9 9M5 10v10h14V10M9 20v-6h6v6" />
                    </svg>
                    Dashboard
                </a>
            </div>

            <div>
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Operações
                </p>

                <div class="space-y-1">
                    <a href="{{ route('bets.index') }}" class="sidebar-link">
                        <span>▦</span>
                        Apostas
                    </a>

                    <a href="{{  route('closings.index') }}" class="sidebar-link">
                        <span>◈</span>
                        Fechamentos
                    </a>

                    <a
                        href="{{ route('lotofacil.analysis') }}"
                        @class([
                            'sidebar-link',
                            'bg-indigo-50 !text-indigo-700 font-semibold' => request()->routeIs('lotofacil.analysis'),
                        ])
                    >
                        <span>∑</span>
                        Cálculos matemáticos
                    </a>

                    <a href="#" class="sidebar-link">
                        <span>◒</span>
                        Cobertura combinatória
                    </a>

                    <a href="#" class="sidebar-link">
                        <span>⌁</span>
                        Otimização
                    </a>

                    <a href="#" class="sidebar-link">
                        <span>◌</span>
                        Simulações
                    </a>
                </div>
            </div>

            <div>
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Dados e relatórios
                </p>

                <div class="space-y-1">
                    <a
                        href="{{ route('results.index') }}"
                        @class([
                            'sidebar-link',
                            'bg-indigo-50 !text-indigo-700 font-semibold' => request()->routeIs('results.*'),
                        ])
                    >
                        <span>◷</span>
                        Consultar resultados anteriores
                    </a>

                    <a href="#" class="sidebar-link">
                        <span>▤</span>
                        Relatórios
                    </a>
                </div>
            </div>

            <div>
                <p class="mb-3 px-3 text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                    Sistema
                </p>

                <div class="space-y-1">
                    <a href="#" class="sidebar-link">
                        <span>⚙</span>
                        Configurações
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <div class="border-t border-slate-200 p-4">
        <div class="rounded-2xl bg-slate-50 p-4">
            <div class="mb-2 flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-amber-400"></span>
                <span class="text-xs font-bold uppercase tracking-wide text-slate-500">
                    Ambiente demonstrativo
                </span>
            </div>

            <p class="text-xs leading-5 text-slate-500">
                Os dados exibidos ainda não estão conectados ao banco de dados.
            </p>
        </div>
    </div>
</aside>

<style>
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        border-radius: 0.75rem;
        padding: 0.625rem 0.75rem;
        color: rgb(71 85 105);
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 150ms ease;
    }

    .sidebar-link:hover {
        background-color: rgb(248 250 252);
        color: rgb(67 56 202);
    }

    .sidebar-link span {
        display: flex;
        width: 1.25rem;
        justify-content: center;
        color: rgb(100 116 139);
        font-size: 1rem;
    }

    .sidebar-link:hover span {
        color: rgb(79 70 229);
    }
</style>
