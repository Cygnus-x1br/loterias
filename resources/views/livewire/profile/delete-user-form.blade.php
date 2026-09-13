<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ], [
            'password.required' => 'Informe sua senha para confirmar.',
            'password.current_password' => 'A senha informada está incorreta.',
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-5">
    <header>
        <div class="flex items-center gap-2 text-rose-600 mb-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            <h2 class="text-lg font-bold text-slate-900">
                Zona de Risco: Excluir Conta
            </h2>
        </div>

        <p class="text-sm text-slate-500">
            Depois que sua conta for excluída, todos os seus dados, históricos e fechamentos serão apagados permanentemente.
        </p>
    </header>

    <div>
        <button
            type="button"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
            class="inline-flex items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100 hover:border-rose-300"
        >
            Excluir minha conta
        </button>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">
            <h2 class="text-lg font-bold text-slate-900">
                Tem certeza de que deseja excluir sua conta?
            </h2>

            <p class="mt-2 text-sm text-slate-500">
                Esta ação é irreversível. Todos os seus fechamentos, apostas e simulações serão permanentemente removidos. Por favor, digite sua senha atual para confirmar a exclusão definitiva.
            </p>

            <div class="mt-5">
                <x-input-label for="password" value="Senha" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500"
                    placeholder="Digite sua senha para confirmar"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    x-on:click="$dispatch('close')"
                    class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700"
                >
                    Confirmar e Excluir
                </button>
            </div>
        </form>
    </x-modal>
</section>
