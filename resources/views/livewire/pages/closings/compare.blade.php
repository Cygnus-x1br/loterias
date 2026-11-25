<?php

use App\Services\Betting\ClosingComparisonService;
use App\Services\Betting\ResultClassification;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Comparar métodos'])] class extends Component
{
    /** @var array<int, int> */
    public array $baseNumbers = [];

    public int $betsBudget = 10;

    /** @var array<int, string> */
    public array $methods = ['random', 'balanced', 'balanced_spread', 'reduced'];

    public int $seed = 0;

    public ?string $error = null;

    /** @var array<string, mixed>|null */
    public ?array $comparison = null;

    public function mount(): void
    {
        $this->seed = random_int(1, 999999);
    }

    public function toggleNumber(int $number): void
    {
        if ($number < 1 || $number > 25) {
            return;
        }

        $this->baseNumbers = in_array($number, $this->baseNumbers, true)
            ? array_values(array_diff($this->baseNumbers, [$number]))
            : [...$this->baseNumbers, $number];

        sort($this->baseNumbers);
    }

    public function newSeed(): void
    {
        $this->seed = random_int(1, 999999);
    }

    public function compare(): void
    {
        $this->error = null;
        $this->comparison = null;

        $this->validate([
            'seed' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'betsBudget' => ['required', 'integer', 'min:'.ClosingComparisonService::MIN_BETS, 'max:'.ClosingComparisonService::MAX_BETS],
        ], [], ['seed' => 'semente', 'betsBudget' => 'orçamento de apostas']);

        try {
            $result = app(ClosingComparisonService::class)->compare($this->baseNumbers, $this->betsBudget, $this->methods, $this->seed);
            // Enums viram texto para o Livewire poder guardar o resultado
            $this->comparison = json_decode(json_encode($result), true);
        } catch (\InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();
        }
    }

    public function with(): array
    {
        return ['availableMethods' => ClosingComparisonService::METHODS];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section>
        <h1 class="text-3xl font-extrabold tracking-tight text-slate-900">Comparar métodos de geração</h1>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-500">
            Gera apostas com cada método sob as mesmas condições: mesmo grupo-base, mesmo orçamento de apostas, mesma semente aleatória
            e a mesma avaliação sobre os 3.268.760 sorteios possíveis. Nada é salvo. A comparação mostra como cada método se organiza;
            nenhum deles torna uma combinação mais provável de ser sorteada.
        </p>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 space-y-5">
        <div>
            <h2 class="text-sm font-bold text-slate-700">Grupo-base ({{ count($baseNumbers) }} dezenas, de 16 a 25)</h2>
            <div class="mt-3 grid grid-cols-5 gap-2 sm:grid-cols-10 lg:grid-cols-13" data-base-grid>
                @foreach (range(1, 25) as $number)
                    <button
                        type="button"
                        wire:click="toggleNumber({{ $number }})"
                        data-number="{{ $number }}"
                        @class([
                            'rounded-lg border py-2 text-sm font-bold transition',
                            'border-indigo-600 bg-indigo-600 text-white' => in_array($number, $baseNumbers, true),
                            'border-slate-200 bg-white text-slate-600 hover:border-indigo-300' => ! in_array($number, $baseNumbers, true),
                        ])
                    >{{ str_pad((string) $number, 2, '0', STR_PAD_LEFT) }}</button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="betsBudget" class="text-sm font-semibold text-slate-700">Orçamento (apostas por método)</label>
                <input id="betsBudget" type="number" wire:model="betsBudget" min="{{ ClosingComparisonService::MIN_BETS }}" max="{{ ClosingComparisonService::MAX_BETS }}" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p class="mt-1 text-xs text-slate-500">De {{ ClosingComparisonService::MIN_BETS }} a {{ ClosingComparisonService::MAX_BETS }}: a chance exata percorre todos os sorteios para cada aposta.</p>
                @error('betsBudget') <p class="mt-1 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="seed" class="text-sm font-semibold text-slate-700">Semente aleatória</label>
                <div class="mt-1 flex gap-2">
                    <input id="seed" type="number" wire:model="seed" min="0" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button type="button" wire:click="newSeed" class="shrink-0 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-600 hover:bg-slate-50">Sortear</button>
                </div>
                <p class="mt-1 text-xs text-slate-500">A mesma semente reproduz as mesmas apostas.</p>
                @error('seed') <p class="mt-1 text-sm font-medium text-rose-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <h2 class="text-sm font-bold text-slate-700">Métodos</h2>
            <div class="mt-2 flex flex-wrap gap-4">
                @foreach ($availableMethods as $key => $definition)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" wire:model="methods" value="{{ $key }}" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        {{ $definition['label'] }}
                    </label>
                @endforeach
            </div>
        </div>

        @if ($error)
            <p class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $error }}</p>
        @endif

        <button type="button" wire:click="compare" wire:loading.attr="disabled" wire:target="compare" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60">
            <span wire:loading.remove wire:target="compare">Comparar</span>
            <span wire:loading wire:target="compare">Calculando (pode levar alguns segundos)…</span>
        </button>
    </section>

    @if ($comparison)
        @php $results = $comparison["results"]; @endphp
        <section class="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm" data-comparison-result>
            <div class="border-b border-slate-100 px-5 py-4 text-sm text-slate-600 sm:px-6">
                Grupo-base de {{ count($comparison['base_numbers']) }} dezenas · orçamento de até {{ $comparison['bets_budget'] }} apostas · semente {{ $comparison['seed'] }} ·
                universo de {{ number_format($comparison['universe'], 0, ',', '.') }} sorteios.
            </div>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <th class="px-5 py-3">Métrica</th>
                        @foreach ($results as $key => $row)
                            <th class="px-4 py-3">
                                {{ $row['label'] }}
                                <div class="mt-1"><x-result-badge :classification="ResultClassification::from($row['classification'])" /></div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    @php
                        $cell = fn (array $row, callable $render): string => $row['error'] ? '—' : $render($row);
                        $pct = fn (float $value): string => number_format($value * 100, 2, ',', '.').'%';
                    @endphp
                    <tr>
                        <td class="px-5 py-2 font-medium">Apostas geradas</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => $r['bets_count']) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Custo</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => 'R$ '.number_format($r['cost'], 2, ',', '.')) }}</td>
                        @endforeach
                    </tr>
                    @foreach ([11, 12, 13, 14, 15] as $hits)
                        <tr>
                            <td class="px-5 py-2 font-medium">Chance de pelo menos {{ $hits }} pontos <x-result-badge :classification="ResultClassification::Exact" /></td>
                            @foreach ($results as $row)
                                <td class="px-4 py-2">{{ $cell($row, fn ($r) => $pct($r['coverage'][$hits]['probability'])) }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <td class="px-5 py-2 font-medium">Média de apostas premiadas (≥ 11)</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => number_format($r['expected_prized_bets'], 3, ',', '.')) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Score (menor · média · maior) <span class="text-xs font-normal text-slate-400">descritivo</span></td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => $r['scores']['min'].' · '.$r['scores']['mean'].' · '.$r['scores']['max']) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Redundância: dezenas em comum entre apostas (média)</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => $r['redundancy']['average_overlap'] !== null ? number_format($r['redundancy']['average_overlap'], 2, ',', '.') : '—') }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Pares de apostas quase iguais (≥ 14 em comum)</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => $r['redundancy']['near_duplicates'].' de '.$r['redundancy']['pairs']) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Uso das dezenas (mín. · máx. · desvio)</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => $r['distribution']['min'].' · '.$r['distribution']['max'].' · '.number_format($r['distribution']['spread'], 2, ',', '.')) }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Garantia de 14 pontos com as 15 sorteadas no grupo-base</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">
                                @if ($row['error'])
                                    —
                                @elseif ($row['guarantee']['evaluated'])
                                    {{ $row['guarantee']['confirmed'] ? 'Sim' : 'Não' }} ({{ number_format($row['guarantee']['covered_percent'], 2, ',', '.') }}% dos cenários)
                                    <x-result-badge :classification="ResultClassification::from($row['guarantee']['classification'])" />
                                @else
                                    <span class="text-xs text-slate-500">{{ $row['guarantee']['note'] }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Tempo de geração</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => number_format($r['time_ms'], 1, ',', '.').' ms') }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <td class="px-5 py-2 font-medium">Memória de geração</td>
                        @foreach ($results as $row)
                            <td class="px-4 py-2">{{ $cell($row, fn ($r) => number_format($r['memory_bytes'] / 1024, 0, ',', '.').' KB') }}</td>
                        @endforeach
                    </tr>
                    @if (collect($results)->contains(fn ($row) => $row['error']))
                        <tr>
                            <td class="px-5 py-2 font-medium text-rose-700">Erros</td>
                            @foreach ($results as $row)
                                <td class="px-4 py-2 text-xs text-rose-700">{{ $row['error'] }}</td>
                            @endforeach
                        </tr>
                    @endif
                </tbody>
            </table>
            <p class="border-t border-slate-100 px-5 py-4 text-xs leading-5 text-slate-500 sm:px-6">
                Cada aposta simples tem a mesma chance de sair; o que a comparação mostra é como o conjunto de apostas se espalha pelos sorteios possíveis.
                A chance exata e a garantia são calculadas por enumeração; score, tempo e memória descrevem a execução e a organização das apostas, não a probabilidade.
                O método reduzido (guloso) não prova ser o menor possível e pode gerar menos apostas que o orçamento.
            </p>
        </section>
    @endif
</div>
