<?php

use App\Models\Closing;
use App\Models\ConsolidatedAudit;
use App\Jobs\GenerateConsolidatedAuditJob;
use App\Services\Betting\CoverageAuditorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\WithPagination;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Auditoria Combinatória Consolidada'])] class extends Component
{
    use WithPagination;

    public ?int $selectedContest = null;
    public array $selectedClosings = [];
    public string $reportType = 'quick'; // quick or complete
    
    public ?array $immediateCoverage = null;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;

    public function updatedSelectedContest()
    {
        $this->selectedClosings = [];
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->immediateCoverage = null;
    }

    public function with(): array
    {
        $contestsWithClosings = Closing::where('user_id', Auth::id())
            ->whereNotNull('contest_number')
            ->select('contest_number')
            ->distinct()
            ->orderBy('contest_number', 'desc')
            ->pluck('contest_number')
            ->toArray();

        $availableClosings = [];
        if ($this->selectedContest) {
            $availableClosings = Closing::where('user_id', Auth::id())
                ->where('contest_number', $this->selectedContest)
                ->get();
        }

        $pastAudits = ConsolidatedAudit::where('user_id', Auth::id())
            ->latest()
            ->paginate(10);

        return [
            'contestsWithClosings' => $contestsWithClosings,
            'availableClosings' => $availableClosings,
            'pastAudits' => $pastAudits,
        ];
    }
    
    public function generateReport()
    {
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->immediateCoverage = null;

        if (count($this->selectedClosings) < 2) {
            $this->errorMessage = 'Selecione pelo menos 2 fechamentos para consolidar.';
            return;
        }

        $closings = Closing::whereIn('id', $this->selectedClosings)->where('user_id', Auth::id())->get();
        
        $baseNumbers = [];
        $totalBets = 0;
        foreach($closings as $closing) {
            if (is_array($closing->base_numbers)) {
                $baseNumbers = array_merge($baseNumbers, $closing->base_numbers);
            }
            $totalBets += $closing->bets()->count();
        }
        $baseNumbers = array_unique($baseNumbers);
        sort($baseNumbers);
        $totalBase = count($baseNumbers);

        if ($totalBets === 0) {
            $this->errorMessage = 'Os fechamentos selecionados não possuem apostas geradas.';
            return;
        }

        if ($totalBase > 25) { 
            $totalBase = 25;
        }

        $guaranteePoints = 14;
        $guaranteeHits = 15;
        
        if ($this->reportType === 'complete' && $totalBase > 23) {
            $audit = ConsolidatedAudit::create([
                'user_id' => Auth::id(),
                'contest_number' => $this->selectedContest,
                'closing_ids' => $this->selectedClosings,
                'base_numbers' => $baseNumbers,
                'total_bets' => $totalBets,
                'status' => 'pending',
                'report_type' => 'exact',
                'guarantee_hits' => $guaranteeHits,
                'guarantee_points' => $guaranteePoints,
            ]);

            GenerateConsolidatedAuditJob::dispatch($audit);
            
            $this->successMessage = 'O relatório completo foi enviado para a fila de processamento. Você pode acompanhar o status na lista abaixo.';
            $this->selectedClosings = [];
            return;
        }

        $auditType = 'exact';
        $coverageData = null;
        $auditor = app(CoverageAuditorService::class);
        
        $allBets = [];
        foreach ($closings as $closing) {
            foreach ($closing->bets()->get() as $bet) {
                $allBets[] = is_array($bet->numbers) ? $bet->numbers : (json_decode((string) $bet->numbers, true) ?? []);
            }
        }

        if ($totalBase > 23) {
            $auditType = 'monte_carlo';
            $coverageData = $auditor->auditCoverageMonteCarlo(
                baseNumbers: $baseNumbers,
                bets: $allBets,
                guaranteePoints: $guaranteePoints,
                guaranteeHits: $guaranteeHits,
                iterations: 500000
            );
        } else {
            $coverageData = $auditor->auditCoverage(
                baseNumbers: $baseNumbers,
                bets: $allBets,
                guaranteePoints: $guaranteePoints,
                guaranteeHits: $guaranteeHits
            );
        }
        
        $audit = ConsolidatedAudit::create([
            'user_id' => Auth::id(),
            'contest_number' => $this->selectedContest,
            'closing_ids' => $this->selectedClosings,
            'base_numbers' => $baseNumbers,
            'total_bets' => $totalBets,
            'status' => 'completed',
            'report_type' => $auditType,
            'guarantee_hits' => $guaranteeHits,
            'guarantee_points' => $guaranteePoints,
            'coverage_data' => $coverageData
        ]);
        
        $this->successMessage = 'Relatório gerado com sucesso!';
        $this->immediateCoverage = $coverageData;
        $this->selectedClosings = [];
    }
    
    public function viewAudit(int $id)
    {
        $this->errorMessage = null;
        $this->successMessage = null;
        $this->selectedContest = null;
        $this->selectedClosings = [];

        $audit = ConsolidatedAudit::where('id', $id)->where('user_id', Auth::id())->first();
        if ($audit && $audit->status === 'completed' && $audit->coverage_data) {
            $this->immediateCoverage = $audit->coverage_data;
        } else {
            $this->immediateCoverage = null;
            $this->errorMessage = 'Relatório ainda não concluído ou dados indisponíveis.';
        }
    }

    public function deleteAudit(int $id)
    {
        $audit = ConsolidatedAudit::where('id', $id)->where('user_id', Auth::id())->first();
        if ($audit) {
            $audit->delete();
        }
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div>
            <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ route('dashboard') }}" class="transition hover:text-indigo-600">Dashboard</a>
                <span>/</span>
                <a href="{{ route('closings.index') }}" class="transition hover:text-indigo-600">Fechamentos</a>
                <span>/</span>
                <span class="font-medium text-slate-700">Auditoria Consolidada</span>
            </div>

            <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
                Auditoria Combinatória Consolidada
            </h1>

            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
                Combine múltiplos fechamentos de um mesmo concurso e descubra as probabilidades reais da união de todos os jogos.
            </p>
        </div>
    </section>

    @if ($successMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <p class="text-sm font-medium">{{ $successMessage }}</p>
        </div>
    @endif

    @if ($errorMessage)
        <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-rose-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            <p class="text-sm font-medium">{{ $errorMessage }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <main class="lg:col-span-2 space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                <h2 class="text-base font-bold text-slate-900 mb-4">Nova Auditoria</h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="contest" class="block text-sm font-semibold text-slate-700">Concurso alvo</label>
                        <select id="contest" wire:model.live="selectedContest" class="mt-1 block w-full rounded-xl border-slate-300 py-2.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Selecione um concurso</option>
                            @foreach($contestsWithClosings as $c)
                                <option value="{{ $c }}">Concurso #{{ $c }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Apenas concursos com fechamentos cadastrados aparecerão aqui.</p>
                    </div>

                    @if($selectedContest)
                        <div class="border-t border-slate-100 pt-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Selecione os fechamentos para combinar:</label>
                            @if(count($availableClosings) === 0)
                                <p class="text-sm text-slate-500">Nenhum fechamento disponível para este concurso.</p>
                            @else
                                <div class="grid gap-3 sm:grid-cols-2 max-h-60 overflow-y-auto pr-2">
                                    @foreach($availableClosings as $closing)
                                        <label class="relative flex cursor-pointer rounded-lg border bg-white p-4 shadow-sm hover:bg-slate-50">
                                            <input type="checkbox" wire:model="selectedClosings" value="{{ $closing->id }}" class="peer sr-only" />
                                            <div class="flex flex-1">
                                                <div class="flex flex-col">
                                                    <span class="block text-sm font-medium text-slate-900">{{ $closing->name }}</span>
                                                    <span class="mt-1 flex items-center text-xs text-slate-500">{{ count($closing->base_numbers ?? []) }} dezenas | {{ $closing->bets()->count() }} apostas</span>
                                                </div>
                                            </div>
                                            <svg class="h-5 w-5 text-indigo-600 peer-checked:block hidden" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            <div class="pointer-events-none absolute -inset-px rounded-lg border-2 border-transparent peer-checked:border-indigo-500" aria-hidden="true"></div>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tipo de Relatório</label>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3">
                                    <input type="radio" wire:model="reportType" value="quick" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                    <div>
                                        <span class="block text-sm font-medium text-slate-900">Relatório Rápido</span>
                                        <span class="block text-xs text-slate-500">Avaliação completa até 23 dezenas unidas. Acima disso, usa aproximação de Monte Carlo (instantâneo).</span>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3">
                                    <input type="radio" wire:model="reportType" value="complete" class="text-indigo-600 focus:ring-indigo-500 h-4 w-4">
                                    <div>
                                        <span class="block text-sm font-medium text-slate-900">Relatório Completo</span>
                                        <span class="block text-xs text-slate-500">Avaliação exata independente do total de dezenas. Se houver mais que 23, será processado em 2º plano.</span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="pt-4 flex justify-end">
                            <button wire:click="generateReport" wire:loading.attr="disabled" class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:opacity-50">
                                <span wire:loading.remove wire:target="generateReport">Gerar Relatório</span>
                                <span wire:loading wire:target="generateReport">Gerando...</span>
                            </button>
                        </div>
                    @endif
                </div>
            </section>

            @if ($immediateCoverage)
                <section class="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/60 via-white to-slate-50 p-5 shadow-sm sm:p-6 space-y-5">
                    <div>
                        <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                            Relatório Gerado
                        </div>
                        <h3 class="mt-2 text-lg font-bold text-slate-900">{{ $immediateCoverage['condicoes_da_garantia'] }}</h3>
                        <p class="text-sm text-slate-600 mt-1">
                            Analisados {{ number_format($immediateCoverage['quantidade_cenarios_analisados'], 0, ',', '.') }} cenários 
                            @if(isset($immediateCoverage['is_monte_carlo']) && $immediateCoverage['is_monte_carlo'])
                                <strong>(Aproximação Simulação)</strong>
                            @else
                                <strong>(Validação Exata)</strong>
                            @endif
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5 pt-2">
                        @foreach(['15' => '15_acertos', '14' => '14_acertos', '13' => '13_acertos', '12' => '12_acertos', '11' => '11_acertos'] as $label => $key)
                            @php $chance = $immediateCoverage['cobertura_por_faixa'][$key] ?? 0; @endphp
                            <div class="rounded-xl border {{ $chance > 0 ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-white' }} p-3 text-center">
                                <span class="text-xs font-bold text-slate-600">{{ $label }} Acertos</span>
                                <p class="mt-1 text-lg font-black {{ $chance > 0 ? 'text-emerald-700' : 'text-slate-800' }}">
                                    {{ $chance }}%
                                </p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </main>

        <aside class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                    <h3 class="text-sm font-bold text-slate-900">Histórico de Relatórios</h3>
                </div>
                <div class="divide-y divide-slate-100" wire:poll.10s>
                    @forelse($pastAudits as $audit)
                        <div class="p-4" wire:key="audit-{{ $audit->id }}">
                            <div class="flex items-start justify-between">
                                <div>
                                    <p class="text-sm font-bold text-slate-900">Concurso #{{ $audit->contest_number }}</p>
                                    <p class="text-xs text-slate-500 mt-1">{{ count($audit->base_numbers ?? []) }} dezenas base unidas</p>
                                    <p class="text-xs text-slate-500">{{ $audit->total_bets }} apostas avaliadas</p>
                                </div>
                                <div>
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        'bg-sky-100 text-sky-700' => $audit->status === 'pending' || $audit->status === 'processing',
                                        'bg-emerald-100 text-emerald-800' => $audit->status === 'completed',
                                        'bg-rose-100 text-rose-700' => $audit->status === 'failed',
                                    ])>
                                        {{ match($audit->status) { 'pending' => 'Na fila', 'processing' => 'Calculando', 'completed' => 'Concluído', 'failed' => 'Falhou', default => $audit->status } }}
                                    </span>
                                </div>
                            </div>
                            
                            @if($audit->status === 'completed' && $audit->coverage_data)
                                <div class="mt-3 bg-slate-50 p-2 rounded border border-slate-100 grid grid-cols-3 gap-2 text-center text-xs">
                                    <div><span class="font-bold">15pts</span><br/>{{ $audit->coverage_data['cobertura_por_faixa']['15_acertos'] ?? 0 }}%</div>
                                    <div><span class="font-bold">14pts</span><br/>{{ $audit->coverage_data['cobertura_por_faixa']['14_acertos'] ?? 0 }}%</div>
                                    <div><span class="font-bold">13pts</span><br/>{{ $audit->coverage_data['cobertura_por_faixa']['13_acertos'] ?? 0 }}%</div>
                                </div>
                            @endif

                            <div class="mt-3 flex justify-end gap-3">
                                @if($audit->status === 'completed' && $audit->coverage_data)
                                    <button wire:click="viewAudit({{ $audit->id }})" class="text-xs text-indigo-600 hover:text-indigo-700 font-medium">Visualizar</button>
                                @endif
                                <button wire:click="deleteAudit({{ $audit->id }})" wire:confirm="Excluir este relatório?" class="text-xs text-rose-600 hover:text-rose-700 font-medium">Excluir</button>
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-center text-sm text-slate-500">
                            Nenhum relatório gerado ainda.
                        </div>
                    @endforelse
                </div>
                @if($pastAudits->hasPages())
                    <div class="px-5 py-3 border-t border-slate-100 bg-slate-50">
                        {{ $pastAudits->links(data: ['scrollTo' => false]) }}
                    </div>
                @endif
            </section>
        </aside>
    </div>
</div>
