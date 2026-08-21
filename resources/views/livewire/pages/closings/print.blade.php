<?php

use App\Models\Closing;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Impressão de Jogos'])] class extends Component
{
    public Closing $closing;

    public function mount(Closing $closing): void
    {
        // Garante que apenas o proprietário pode ver o fechamento para impressão
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        $this->closing = $closing;
    }

    /**
     * Retorna o nome amigável do método.
     */
    public function methodLabel(?string $method): string
    {
        return match ($method) {
            'integral' => 'Combinação integral',
            'reduced' => 'Fechamento reduzido',
            'wheel' => 'Sistema de roda',
            'random' => 'Geração aleatória',
            'balanced' => 'Geração equilibrada',
            default => ucfirst((string) $method),
        };
    }

    /**
     * Retorna todas as apostas associadas ordenadas.
     */
    public function with(): array
    {
        $bets = $this->closing->bets()->orderBy('id')->get();

        return [
            'bets' => $bets,
        ];
    }
};
?>

<div class="mx-auto max-w-5xl space-y-6">
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 10mm 10mm 10mm;
            }
            body {
                background: white !important;
                color: black !important;
            }
            /* Ocultar navegação, sidebar, topbar e botões de ação */
            nav, aside, header, .no-print, [role="navigation"] {
                display: none !important;
            }
            main {
                padding: 0 !important;
                margin: 0 !important;
                min-height: auto !important;
            }
            .lg\:pl-72 {
                padding-left: 0 !important;
            }
            .page-break-avoid {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
            .print-border {
                border: 1px solid #94a3b8 !important;
            }
            .print-bg-slate {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>

    {{-- Barra de Ações Superior (Apenas Tela) --}}
    <div class="no-print flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex items-center gap-3">
            <a
                href="{{ route('closings.show', $closing) }}"
                class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Voltar aos Detalhes
            </a>
            <div>
                <h1 class="text-lg font-bold text-slate-900 leading-tight">
                    Impressão: {{ $closing->name }}
                </h1>
                <p class="text-xs text-slate-500">
                    {{ $bets->count() }} aposta(s) pronta(s) para impressão
                </p>
            </div>
        </div>

        <button
            type="button"
            onclick="window.print()"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-indigo-600/20 transition hover:bg-indigo-700"
        >
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Imprimir Agora
        </button>
    </div>

    {{-- Folha / Conteúdo de Impressão --}}
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm print:border-none print:p-0 print:shadow-none space-y-6">
        
        {{-- Cabeçalho do Fechamento na Impressão --}}
        <header class="border-b-2 border-slate-800 pb-4">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-2xl font-black tracking-tight text-slate-900">
                        {{ $closing->name }}
                    </h2>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-600 mt-0.5">
                        Fechamento Lotofácil &bull; {{ $this->methodLabel($closing->method) }}
                    </p>
                </div>
                <div class="text-right">
                    @if ($closing->contest_number)
                        <span class="inline-block rounded-md border border-slate-800 bg-slate-100 px-2 py-0.5 text-xs font-black text-slate-900">
                            Concurso #{{ $closing->contest_number }}
                        </span>
                    @endif
                    @if ($closing->draw_date)
                        <p class="text-xs text-slate-600 mt-1 font-medium">
                            Sorteio: {{ $closing->draw_date->format('d/m/Y') }}
                        </p>
                    @endif
                </div>
            </div>

            {{-- Informações do Fechamento --}}
            <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs border-t border-slate-200 pt-2 text-slate-700">
                <div>
                    <span class="text-slate-500 font-medium">Total de Jogos:</span>
                    <strong class="font-bold text-slate-900">{{ $bets->count() }}</strong>
                </div>
                <div>
                    <span class="text-slate-500 font-medium">Dezenas por Jogo:</span>
                    <strong class="font-bold text-slate-900">{{ $closing->bet_size }}</strong>
                </div>
                <div class="col-span-2">
                    <span class="text-slate-500 font-medium">Grupo-base ({{ count($closing->base_numbers ?? []) }}):</span>
                    <span class="font-bold text-slate-900">
                        {{ implode(' - ', array_map(fn($n) => str_pad($n, 2, '0', STR_PAD_LEFT), $closing->base_numbers ?? [])) }}
                    </span>
                </div>
            </div>
        </header>

        @if ($bets->isEmpty())
            <div class="py-12 text-center text-slate-500">
                <p class="text-base font-semibold">Nenhuma aposta gerada para este fechamento.</p>
                <p class="text-xs mt-1">Gere as apostas no painel de detalhes antes de imprimir.</p>
            </div>
        @else
            {{-- Grid de Jogos (Lista + Simulação do Volante) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 print:grid-cols-2 print:gap-3">
                @foreach ($bets as $index => $bet)
                    @php
                        $betNumbers = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
                        $betNumbers = array_map('intval', $betNumbers);
                        sort($betNumbers);
                        $betNumbersSet = array_flip($betNumbers);
                    @endphp

                    <article class="page-break-avoid rounded-xl border border-slate-300 bg-slate-50/50 p-3.5 shadow-2xs print:border-slate-400 print:bg-white print:p-2.5">
                        {{-- Topo do Card de Aposta --}}
                        <div class="flex items-center justify-between border-b border-slate-200 pb-2 mb-2.5">
                            <span class="text-sm font-black text-slate-900">
                                Jogo #{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                                @if ($bet->name && $bet->name !== 'Aposta #' . $bet->id)
                                    <span class="text-xs font-normal text-slate-500">({{ $bet->name }})</span>
                                @endif
                            </span>
                            <span class="text-[11px] font-bold text-slate-600 bg-white border border-slate-200 px-2 py-0.5 rounded print:border-slate-400">
                                {{ count($betNumbers) }} dezenas
                            </span>
                        </div>

                        {{-- Lista de Dezenas do Jogo --}}
                        <div class="mb-3">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-1">
                                Dezenas Escolhidas:
                            </span>
                            <div class="flex flex-wrap gap-1">
                                @foreach ($betNumbers as $num)
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded bg-slate-900 text-white text-xs font-black shadow-2xs print:bg-slate-900 print:text-white">
                                        {{ str_pad($num, 2, '0', STR_PAD_LEFT) }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Simulação do Bilhete / Volante (5 dezenas por linha x 5 linhas = 25 dezenas) --}}
                        <div class="border-t border-slate-200 pt-2.5 print:pt-2">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
                                    Simulação do Volante (Marque com X)
                                </span>
                            </div>

                            <div class="inline-block rounded-lg border border-slate-300 bg-white p-2 shadow-2xs print:border-slate-400 print:shadow-none w-full">
                                <div class="grid grid-cols-5 gap-1.5 text-center">
                                    @for ($i = 1; $i <= 25; $i++)
                                        @php
                                            $isSelected = isset($betNumbersSet[$i]);
                                        @endphp
                                        <div @class([
                                            'relative flex h-8 flex-col items-center justify-center rounded border text-xs font-bold transition-all',
                                            'border-slate-900 bg-slate-900 text-white shadow-2xs print:border-black print:bg-black print:text-white' => $isSelected,
                                            'border-slate-200 bg-slate-50/80 text-slate-400 print:border-slate-300 print:bg-white print:text-slate-400' => ! $isSelected,
                                        ])>
                                            @if ($isSelected)
                                                <span class="text-[11px] font-black leading-none tracking-tight">
                                                    {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                                                </span>
                                                <span class="text-[9px] font-black text-rose-400 print:text-white leading-none mt-0.5">
                                                    [ X ]
                                                </span>
                                            @else
                                                <span class="text-[11px] font-semibold opacity-60 leading-none">
                                                    {{ str_pad($i, 2, '0', STR_PAD_LEFT) }}
                                                </span>
                                            @endif
                                        </div>
                                    @endfor
                                </div>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif

        {{-- Rodapé na Impressão --}}
        <footer class="border-t border-slate-300 pt-3 text-center text-[10px] text-slate-500">
            <p>Gerado em {{ now()->format('d/m/Y \à\s H:i') }} &bull; Lotofácil Analytics</p>
        </footer>
    </div>
</div>
