<header class="sticky top-0 z-30 h-16 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="flex h-full items-center justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="sidebarOpen = true"
                class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 lg:hidden"
                aria-label="Abrir menu"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>

            <div class="hidden items-center gap-2 text-sm text-slate-400 sm:flex">
                <span>Lotofácil Analytics</span>
                <span>/</span>
                <span class="font-semibold text-slate-700">
                    {{ $title ?? 'Dashboard' }}
                </span>
            </div>

            <span class="text-sm font-semibold text-slate-700 sm:hidden">
                {{ $title ?? 'Dashboard' }}
            </span>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <button
                type="button"
                class="relative rounded-xl p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                aria-label="Notificações"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>

                <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-rose-500 ring-2 ring-white"></span>
            </button>

            <div class="hidden h-7 w-px bg-slate-200 sm:block"></div>

            <div class="flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-semibold text-slate-800">
                        {{ auth()->user()->name ?? 'Usuário demonstrativo' }}
                    </p>
                    <p class="text-xs text-slate-400">
                        Conta principal
                    </p>
                </div>

                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>

                <div x-data>
                    <button
                        type="button"
                        @click="$dispatch('open-logout')"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="hidden rounded-lg p-2 text-slate-400 hover:bg-rose-50 hover:text-rose-600 sm:block"
                        title="Sair"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" />
                        </svg>
                    </button>
                    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                        @csrf
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
