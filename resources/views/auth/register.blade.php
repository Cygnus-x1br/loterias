<x-guest-layout>
    <div class="mb-6 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-600/20">
            <svg
                class="h-7 w-7"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v16m8-8H4"
                />
            </svg>
        </div>

        <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">
            Criar sua conta
        </h1>

        <p class="mt-2 text-sm text-slate-500">
            Comece a organizar seus jogos da Lotofácil.
        </p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label
                for="name"
                :value="__('Nome')"
            />

            <x-text-input
                id="name"
                class="mt-1 block w-full"
                type="text"
                name="name"
                :value="old('name')"
                required
                autofocus
                autocomplete="name"
                placeholder="Digite seu nome"
            />

            <x-input-error
                :messages="$errors->get('name')"
                class="mt-2"
            />
        </div>

        <div>
            <x-input-label
                for="email"
                :value="__('Email')"
            />

            <x-text-input
                id="email"
                class="mt-1 block w-full"
                type="email"
                name="email"
                :value="old('email')"
                required
                autocomplete="username"
                placeholder="voce@exemplo.com"
            />

            <x-input-error
                :messages="$errors->get('email')"
                class="mt-2"
            />

            <p class="mt-1 text-xs text-slate-500">
                O email será usado para acessar sua conta.
            </p>
        </div>

        <div>
            <x-input-label
                for="password"
                :value="__('Senha')"
            />

            <x-text-input
                id="password"
                class="mt-1 block w-full"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Crie uma senha"
            />

            <x-input-error
                :messages="$errors->get('password')"
                class="mt-2"
            />
        </div>

        <div>
            <x-input-label
                for="password_confirmation"
                :value="__('Confirmar senha')"
            />

            <x-text-input
                id="password_confirmation"
                class="mt-1 block w-full"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repita sua senha"
            />

            <x-input-error
                :messages="$errors->get('password_confirmation')"
                class="mt-2"
            />
        </div>

        <button
            type="submit"
            class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            Criar conta
        </button>

        <p class="text-center text-sm text-slate-500">
            Já possui uma conta?
            <a
                href="{{ route('login') }}"
                class="font-semibold text-indigo-600 hover:text-indigo-700"
            >
                Entrar
            </a>
        </p>
    </form>
</x-guest-layout>
