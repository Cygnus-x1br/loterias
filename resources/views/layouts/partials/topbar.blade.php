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

            <!-- Menu do Usuário com Dropdown -->
            <div
                x-data="{ userMenuOpen: false }"
                @click.outside="userMenuOpen = false"
                @keydown.escape.window="userMenuOpen = false"
                class="relative"
            >
                <button
                    type="button"
                    @click="userMenuOpen = !userMenuOpen"
                    class="flex items-center gap-3 rounded-xl p-1.5 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-500/20"
                    aria-expanded="false"
                    aria-haspopup="true"
                >
                    <div class="hidden text-right sm:block">
                        <p class="text-sm font-semibold text-slate-800">
                            {{ auth()->user()->name ?? 'Usuário demonstrativo' }}
                        </p>
                        <p class="text-xs text-slate-400">
                            Conta principal
                        </p>
                    </div>

                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700 shadow-sm">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </div>

                    <svg
                        class="hidden h-4 w-4 text-slate-400 transition-transform duration-200 sm:block"
                        :class="{ 'rotate-180': userMenuOpen }"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <!-- Dropdown Menu -->
                <div
                    x-show="userMenuOpen"
                    x-transition:enter="transition ease-out duration-150"
                    x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                    x-transition:leave="transition ease-in duration-100"
                    x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                    x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                    class="absolute right-0 mt-2 w-64 origin-top-right rounded-2xl border border-slate-200 bg-white p-2 shadow-xl ring-1 ring-black/5 focus:outline-none z-50"
                    style="display: none;"
                >
                    <div class="border-b border-slate-100 px-3 py-2.5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Logado como</p>
                        <p class="truncate text-sm font-bold text-slate-800">{{ auth()->user()->name ?? 'Usuário demonstrativo' }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email ?? 'usuario@exemplo.com' }}</p>
                    </div>

                    <div class="py-1">
                        <a
                            href="{{ route('profile') }}"
                            class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span>Gerenciamento de Conta</span>
                        </a>

                        <a
                            href="{{ route('settings.index') }}"
                            class="flex items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-indigo-50 hover:text-indigo-700"
                        >
                            <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span>Configurações do Sistema</span>
                        </a>
                    </div>

                    <div class="border-t border-slate-100 pt-1">
                        <button
                            type="button"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-sm font-medium text-rose-600 transition hover:bg-rose-50"
                        >
                            <svg class="h-4 w-4 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1" />
                            </svg>
                            <span>Sair da Conta</span>
                        </button>
                    </div>
                </div>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</header>
