<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" method="POST" action="{{ route('login') }}">
        @csrf
        <!-- Email Address -->
        <div>
            <label for="email" class="block font-medium text-sm text-gray-200">{{ __('Email') }}</label>
            <input wire:model="form.email" id="email" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm" type="email" name="email" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-red-400" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-200">{{ __('Password') }}</label>
            <input wire:model="form.password" id="password" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-red-400" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4 flex items-center justify-between">
            <label for="remember" class="inline-flex items-center">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded bg-slate-900/50 border-white/10 text-fuchsia-500 shadow-sm focus:ring-fuchsia-500" name="remember">
                <span class="ms-2 text-sm text-gray-300">{{ __('Remember me') }}</span>
            </label>
            
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-300 hover:text-white rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-fuchsia-500 focus:ring-offset-slate-900" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="text-sm text-fuchsia-300 hover:text-fuchsia-200 font-medium transition-colors" href="{{ route('register') }}" wire:navigate>
                Não tem uma conta? Crie aqui
            </a>

            <button type="submit" class="inline-flex items-center px-6 py-2 bg-fuchsia-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-fuchsia-500 focus:bg-fuchsia-500 active:bg-fuchsia-700 focus:outline-none focus:ring-2 focus:ring-fuchsia-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition ease-in-out duration-150 shadow-lg shadow-fuchsia-500/30">
                {{ __('Log in') }}
            </button>
        </div>
    </form>
</div>
