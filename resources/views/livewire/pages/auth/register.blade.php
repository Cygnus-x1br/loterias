<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <label for="name" class="block font-medium text-sm text-gray-200">{{ __('Name') }}</label>
            <input wire:model="name" id="name" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-red-400" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <label for="email" class="block font-medium text-sm text-gray-200">{{ __('Email') }}</label>
            <input wire:model="email" id="email" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <label for="password" class="block font-medium text-sm text-gray-200">{{ __('Password') }}</label>
            <input wire:model="password" id="password" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <label for="password_confirmation" class="block font-medium text-sm text-gray-200">{{ __('Confirm Password') }}</label>
            <input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full bg-slate-900/50 border border-white/10 text-white placeholder-gray-400 focus:border-fuchsia-500 focus:ring-fuchsia-500 rounded-md shadow-sm"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-red-400" />
        </div>

        <div class="flex items-center justify-between mt-6">
            <a class="text-sm text-fuchsia-300 hover:text-fuchsia-200 font-medium transition-colors" href="{{ route('login') }}" wire:navigate>
                Já possui uma conta? Faça login
            </a>

            <button type="submit" class="inline-flex items-center px-6 py-2 bg-fuchsia-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-fuchsia-500 focus:bg-fuchsia-500 active:bg-fuchsia-700 focus:outline-none focus:ring-2 focus:ring-fuchsia-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition ease-in-out duration-150 shadow-lg shadow-fuchsia-500/30">
                {{ __('Register') }}
            </button>
        </div>
    </form>
</div>
