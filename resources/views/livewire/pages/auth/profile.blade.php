<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Gerenciamento de Conta'])] class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="mx-auto max-w-5xl space-y-6">
    {{-- Cabeçalho da Página --}}
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
                    Gerenciamento de Conta
                </span>
            </div>

            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                Perfil & Segurança
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Gerenciamento de Conta
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Gerencie suas informações cadastrais, altere sua senha de acesso e configure a segurança da sua conta.
            </p>
        </div>
    </section>

    {{-- Seção: Informações de Perfil --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="max-w-2xl">
            <livewire:profile.update-profile-information-form />
        </div>
    </div>

    {{-- Seção: Alterar Senha --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="max-w-2xl">
            <livewire:profile.update-password-form />
        </div>
    </div>

    {{-- Seção: Excluir Conta --}}
    <div class="rounded-2xl border border-rose-100 bg-white p-6 shadow-sm">
        <div class="max-w-2xl">
            <livewire:profile.delete-user-form />
        </div>
    </div>
</div>
