<?php

use App\Models\Closing;
use App\Services\LotofacilStatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Novo fechamento'])] class extends Component
{
    public string $name = '';

    public string $method = 'reduced'; // Valor inicial pode ser 'reduced' para testar

    public array $base_numbers = [];

    public int $bet_size = 15;

    public int $planned_bets = 10;

    public function mount(): void
    {
        $numbersQuery = request()->query('numbers');
        if ($numbersQuery && is_string($numbersQuery)) {
            $parsedNumbers = array_filter(array_map('intval', explode(',', $numbersQuery)), fn ($n) => $n >= 1 && $n <= 25);
            if (! empty($parsedNumbers)) {
                $this->setBaseNumbersFromResult($parsedNumbers);
            }
        }
    }

    #[On('numbersSelected')]
    public function setBaseNumbersFromResult(array $numbers): void
    {
        $validNumbers = array_values(array_unique(array_filter(array_map('intval', $numbers), fn ($n) => $n >= 1 && $n <= 25)));
        sort($validNumbers);
        $this->base_numbers = $validNumbers;
        $this->resetValidation('base_numbers');
    }

    public function loadLastResultNumbers(): void
    {
        $service = app(LotofacilStatisticsService::class);
        $lastContestData = $service->getLastContestWithSum();
        if ($lastContestData && isset($lastContestData['result']['drawn_numbers'])) {
            $this->setBaseNumbersFromResult($lastContestData['result']['drawn_numbers']);
        }
    }

    public function generateStatisticalBaseNumbers(int $totalBase = 18, int $repetitions = 9): void
    {
        $service = app(LotofacilStatisticsService::class);
        $lastContestData = $service->getLastContestWithSum();
        $lastDrawn = $lastContestData['result']['drawn_numbers'] ?? [];
        $frequencies = $service->getNumberFrequencies()->toArray();

        // 1. Repetidas
        $selectedRepeated = [];
        if (! empty($lastDrawn) && $repetitions > 0) {
            $ranked = $lastDrawn;
            usort($ranked, function ($a, $b) use ($frequencies) {
                $freqA = $frequencies[$a] ?? 0;
                $freqB = $frequencies[$b] ?? 0;
                if ($freqA === $freqB) {
                    return random_int(-1, 1);
                }
                return $freqB <=> $freqA;
            });
            $pool = array_slice($ranked, 0, min(count($ranked), $repetitions + 4));
            shuffle($pool);
            $selectedRepeated = array_slice($pool, 0, min($repetitions, count($lastDrawn)));
        }

        // 2. Novas dezenas com filtros estatísticos
        $neededNewCount = max(0, $totalBase - count($selectedRepeated));
        $allNumbers = range(1, 25);
        $nonDrawnNumbers = array_values(array_diff($allNumbers, $lastDrawn));
        $frameNumbers = [1, 2, 3, 4, 5, 6, 10, 11, 15, 16, 20, 21, 22, 23, 24, 25];
        $frameSet = array_flip($frameNumbers);

        $scoredCandidates = [];
        foreach ($nonDrawnNumbers as $num) {
            $score = 0;
            $freq = $frequencies[$num] ?? 0;
            $score += $freq * 0.1;

            $currentEvens = count(array_filter($selectedRepeated, fn ($n) => $n % 2 === 0));
            $currentOdds = count($selectedRepeated) - $currentEvens;
            $isEven = ($num % 2 === 0);

            if ($currentEvens < $currentOdds && $isEven) {
                $score += 15;
            } elseif ($currentOdds <= $currentEvens && ! $isEven) {
                $score += 15;
            }

            $currentFrame = count(array_filter($selectedRepeated, fn ($n) => isset($frameSet[$n])));
            $isFrame = isset($frameSet[$num]);
            $targetRatio = 10 / 15;
            $currentRatio = count($selectedRepeated) > 0 ? $currentFrame / count($selectedRepeated) : 0.66;

            if ($currentRatio < $targetRatio && $isFrame) {
                $score += 12;
            } elseif ($currentRatio >= $targetRatio && ! $isFrame) {
                $score += 12;
            }

            $scoredCandidates[] = [
                'number' => $num,
                'score' => $score + mt_rand(0, 5),
            ];
        }

        usort($scoredCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
        $selectedNew = array_slice(array_column($scoredCandidates, 'number'), 0, $neededNewCount);

        $generatedGroup = array_merge($selectedRepeated, $selectedNew);
        $this->setBaseNumbersFromResult($generatedGroup);
    }

    public string $guarantee = ''; // Este campo 'guarantee' é genérico, não o do reduced_parameters

    public string $budget = '';

    public string $notes = '';

    public bool $processing = false;

    // Parâmetros para geração equilibrada
    public ?int $min_even = null;
    public ?int $max_even = null;
    public ?int $min_sum = null;
    public ?int $max_sum = null;
    public ?int $min_primes = null;
    public ?int $max_primes = null;
    public ?int $min_fibonacci = null;
    public ?int $max_fibonacci = null;
    public ?int $min_repeated_last_draw = null;
    public ?int $max_repeated_last_draw = null;

    // Novos parâmetros para sistema de roda
    public array $fixed_numbers = [];
    public array $variable_numbers = [];
    public ?int $wheel_size = null;

    // NOVOS PARÂMETROS PARA FECHAMENTO REDUZIDO
    public ?int $guarantee_hits = null;    // Acertos na base para garantia
    public ?int $guarantee_points = null;  // Pontos garantidos

    public function rules(): array
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'method' => [
                'required',
                'string',
                'in:integral,reduced,wheel,random,balanced',
            ],

            'base_numbers' => [
                'required',
                'array',
                'between:15,25',
            ],

            'base_numbers.*' => [
                'required',
                'integer',
                'distinct',
                'between:1,25',
            ],

            'bet_size' => [
                'required',
                'integer',
                'between:15,25',
            ],

            'planned_bets' => [
                'required',
                'integer',
                'min:1',
            ],

            'guarantee' => [
                'nullable',
                'integer',
                'between:15,15',
            ],

            'budget' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];

        // Regras condicionais para o método 'balanced'
        if ($this->method === 'balanced') {
            $rules['min_even'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_even'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_even'];
            $rules['min_sum'] = ['nullable', 'integer', 'min:15', 'max:300'];
            $rules['max_sum'] = ['nullable', 'integer', 'min:15', 'max:300', 'gte:min_sum'];
            $rules['min_primes'] = ['nullable', 'integer', 'min:0', 'max:9'];
            $rules['max_primes'] = ['nullable', 'integer', 'min:0', 'max:9', 'gte:min_primes'];
            $rules['min_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7'];
            $rules['max_fibonacci'] = ['nullable', 'integer', 'min:0', 'max:7', 'gte:min_fibonacci'];
            $rules['min_repeated_last_draw'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_repeated_last_draw'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_repeated_last_draw'];
        }

        // Regras condicionais para o método 'wheel'
        if ($this->method === 'wheel') {
            $rules['fixed_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . ($this->bet_size - 1), // Pelo menos uma variável
            ];
            $rules['fixed_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->base_numbers), // Dezenas fixas devem estar no grupo-base
            ];
            $rules['variable_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . (count($this->base_numbers) - count($this->fixed_numbers)), // Não pode exceder o restante do grupo-base
            ];
            $rules['variable_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->base_numbers), // Dezenas variáveis devem estar no grupo-base
                Rule::notIn($this->fixed_numbers), // Não pode estar nas fixas
            ];
            $rules['wheel_size'] = [
                'required',
                'integer',
                'min:1',
                'max:' . count($this->variable_numbers),
                'size:' . ($this->bet_size - count($this->fixed_numbers)), // fixed + wheel_size = bet_size
            ];
        }

        // NOVAS REGRAS CONDICIONAIS PARA O MÉTODO 'REDUCED'
        if ($this->method === 'reduced') {
            $rules['guarantee_hits'] = [
                'required',
                'integer',
                'min:' . $this->bet_size, // Deve ser pelo menos o tamanho da aposta
                'max:' . count($this->base_numbers), // Não pode exceder o grupo-base
            ];
            $rules['guarantee_points'] = [
                'required',
                'integer',
                'between:11,' . ($this->bet_size - 1), // Pontos garantidos entre 11 e (tamanho da aposta - 1)
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe um nome para o fechamento.',
            'name.max' => 'O nome não pode ultrapassar 255 caracteres.',

            'method.required' => 'Selecione o método do fechamento.',
            'method.in' => 'O método informado não é válido.',

            'base_numbers.required' => 'Selecione as dezenas do grupo-base.',
            'base_numbers.between' => 'O grupo-base deve conter entre 15 e 25 dezenas.',
            'base_numbers.*.distinct' => 'Não é permitido repetir dezenas no grupo-base.',
            'base_numbers.*.between' => 'As dezenas devem estar entre 1 e 25.',

            'bet_size.required' => 'Informe o tamanho de cada aposta.',
            'bet_size.between' => 'O tamanho da aposta deve estar entre 15 e 25.',

            'planned_bets.required' => 'Informe a quantidade planejada de apostas.',
            'planned_bets.min' => 'A quantidade planejada deve ser maior que zero.',

            'guarantee.between' => 'A garantia informada não é válida.',

            'budget.numeric' => 'O orçamento deve ser um valor numérico.',
            'budget.min' => 'O orçamento não pode ser negativo.',

            'notes.max' => 'As observações não podem ultrapassar 2.000 caracteres.',

            // Mensagens para os parâmetros de equilíbrio
            'min_even.min' => 'O mínimo de pares não pode ser negativo.',
            'min_even.max' => 'O mínimo de pares não pode exceder 15.',
            'max_even.min' => 'O máximo de pares não pode ser negativo.',
            'max_even.max' => 'O máximo de pares não pode exceder 15.',
            'max_even.gte' => 'O máximo de pares deve ser maior ou igual ao mínimo.',

            'min_sum.min' => 'A soma mínima não pode ser menor que 15.',
            'min_sum.max' => 'A soma mínima não pode exceder 300.',
            'max_sum.min' => 'A soma máxima não pode ser menor que 15.',
            'max_sum.max' => 'A soma máxima não pode exceder 300.',
            'max_sum.gte' => 'A soma máxima deve ser maior ou igual à soma mínima.',

            'min_primes.min' => 'O mínimo de primos não pode ser negativo.',
            'min_primes.max' => 'O mínimo de primos não pode exceder 9.',
            'max_primes.min' => 'O máximo de primos não pode ser negativo.',
            'max_primes.max' => 'O máximo de primos não pode exceder 9.',
            'max_primes.gte' => 'O máximo de primos deve ser maior ou igual ao mínimo.',

            'min_fibonacci.min' => 'O mínimo de Fibonacci não pode ser negativo.',
            'min_fibonacci.max' => 'O mínimo de Fibonacci não pode exceder 7.',
            'max_fibonacci.min' => 'O máximo de Fibonacci não pode ser negativo.',
            'max_fibonacci.max' => 'O máximo de Fibonacci não pode exceder 7.',
            'max_fibonacci.gte' => 'O máximo de Fibonacci deve ser maior ou igual ao mínimo.',

            // Mensagens para os parâmetros do sistema de roda
            'fixed_numbers.required' => 'Selecione as dezenas fixas.',
            'fixed_numbers.array' => 'As dezenas fixas devem ser uma lista.',
            'fixed_numbers.min' => 'Selecione pelo menos uma dezena fixa.',
            'fixed_numbers.max' => 'O número de dezenas fixas não pode ser maior que o tamanho da aposta menos 1.',
            'fixed_numbers.*.required' => 'A dezena fixa não pode ser vazia.',
            'fixed_numbers.*.distinct' => 'Não é permitido repetir dezenas fixas.',
            'fixed_numbers.*.between' => 'As dezenas fixas devem estar entre 1 e 25.',
            'fixed_numbers.*.in' => 'A dezena fixa :input não está no grupo-base.',

            'variable_numbers.required' => 'Selecione as dezenas variáveis.',
            'variable_numbers.array' => 'As dezenas variáveis devem ser uma lista.',
            'variable_numbers.min' => 'Selecione pelo menos uma dezena variável.',
            'variable_numbers.max' => 'O número de dezenas variáveis excede o restante do grupo-base.',
            'variable_numbers.*.required' => 'A dezena variável não pode ser vazia.',
            'variable_numbers.*.distinct' => 'Não é permitido repetir dezenas variáveis.',
            'variable_numbers.*.between' => 'As dezenas variáveis devem estar entre 1 e 25.',
            'variable_numbers.*.in' => 'A dezena variável :input não está no grupo-base.',
            'variable_numbers.*.not_in' => 'A dezena variável :input também está nas dezenas fixas.',

            'wheel_size.required' => 'Informe o tamanho da roda.',
            'wheel_size.integer' => 'O tamanho da roda deve ser um número inteiro.',
            'wheel_size.min' => 'O tamanho da roda deve ser de pelo menos 1.',
            'wheel_size.max' => 'O tamanho da roda não pode exceder o número de dezenas variáveis.',
            'wheel_size.size' => 'A soma das dezenas fixas e o tamanho da roda deve ser igual ao tamanho da aposta.',

            // NOVAS MENSAGENS PARA O MÉTODO 'REDUCED'
            'guarantee_hits.required' => 'Informe o número de acertos na base para garantia.',
            'guarantee_hits.integer' => 'O número de acertos na base para garantia deve ser um número inteiro.',
            'guarantee_hits.min' => 'O número de acertos na base para garantia deve ser pelo menos :min.',
            'guarantee_hits.max' => 'O número de acertos na base para garantia não pode exceder :max.',

            'guarantee_points.required' => 'Informe os pontos garantidos.',
            'guarantee_points.integer' => 'Os pontos garantidos devem ser um número inteiro.',
            'guarantee_points.between' => 'Os pontos garantidos devem estar entre :min e :max.',
        ];
    }

    public function toggleNumber(int $number): void
    {
        if ($number < 1 || $number > 25) {
            return;
        }

        if (in_array($number, $this->base_numbers, true)) {
            $this->base_numbers = array_values(
                array_filter(
                    $this->base_numbers,
                    fn (int $selectedNumber): bool => $selectedNumber !== $number
                )
            );

            // Remove dezenas fixas/variáveis se forem desmarcadas do grupo-base
            $this->fixed_numbers = array_values(array_filter($this->fixed_numbers, fn($n) => $n !== $number));
            $this->variable_numbers = array_values(array_filter($this->variable_numbers, fn($n) => $n !== $number));

            if ($this->bet_size > count($this->base_numbers)) {
                $this->bet_size = max(15, count($this->base_numbers));
            }

            $this->resetValidation('base_numbers');
            $this->resetValidation('fixed_numbers');
            $this->resetValidation('variable_numbers');

            return;
        }

        if (count($this->base_numbers) >= 25) {
            $this->addError(
                'base_numbers',
                'O grupo-base pode conter no máximo 25 dezenas.'
            );

            return;
        }

        $this->resetValidation('base_numbers');

        $this->base_numbers[] = $number;

        sort($this->base_numbers);
    }

    // Métodos para toggle de dezenas fixas/variáveis
    public function toggleFixedNumber(int $number): void
    {
        if (!in_array($number, $this->base_numbers, true)) {
            $this->addError('fixed_numbers', "A dezena {$number} não está no grupo-base.");
            return;
        }

        if (in_array($number, $this->fixed_numbers, true)) {
            $this->fixed_numbers = array_values(array_filter($this->fixed_numbers, fn($n) => $n !== $number));
        } elseif (!in_array($number, $this->variable_numbers, true)) { // Não pode ser fixa e variável ao mesmo tempo
            $this->fixed_numbers[] = $number;
            sort($this->fixed_numbers);
        }
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
    }

    public function toggleVariableNumber(int $number): void
    {
        if (!in_array($number, $this->base_numbers, true)) {
            $this->addError('variable_numbers', "A dezena {$number} não está no grupo-base.");
            return;
        }

        if (in_array($number, $this->variable_numbers, true)) {
            $this->variable_numbers = array_values(array_filter($this->variable_numbers, fn($n) => $n !== $number));
        } elseif (!in_array($number, $this->fixed_numbers, true)) { // Não pode ser fixa e variável ao mesmo tempo
            $this->variable_numbers[] = $number;
            sort($this->variable_numbers);
        }
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
    }

    public function clearNumbers(): void
    {
        $this->base_numbers = [];
        $this->bet_size = 15;
        $this->fixed_numbers = [];
        $this->variable_numbers = [];
        $this->wheel_size = null;
        $this->guarantee_hits = null; // Limpar também os novos campos
        $this->guarantee_points = null; // Limpar também os novos campos

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
        $this->resetValidation('guarantee_hits'); // Resetar validação
        $this->resetValidation('guarantee_points'); // Resetar validação
    }

    public function selectRandomNumbers(): void
    {
        $availableNumbers = range(1, 25);

        shuffle($availableNumbers);

        $this->base_numbers = array_slice($availableNumbers, 0, 20);

        sort($this->base_numbers);

        $this->bet_size = 15;
        $this->fixed_numbers = [];
        $this->variable_numbers = [];
        $this->wheel_size = null;
        $this->guarantee_hits = null; // Limpar também os novos campos
        $this->guarantee_points = null; // Limpar também os novos campos

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
        $this->resetValidation('guarantee_hits'); // Resetar validação
        $this->resetValidation('guarantee_points'); // Resetar validação
    }

    public function save(): void
    {
        $this->processing = true;

        try {
            $this->validate();

            if ($this->bet_size > count($this->base_numbers)) {
                $this->addError(
                    'bet_size',
                    'O tamanho da aposta não pode ser maior que o grupo-base.'
                );

                return;
            }

            // Validações manuais para o método 'wheel'
            if ($this->method === 'wheel') {
                // 1. Dezenas fixas devem estar no grupo-base
                foreach ($this->fixed_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('fixed_numbers', "A dezena fixa {$number} não está no grupo-base.");
                        return;
                    }
                }

                // 2. Dezenas variáveis devem estar no grupo-base
                foreach ($this->variable_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} não está no grupo-base.");
                        return;
                    }
                }

                // 3. Dezenas variáveis não podem estar nas dezenas fixas
                foreach ($this->variable_numbers as $number) {
                    if (in_array($number, $this->fixed_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} também está nas dezenas fixas.");
                        return;
                    }
                }
            }

            // Validações manuais para o método 'reduced'
            if ($this->method === 'reduced') {
                if ($this->guarantee_hits !== null && $this->guarantee_points !== null) {
                    if ($this->guarantee_points >= $this->bet_size) {
                        $this->addError(
                            'guarantee_points',
                            'Os pontos garantidos devem ser menores que o tamanho da aposta.'
                        );
                        return;
                    }
                    if ($this->guarantee_hits < $this->bet_size) {
                        $this->addError(
                            'guarantee_hits',
                            'O número de acertos na base para garantia deve ser maior ou igual ao tamanho da aposta.'
                        );
                        return;
                    }
                    if ($this->guarantee_hits > count($this->base_numbers)) {
                        $this->addError(
                            'guarantee_hits',
                            'O número de acertos na base para garantia não pode ser maior que o grupo-base.'
                        );
                        return;
                    }
                }
            }


            $parameters = null;
            if ($this->method === 'balanced') {
                $parameters = [];
                if ($this->min_even !== null && $this->max_even !== null) {
                    $parameters['even_odd_balance'] = [(int) $this->min_even, (int) $this->max_even];
                }
                if ($this->min_sum !== null && $this->max_sum !== null) {
                    $parameters['sum_range'] = [(int) $this->min_sum, (int) $this->max_sum];
                }
                if ($this->min_primes !== null && $this->max_primes !== null) {
                    $parameters['primes_count'] = [(int) $this->min_primes, (int) $this->max_primes];
                }
                if ($this->min_fibonacci !== null && $this->max_fibonacci !== null) {
                    $parameters['fibonacci_count'] = [(int) $this->min_fibonacci, (int) $this->max_fibonacci];
                }
                if ($this->min_repeated_last_draw !== null && $this->max_repeated_last_draw !== null) {
                    $parameters['repeated_last_draw'] = [(int) $this->min_repeated_last_draw, (int) $this->max_repeated_last_draw];
                }
                if (empty($parameters)) {
                    $parameters = null;
                }
            } elseif ($this->method === 'wheel') {
                $parameters = [];
                if (!empty($this->fixed_numbers)) {
                    $parameters['fixed_numbers'] = $this->fixed_numbers;
                }
                if (!empty($this->variable_numbers)) {
                    $parameters['variable_numbers'] = $this->variable_numbers;
                }
                if ($this->wheel_size !== null) {
                    $parameters['wheel_size'] = (int) $this->wheel_size;
                }
                if (empty($parameters)) {
                    $parameters = null;
                }
            } elseif ($this->method === 'reduced') { // NOVO: Lógica para salvar parâmetros do fechamento reduzido
                $parameters = [];
                if ($this->guarantee_hits !== null) {
                    $parameters['reduced_parameters']['guarantee_hits'] = (int) $this->guarantee_hits;
                }
                if ($this->guarantee_points !== null) {
                    $parameters['reduced_parameters']['guarantee_points'] = (int) $this->guarantee_points;
                }
                if (empty($parameters['reduced_parameters'])) {
                    $parameters = null;
                }
            }

            $closing = Closing::create([
                'user_id' => Auth::id(),
                'name' => $this->name,
                'method' => $this->method,
                'base_numbers' => $this->base_numbers,
                'bet_size' => $this->bet_size,
                'planned_bets' => $this->planned_bets,
                'guarantee' => $this->guarantee !== ''
                    ? (int) $this->guarantee
                    : null,
                'budget' => $this->budget !== ''
                    ? $this->budget
                    : null,
                'parameters' => $parameters,
                'status' => 'draft',
                'notes' => $this->notes !== ''
                    ? $this->notes
                    : null,
            ]);

            session()->flash(
                'success',
                "Fechamento {$closing->id} criado como rascunho."
            );

            $this->redirectRoute('closings.index');
        } finally {
            $this->processing = false;
        }
    }

    public function with(): array
    {
        $service = app(LotofacilStatisticsService::class);
        $temperatures = $service->getNumberTemperatureClassification(20);
        $lastContestStats = $service->getLastContestFullStatistics();

        return [
            'numberTemperatures' => $temperatures,
            'lastContestStats' => $lastContestStats,
        ];
    }
};
?>

<div class="mx-auto max-w-7xl space-y-6">
    <section class="flex flex-col justify-between gap-4 md:flex-row md:items-end">
        <div class="mb-3 flex items-center gap-2 text-sm text-slate-400">
            <a
                href="{{ route('dashboard') }}"
                class="transition hover:text-indigo-600"
            >
                Dashboard
            </a>

            <span>/</span>

            <a
                href="{{ route('closings.index') }}"
                class="transition hover:text-indigo-600"
            >
                Fechamentos
            </a>

            <span>/</span>

            <span class="font-medium text-slate-700">
                Novo fechamento
            </span>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
            Configuração de fechamento
        </div>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
            Novo fechamento
        </h1>

        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
            Defina o grupo-base e os parâmetros que serão utilizados em um futuro fechamento.
        </p>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2 sm:p-6">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Grupo-base
                    </h2>

                    <p class="mt-1 text-sm text-slate-500">
                        Selecione entre 15 e 25 dezenas para formar o grupo-base.
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-bold text-indigo-700">
                        {{ count($base_numbers) }}/25
                    </span>

                    <button
                        type="button"
                        wire:click="clearNumbers"
                        wire:loading.attr="disabled"
                        class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Limpar
                    </button>
                </div>
            </div>

            {{-- Painel de Estatísticas do Último Concurso para Consulta --}}
            @if ($lastContestStats)
                <div class="mt-4 rounded-xl border border-indigo-100 bg-gradient-to-r from-indigo-50/70 via-slate-50 to-emerald-50/50 p-3.5 text-xs text-slate-700">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-100/70 pb-2 mb-2.5">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-md bg-indigo-600 px-2 py-0.5 text-[11px] font-bold text-white shadow-sm">
                                Concurso {{ $lastContestStats['contest_number'] }}
                            </span>
                            @if ($lastContestStats['draw_date'])
                                <span class="text-slate-500 font-medium">({{ $lastContestStats['draw_date'] }})</span>
                            @endif
                        </div>

                        <span class="text-[11px] font-semibold text-slate-500">
                            Referência para planejamento do fechamento
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-6">
                        {{-- Soma --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Soma Total</span>
                            <span class="text-sm font-bold text-slate-900">{{ $lastContestStats['sum'] }}</span>
                            <span class="text-[10px] text-slate-500 block">pontos</span>
                        </div>

                        {{-- Par / Ímpar --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Par / Ímpar</span>
                            <span class="text-sm font-bold text-slate-900">{{ $lastContestStats['evens'] }}P / {{ $lastContestStats['odds'] }}I</span>
                            <span class="text-[10px] text-slate-500 block">proporção</span>
                        </div>

                        {{-- Repetições do Anterior --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Repetições</span>
                            <span class="text-sm font-bold text-indigo-700">
                                {{ $lastContestStats['repeated_from_previous'] !== null ? $lastContestStats['repeated_from_previous'].' dezenas' : 'N/A' }}
                            </span>
                            <span class="text-[10px] text-slate-500 block">do conc. anterior</span>
                        </div>

                        {{-- Primos --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Primos</span>
                            <span class="text-sm font-bold text-slate-900">{{ $lastContestStats['primes'] }}</span>
                            <span class="text-[10px] text-slate-500 block">de 9 possíveis</span>
                        </div>

                        {{-- Fibonacci --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Fibonacci</span>
                            <span class="text-sm font-bold text-slate-900">{{ $lastContestStats['fibonacci'] }}</span>
                            <span class="text-[10px] text-slate-500 block">de 7 possíveis</span>
                        </div>

                        {{-- Moldura / Centro --}}
                        <div class="rounded-lg bg-white/90 p-2 border border-slate-200/60 shadow-2xs">
                            <span class="text-[10px] font-medium text-slate-400 block uppercase tracking-wider">Moldura / Centro</span>
                            <span class="text-sm font-bold text-slate-900">{{ $lastContestStats['frame'] }}M / {{ $lastContestStats['center'] }}C</span>
                            <span class="text-[10px] text-slate-500 block">distribuição</span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-6 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                @foreach (range(1, 25) as $number)
                    @php
                        $selected = in_array($number, $base_numbers, true);
                        $tempInfo = $numberTemperatures[$number] ?? ['temperature' => 'neutral', 'recent_count' => 0, 'delay' => 0];
                        $temp = $tempInfo['temperature'];
                    @endphp

                    <button
                        type="button"
                        wire:key="closing-number-{{ $number }}"
                        wire:click="toggleNumber({{ $number }})"
                        title="Dezena {{ sprintf('%02d', $number) }} - {{ $temp === 'hot' ? 'Quente (Saiu '.$tempInfo['recent_count'].'x nos ultimos 20 concursos)' : ($temp === 'cold' ? 'Fria (Atrasada ou poucas saídas recentes)' : 'Neutra / Media') }}"
                        @class([
                            'relative flex aspect-square flex-col items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => $selected,
                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! $selected,
                        ])
                    >
                        {{-- Indicador visual sutil de temperatura --}}
                        <span class="absolute top-1 right-1.5 flex h-2 w-2">
                            @if ($temp === 'hot')
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500" title="Dezena Quente"></span>
                            @elseif ($temp === 'cold')
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-sky-400" title="Dezena Fria"></span>
                            @endif
                        </span>

                        <span>{{ str_pad($number, 2, '0', STR_PAD_LEFT) }}</span>
                    </button>
                @endforeach
            </div>

            {{-- Painel de Relevância / Temperatura do Grupo Base Selecionado --}}
            @php
                $hotCount = 0;
                $neutralCount = 0;
                $coldCount = 0;
                foreach ($base_numbers as $num) {
                    $t = $numberTemperatures[$num]['temperature'] ?? 'neutral';
                    if ($t === 'hot') $hotCount++;
                    elseif ($t === 'cold') $coldCount++;
                    else $neutralCount++;
                }
            @endphp

            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-3 text-xs">
                <div class="flex items-center gap-1.5 font-medium text-slate-600">
                    <span class="font-semibold text-slate-800">Composição do Grupo:</span>
                    <span>{{ count($base_numbers) }} dezenas selecionadas</span>
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5 rounded-lg bg-amber-100/70 px-2.5 py-1 font-semibold text-amber-800 border border-amber-200/50">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span>{{ $hotCount }} Quentes</span>
                    </div>

                    <div class="flex items-center gap-1.5 rounded-lg bg-slate-200/60 px-2.5 py-1 font-semibold text-slate-700 border border-slate-300/50">
                        <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                        <span>{{ $neutralCount }} Médias</span>
                    </div>

                    <div class="flex items-center gap-1.5 rounded-lg bg-sky-100/70 px-2.5 py-1 font-semibold text-sky-800 border border-sky-200/50">
                        <span class="h-2 w-2 rounded-full bg-sky-400"></span>
                        <span>{{ $coldCount }} Frias</span>
                    </div>
                </div>
            </div>

            @error('base_numbers')
                <p class="mt-4 text-sm font-medium text-rose-600">
                    {{ $message }}
                </p>
            @enderror

            <div class="mt-6 flex flex-wrap justify-end gap-3">
                {{-- Botão de Sugestão Estatística Avançada --}}
                <div x-data="{ open: false, totalBase: 18, repetitions: 9 }" class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        Sugerir por Análise Estatística
                    </button>

                    {{-- Popover com os parâmetros --}}
                    <div
                        x-show="open"
                        @click.outside="open = false"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95"
                        class="absolute right-0 z-30 mt-2 w-80 rounded-2xl border border-emerald-100 bg-white p-5 shadow-xl"
                        style="display: none;"
                    >
                        <div class="border-b border-slate-100 pb-3">
                            <h3 class="text-sm font-bold text-slate-800">
                                Parâmetros do Grupo Estatístico
                            </h3>
                            <p class="text-xs text-slate-500">
                                Filtra repetições do último concurso, paridade, moldura/centro e sequências.
                            </p>
                        </div>

                        <div class="mt-4 space-y-4">
                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-700">
                                    <span>Tamanho da Base:</span>
                                    <span class="text-emerald-700 font-bold" x-text="totalBase + ' dezenas'"></span>
                                </div>
                                <input
                                    type="range"
                                    min="15"
                                    max="25"
                                    step="1"
                                    x-model="totalBase"
                                    class="mt-1.5 w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-emerald-600"
                                >
                            </div>

                            <div>
                                <div class="flex justify-between text-xs font-semibold text-slate-700">
                                    <span>Repetições do Último:</span>
                                    <span class="text-indigo-700 font-bold" x-text="repetitions + ' dezenas'"></span>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    :max="Math.min(15, totalBase)"
                                    step="1"
                                    x-model="repetitions"
                                    class="mt-1.5 w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                                >
                            </div>

                            <button
                                type="button"
                                @click="$wire.generateStatisticalBaseNumbers(Number(totalBase), Number(repetitions)); open = false;"
                                class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-emerald-600 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700"
                            >
                                Aplicar Sugestão
                            </button>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="loadLastResultNumbers"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-sm font-semibold text-indigo-700 shadow-sm transition hover:border-indigo-300 hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Usar último resultado (15 dezenas)
                </button>

                <button
                    type="button"
                    wire:click="selectRandomNumbers"
                    wire:loading.attr="disabled"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:bg-indigo-50 hover:text-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004 12c0 2.972 1.154 5.667 3.034 7.75M20 20v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                    </svg>

                    Selecionar 20 aleatórias
                </button>
            </div>
        </section>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l4.414 4.414A1 1 0 0119 8.414V19a2 2 0 01-2 2z"
                        />
                    </svg>
                </div>

                <div>
                    <h2 class="text-base font-bold text-slate-900">
                        Configuração
                    </h2>

                    <p class="text-sm text-slate-500">
                        Parâmetros do fechamento
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-6">
                <div>
                    <label
                        for="name"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Nome do fechamento
                    </label>

                    <input
                        id="name"
                        type="text"
                        wire:model="name"
                        placeholder="Ex: Meus números da sorte"
                        autocomplete="off"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('name')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="method"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Método de geração
                    </label>

                    <select
                        id="method"
                        wire:model.live="method"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="integral">Combinação integral</option>
                        <option value="random">Geração aleatória</option>
                        <option value="balanced">Geração equilibrada</option>
                        <option value="wheel">Sistema de roda</option>
                        <option value="reduced">Fechamento reduzido</option> {{-- Removido "(em breve)" --}}
                    </select>

                    @error('method')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="bet_size"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Tamanho da aposta
                    </label>

                    <input
                        id="bet_size"
                        type="number"
                        wire:model="bet_size"
                        min="15"
                        max="25"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('bet_size')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="planned_bets"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Apostas planejadas
                    </label>

                    <input
                        id="planned_bets"
                        type="number"
                        wire:model="planned_bets"
                        min="1"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('planned_bets')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                {{-- Campos para o método Balanced --}}
                @if ($method === 'balanced')
                    <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <h3 class="text-base font-bold text-indigo-700">
                            Parâmetros de Equilíbrio
                        </h3>

                        <div>
                            <label
                                for="min_even"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Pares (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_even"
                                    type="number"
                                    wire:model="min_even"
                                    placeholder="Mín"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_even"
                                    placeholder="Máx"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_even')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_even')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_sum"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Soma das Dezenas (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_sum"
                                    type="number"
                                    wire:model="min_sum"
                                    placeholder="Mín"
                                    min="15"
                                    max="300"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_sum"
                                    placeholder="Máx"
                                    min="15"
                                    max="300"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_sum')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_sum')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_primes"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Primas (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_primes"
                                    type="number"
                                    wire:model="min_primes"
                                    placeholder="Mín"
                                    min="0"
                                    max="9"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    type="number"
                                    wire:model="max_primes"
                                    placeholder="Máx"
                                    min="0"
                                    max="9"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_primes')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_primes')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_fibonacci"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Dezenas Fibonacci (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_fibonacci"
                                    type="number"
                                    wire:model="min_fibonacci"
                                    placeholder="Mín"
                                    min="0"
                                    max="7"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    id="max_fibonacci"
                                    type="number"
                                    wire:model="max_fibonacci"
                                    placeholder="Máx"
                                    min="0"
                                    max="7"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_fibonacci')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_fibonacci')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="min_repeated_last_draw"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Repetidas do Último Sorteio (Mín/Máx)
                            </label>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_repeated_last_draw"
                                    type="number"
                                    wire:model="min_repeated_last_draw"
                                    placeholder="Mín (ex: 8)"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    id="max_repeated_last_draw"
                                    type="number"
                                    wire:model="max_repeated_last_draw"
                                    placeholder="Máx (ex: 10)"
                                    min="0"
                                    max="15"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_repeated_last_draw')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_repeated_last_draw')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- Campos para o método Wheel --}}
                @if ($method === 'wheel')
                    <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <h3 class="text-base font-bold text-indigo-700">
                            Parâmetros do Sistema de Roda
                        </h3>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Dezenas Fixas (presentes em todas as apostas)
                            </label>
                            <div class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                                @foreach ($base_numbers as $number)
                                    <button
                                        type="button"
                                        wire:key="fixed-number-{{ $number }}"
                                        wire:click="toggleFixedNumber({{ $number }})"
                                        @class([
                                            'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => in_array($number, $fixed_numbers, true),
                                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! in_array($number, $fixed_numbers, true),
                                        ])
                                    >
                                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                    </button>
                                @endforeach
                            </div>
                            @error('fixed_numbers')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('fixed_numbers.*')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700">
                                Dezenas Variáveis (serão combinadas)
                            </label>
                            <div class="mt-2 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                                @foreach ($base_numbers as $number)
                                    @if (!in_array($number, $fixed_numbers, true))
                                        <button
                                            type="button"
                                            wire:key="variable-number-{{ $number }}"
                                            wire:click="toggleVariableNumber({{ $number }})"
                                            @class([
                                                'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                                                'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => in_array($number, $variable_numbers, true),
                                                'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! in_array($number, $variable_numbers, true),
                                            ])
                                        >
                                            {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                            @error('variable_numbers')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('variable_numbers.*')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="wheel_size"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Tamanho da Roda (quantas variáveis por aposta)
                            </label>

                            <input
                                id="wheel_size"
                                type="number"
                                wire:model="wheel_size"
                                min="1"
                                max="{{ count($variable_numbers) }}"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >

                            @error('wheel_size')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- NOVOS CAMPOS PARA O MÉTODO REDUCED --}}
                @if ($method === 'reduced')
                    <div class="space-y-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                        <h3 class="text-base font-bold text-indigo-700">
                            Parâmetros de Fechamento Reduzido
                        </h3>

                        <div>
                            <label
                                for="guarantee_hits"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Acertos na Base para Garantia
                            </label>
                            <p class="mt-1 text-xs text-slate-500">
                                Quantas dezenas do grupo-base precisam ser acertadas para ativar a garantia.
                            </p>
                            <input
                                id="guarantee_hits"
                                type="number"
                                wire:model="guarantee_hits"
                                min="{{ $bet_size }}"
                                max="{{ count($base_numbers) }}"
                                placeholder="Ex: 15 (se acertar 15 dezenas do grupo-base)"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            @error('guarantee_hits')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <div>
                            <label
                                for="guarantee_points"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Pontos Garantidos
                            </label>
                            <p class="mt-1 text-xs text-slate-500">
                                Quantos pontos serão garantidos se a condição de acertos na base for cumprida.
                            </p>
                            <input
                                id="guarantee_points"
                                type="number"
                                wire:model="guarantee_points"
                                min="11"
                                max="{{ $bet_size - 1 }}"
                                placeholder="Ex: 14 (garantir 14 pontos)"
                                class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                            @error('guarantee_points')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                @endif

                <div>
                    <label
                        for="guarantee"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Garantia (ex: 15 acertos)
                    </label>

                    <input
                        id="guarantee"
                        type="number"
                        wire:model="guarantee"
                        min="15"
                        max="15"
                        placeholder="Não obrigatório"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('guarantee')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="budget"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Orçamento (R$)
                    </label>

                    <input
                        id="budget"
                        type="number"
                        wire:model="budget"
                        min="0"
                        step="0.01"
                        placeholder="Não obrigatório"
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >

                    @error('budget')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>

                <div>
                    <label
                        for="notes"
                        class="block text-sm font-semibold text-slate-700"
                    >
                        Observações
                    </label>

                    <textarea
                        id="notes"
                        wire:model="notes"
                        rows="3"
                        maxlength="2000"
                        placeholder="Anote detalhes importantes sobre este fechamento..."
                        class="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    ></textarea>

                    @error('notes')
                        <p class="mt-2 text-sm font-medium text-rose-600">
                            {{ $message }}
                        </p>
                    @enderror
                </div>
            </div>
        </aside>
    </div>

    <div class="flex justify-end">
        <button
            type="submit"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
            </svg>

            <span wire:loading.remove wire:target="save">
                Salvar fechamento
            </span>

            <span wire:loading wire:target="save">
                Salvando...
            </span>
        </button>
    </div>
</div>
