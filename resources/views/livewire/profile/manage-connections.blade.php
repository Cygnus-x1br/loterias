<?php

use App\Models\Connection;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $friendCode = '';
    public string $statusMessage = '';
    public string $errorMessage = '';

    public function addFriend()
    {
        $this->statusMessage = '';
        $this->errorMessage = '';

        $this->validate([
            'friendCode' => ['required', 'string', 'size:8'],
        ]);

        $friendCode = strtoupper(trim($this->friendCode));
        $user = Auth::user();

        if ($friendCode === $user->share_code) {
            $this->errorMessage = 'Você não pode se adicionar como amigo.';
            return;
        }

        $friend = User::where('share_code', $friendCode)->first();

        if (!$friend) {
            $this->errorMessage = 'Código não encontrado.';
            return;
        }

        // Check if connection already exists in either direction
        $existing = Connection::where(function ($query) use ($user, $friend) {
            $query->where('user_id', $user->id)->where('friend_id', $friend->id);
        })->orWhere(function ($query) use ($user, $friend) {
            $query->where('user_id', $friend->id)->where('friend_id', $user->id);
        })->first();

        if ($existing) {
            if ($existing->status === 'accepted') {
                $this->errorMessage = 'Vocês já estão conectados!';
            } elseif ($existing->status === 'pending') {
                $this->errorMessage = 'Já existe um pedido de conexão pendente.';
            } else {
                // If rejected, we can update it back to pending
                $existing->update([
                    'user_id' => $user->id,
                    'friend_id' => $friend->id,
                    'status' => 'pending'
                ]);
                $this->statusMessage = 'Pedido de conexão enviado novamente!';
            }
            $this->friendCode = '';
            return;
        }

        Connection::create([
            'user_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => 'pending',
        ]);

        $this->statusMessage = 'Pedido de conexão enviado com sucesso!';
        $this->friendCode = '';
    }

    public function acceptRequest(int $connectionId)
    {
        $connection = Connection::where('id', $connectionId)
            ->where('friend_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if ($connection) {
            $connection->update(['status' => 'accepted']);
            $this->statusMessage = 'Conexão aceita!';
        }
    }

    public function rejectRequest(int $connectionId)
    {
        $connection = Connection::where('id', $connectionId)
            ->where('friend_id', Auth::id())
            ->where('status', 'pending')
            ->first();

        if ($connection) {
            $connection->update(['status' => 'rejected']);
            $this->statusMessage = 'Conexão recusada.';
        }
    }

    public function removeConnection(int $connectionId)
    {
        $connection = Connection::where('id', $connectionId)
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhere('friend_id', Auth::id());
            })->first();

        if ($connection) {
            $connection->delete();
            $this->statusMessage = 'Conexão removida.';
        }
    }

    public function with(): array
    {
        $user = Auth::user();

        if (empty($user->share_code)) {
            $user->share_code = User::generateUniqueShareCode();
            $user->saveQuietly();
        }

        $pendingRequests = Connection::with('user')
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->get();

        $sentRequests = Connection::with('friend')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get();

        $activeConnections = Connection::with(['user', 'friend'])
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('friend_id', $user->id);
            })
            ->where('status', 'accepted')
            ->get()
            ->map(function ($conn) use ($user) {
                // Map the contact name and id based on who the logged-in user is
                $contact = $conn->user_id === $user->id ? $conn->friend : $conn->user;
                return [
                    'connection_id' => $conn->id,
                    'contact' => $contact
                ];
            });

        return [
            'user' => $user,
            'pendingRequests' => $pendingRequests,
            'sentRequests' => $sentRequests,
            'activeConnections' => $activeConnections,
        ];
    }
}; ?>

<section>
    <header>
        <div class="flex items-center gap-2 text-indigo-600 mb-1">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <h2 class="text-lg font-bold text-slate-900">
                {{ __('Minha Rede de Bolão') }}
            </h2>
        </div>

        <p class="text-sm text-slate-500">
            {{ __('Compartilhe seu código com outros apostadores para criar bolões. O compartilhamento funciona apenas com este ID, preservando seu e-mail e dados pessoais.') }}
        </p>
    </header>

    @if ($statusMessage)
        <div class="mt-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
            <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ $statusMessage }}
        </div>
    @endif
    @if ($errorMessage)
        <div class="mt-4 flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm font-medium text-rose-800">
            <svg class="h-5 w-5 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
            {{ $errorMessage }}
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Seu Código -->
        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-5 space-y-6">
            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-1">Seu ID / Código de Compartilhamento</h3>
                <p class="text-xs text-slate-500 mb-3">Passe este código para quem deseja adicionar você ao bolão:</p>
                
                <div class="flex items-center gap-2" x-data="{ copied: false }">
                    <span class="inline-flex items-center px-4 py-2.5 rounded-xl border border-indigo-200 bg-indigo-50 font-mono text-xl font-black tracking-widest text-indigo-700 shadow-inner">
                        {{ $user->share_code }}
                    </span>
                    <button 
                        type="button"
                        @click="navigator.clipboard.writeText('{{ $user->share_code }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="inline-flex items-center gap-1.5 px-3 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 hover:text-indigo-600 transition"
                    >
                        <svg x-show="!copied" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <svg x-show="copied" x-cloak class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span x-text="copied ? 'Copiado!' : 'Copiar'"></span>
                    </button>
                </div>
            </div>
            
            <hr class="border-slate-200">

            <form wire:submit="addFriend" class="space-y-4">
                <div>
                    <x-input-label for="friendCode" value="{{ __('Conectar com um Amigo (Adicionar por ID)') }}" class="text-sm font-semibold text-slate-700" />
                    <p class="text-xs text-slate-500 mb-2">Digite o código de 8 caracteres do amigo:</p>
                    <div class="flex gap-2">
                        <x-text-input 
                            wire:model="friendCode" 
                            id="friendCode" 
                            name="friendCode" 
                            type="text" 
                            class="block w-full uppercase font-mono tracking-wider rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" 
                            placeholder="EXEMPLO: ABCDEFGH" 
                            maxlength="8"
                            required 
                        />
                        <button
                            type="submit"
                            class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Conectar') }}
                        </button>
                    </div>
                    <x-input-error class="mt-2" :messages="$errors->get('friendCode')" />
                </div>
            </form>
        </div>

        <!-- Pedidos e Conexões -->
        <div class="space-y-5">
            @if($pendingRequests->isNotEmpty())
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-4">
                    <h3 class="text-sm font-bold text-amber-900 mb-2 flex items-center gap-2">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                        </span>
                        Convites Recebidos ({{ $pendingRequests->count() }})
                    </h3>
                    <ul class="divide-y divide-amber-200/70">
                        @foreach($pendingRequests as $req)
                            <li class="py-3 flex items-center justify-between gap-2 text-sm">
                                <div>
                                    <span class="font-semibold text-slate-800">{{ $req->user->name }}</span>
                                    <span class="text-slate-500 text-xs block font-mono">ID: {{ $req->user->share_code }}</span>
                                </div>
                                <div class="flex gap-2 shrink-0">
                                    <button wire:click="acceptRequest({{ $req->id }})" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-medium hover:bg-emerald-700 shadow-sm transition">
                                        Aceitar
                                    </button>
                                    <button wire:click="rejectRequest({{ $req->id }})" class="px-3 py-1.5 bg-white text-slate-700 border border-slate-200 rounded-lg text-xs font-medium hover:bg-slate-50 transition">
                                        Recusar
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($sentRequests->isNotEmpty())
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Convites Enviados Pendentes</h3>
                    <ul class="divide-y divide-slate-200">
                        @foreach($sentRequests as $req)
                            <li class="py-2 flex items-center justify-between text-xs text-slate-600">
                                <div>
                                    <span class="font-medium text-slate-700">{{ $req->friend->name }}</span>
                                    <span class="text-slate-400 font-mono ml-1">({{ $req->friend->share_code }})</span>
                                </div>
                                <span class="italic text-slate-400">Aguardando resposta...</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-white p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-slate-800">
                        Amigos Conectados ({{ $activeConnections->count() }})
                    </h3>
                </div>

                @if($activeConnections->isEmpty())
                    <p class="text-xs text-slate-500 bg-slate-50 p-4 rounded-xl border border-dashed border-slate-200 text-center">
                        Você ainda não possui amigos na sua rede.<br>Compartilhe seu código ou adicione o código de um amigo acima.
                    </p>
                @else
                    <ul class="divide-y divide-slate-100 max-h-60 overflow-y-auto pr-1">
                        @foreach($activeConnections as $conn)
                            <li class="py-3 flex items-center justify-between text-sm">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs">
                                        {{ substr($conn['contact']->name, 0, 2) }}
                                    </div>
                                    <div>
                                        <span class="font-semibold text-slate-800 block">{{ $conn['contact']->name }}</span>
                                        <span class="text-xs font-mono text-slate-400">ID: {{ $conn['contact']->share_code }}</span>
                                    </div>
                                </div>
                                <button 
                                    wire:click="removeConnection({{ $conn['connection_id'] }})" 
                                    wire:confirm="Tem certeza que deseja remover este amigo da sua rede de bolão?" 
                                    class="text-xs text-rose-600 hover:text-rose-800 font-medium px-2 py-1 rounded hover:bg-rose-50 transition"
                                >
                                    Remover
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</section>
