<?php

use App\Models\Closing;
use App\Services\LotofacilStatisticsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new #[Layout('layouts.app', ['title' => 'Editar fechamento'])] class extends Component
{
    public Closing $closing;

    public string $name = '';

    public string $method = 'reduced';

    public array $base_numbers = [];

    public int $bet_size = 15;

    public int $planned_bets = 10;

    public string $guarantee = '';

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
    public ?int $min_score = null;
    public ?int $max_score = null;
    public ?int $min_hot = null;
    public ?int $max_hot = null;
    public ?int $min_neutral = null;
    public ?int $max_neutral = null;
    public ?int $min_cold = null;
    public ?int $max_cold = null;

    // Parâmetros para sistema de roda
    public array $fixed_numbers = [];
    public array $variable_numbers = [];
    public ?int $wheel_size = null;

    // Parâmetros para fechamento reduzido
    public ?int $guarantee_hits = null;
    public ?int $guarantee_points = null;

    public function mount(Closing $closing): void
    {
        if ($closing->user_id !== Auth::id()) {
            abort(403);
        }

        if ($closing->status !== 'draft' && $closing->status !== 'failed') {
            session()->flash('error', 'Fechamentos já gerados ou concluídos não podem ter seus parâmetros alterados.');
            $this->redirectRoute('closings.show', $closing);
            return;
        }

        $this->closing = $closing;
        $this->name = (string) $closing->name;
        $this->method = (string) $closing->method;
        $this->base_numbers = is_array($closing->base_numbers) ? array_map('intval', $closing->base_numbers) : [];
        sort($this->base_numbers);
        $this->bet_size = (int) $closing->bet_size;
        $this->planned_bets = (int) $closing->planned_bets;
        $this->guarantee = $closing->guarantee !== null ? (string) $closing->guarantee : '';
        $this->budget = $closing->budget !== null ? (string) $closing->budget : '';
        $this->notes = (string) ($closing->notes ?? '');

        // Carregar parâmetros salvos
        $params = is_array($closing->parameters) ? $closing->parameters : [];

        if ($this->method === 'balanced') {
            if (isset($params['even_odd_balance']) && is_array($params['even_odd_balance'])) {
                $this->min_even = isset($params['even_odd_balance'][0]) ? (int) $params['even_odd_balance'][0] : null;
                $this->max_even = isset($params['even_odd_balance'][1]) ? (int) $params['even_odd_balance'][1] : null;
            }
            if (isset($params['sum_range']) && is_array($params['sum_range'])) {
                $this->min_sum = isset($params['sum_range'][0]) ? (int) $params['sum_range'][0] : null;
                $this->max_sum = isset($params['sum_range'][1]) ? (int) $params['sum_range'][1] : null;
            }
            if (isset($params['primes_count']) && is_array($params['primes_count'])) {
                $this->min_primes = isset($params['primes_count'][0]) ? (int) $params['primes_count'][0] : null;
                $this->max_primes = isset($params['primes_count'][1]) ? (int) $params['primes_count'][1] : null;
            }
            if (isset($params['fibonacci_count']) && is_array($params['fibonacci_count'])) {
                $this->min_fibonacci = isset($params['fibonacci_count'][0]) ? (int) $params['fibonacci_count'][0] : null;
                $this->max_fibonacci = isset($params['fibonacci_count'][1]) ? (int) $params['fibonacci_count'][1] : null;
            }
            if (isset($params['repeated_last_draw']) && is_array($params['repeated_last_draw'])) {
                $this->min_repeated_last_draw = isset($params['repeated_last_draw'][0]) ? (int) $params['repeated_last_draw'][0] : null;
                $this->max_repeated_last_draw = isset($params['repeated_last_draw'][1]) ? (int) $params['repeated_last_draw'][1] : null;
            }
            if (isset($params['score_range']) && is_array($params['score_range'])) {
                $this->min_score = isset($params['score_range'][0]) ? (int) $params['score_range'][0] : null;
                $this->max_score = isset($params['score_range'][1]) ? (int) $params['score_range'][1] : null;
            }
            if (isset($params['temperature_distribution']) && is_array($params['temperature_distribution'])) {
                $temp = $params['temperature_distribution'];
                if (isset($temp['hot']) && is_array($temp['hot'])) {
                    $this->min_hot = isset($temp['hot'][0]) ? (int) $temp['hot'][0] : null;
                    $this->max_hot = isset($temp['hot'][1]) ? (int) $temp['hot'][1] : null;
                }
                if (isset($temp['neutral']) && is_array($temp['neutral'])) {
                    $this->min_neutral = isset($temp['neutral'][0]) ? (int) $temp['neutral'][0] : null;
                    $this->max_neutral = isset($temp['neutral'][1]) ? (int) $temp['neutral'][1] : null;
                }
                if (isset($temp['cold']) && is_array($temp['cold'])) {
                    $this->min_cold = isset($temp['cold'][0]) ? (int) $temp['cold'][0] : null;
                    $this->max_cold = isset($temp['cold'][1]) ? (int) $temp['cold'][1] : null;
                }
            }
        } elseif ($this->method === 'wheel') {
            if (isset($params['fixed_numbers']) && is_array($params['fixed_numbers'])) {
                $this->fixed_numbers = array_map('intval', $params['fixed_numbers']);
                sort($this->fixed_numbers);
            }
            if (isset($params['variable_numbers']) && is_array($params['variable_numbers'])) {
                $this->variable_numbers = array_map('intval', $params['variable_numbers']);
                sort($this->variable_numbers);
            }
            if (isset($params['wheel_size'])) {
                $this->wheel_size = (int) $params['wheel_size'];
            }
        } elseif ($this->method === 'reduced') {
            if (isset($params['reduced_parameters']['guarantee_hits'])) {
                $this->guarantee_hits = (int) $params['reduced_parameters']['guarantee_hits'];
            }
            if (isset($params['reduced_parameters']['guarantee_points'])) {
                $this->guarantee_points = (int) $params['reduced_parameters']['guarantee_points'];
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
            $rules['min_score'] = ['nullable', 'integer', 'min:0', 'max:1000'];
            $rules['max_score'] = ['nullable', 'integer', 'min:0', 'max:1000', 'gte:min_score'];
            $rules['min_hot'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_hot'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_hot'];
            $rules['min_neutral'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_neutral'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_neutral'];
            $rules['min_cold'] = ['nullable', 'integer', 'min:0', 'max:15'];
            $rules['max_cold'] = ['nullable', 'integer', 'min:0', 'max:15', 'gte:min_cold'];
        }

        // Regras condicionais para o método 'wheel'
        if ($this->method === 'wheel') {
            $rules['fixed_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . ($this->bet_size - 1),
            ];
            $rules['fixed_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->base_numbers),
            ];
            $rules['variable_numbers'] = [
                'required',
                'array',
                'min:1',
                'max:' . (count($this->base_numbers) - count($this->fixed_numbers)),
            ];
            $rules['variable_numbers.*'] = [
                'required',
                'integer',
                'distinct',
                'between:1,25',
                Rule::in($this->base_numbers),
                Rule::notIn($this->fixed_numbers),
            ];
            $rules['wheel_size'] = [
                'required',
                'integer',
                'min:1',
                'max:' . count($this->variable_numbers),
                'size:' . ($this->bet_size - count($this->fixed_numbers)),
            ];
        }

        // Regras condicionais para o método 'reduced'
        if ($this->method === 'reduced') {
            $rules['guarantee_hits'] = [
                'required',
                'integer',
                'min:' . $this->bet_size,
                'max:' . count($this->base_numbers),
            ];
            $rules['guarantee_points'] = [
                'required',
                'integer',
                'between:11,' . ($this->bet_size - 1),
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

            'min_repeated_last_draw.min' => 'O mínimo de repetidas não pode ser negativo.',
            'min_repeated_last_draw.max' => 'O mínimo de repetidas não pode exceder 15.',
            'max_repeated_last_draw.min' => 'O máximo de repetidas não pode ser negativo.',
            'max_repeated_last_draw.max' => 'O máximo de repetidas não pode exceder 15.',
            'max_repeated_last_draw.gte' => 'O máximo de repetidas deve ser maior ou igual ao mínimo.',

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

    public function toggleFixedNumber(int $number): void
    {
        if (!in_array($number, $this->base_numbers, true)) {
            $this->addError('fixed_numbers', "A dezena {$number} não está no grupo-base.");
            return;
        }

        if (in_array($number, $this->fixed_numbers, true)) {
            $this->fixed_numbers = array_values(array_filter($this->fixed_numbers, fn($n) => $n !== $number));
        } elseif (!in_array($number, $this->variable_numbers, true)) {
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
        } elseif (!in_array($number, $this->fixed_numbers, true)) {
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
        $this->guarantee_hits = null;
        $this->guarantee_points = null;

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
        $this->resetValidation('guarantee_hits');
        $this->resetValidation('guarantee_points');
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
        $this->guarantee_hits = null;
        $this->guarantee_points = null;

        $this->resetValidation('base_numbers');
        $this->resetValidation('fixed_numbers');
        $this->resetValidation('variable_numbers');
        $this->resetValidation('wheel_size');
        $this->resetValidation('guarantee_hits');
        $this->resetValidation('guarantee_points');
    }

    public function save(): void
    {
        if ($this->closing->status !== 'draft' && $this->closing->status !== 'failed') {
            session()->flash('error', 'Fechamentos já gerados ou concluídos não podem ter seus parâmetros alterados.');
            $this->redirectRoute('closings.show', $this->closing);
            return;
        }

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

            if ($this->method === 'wheel') {
                foreach ($this->fixed_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('fixed_numbers', "A dezena fixa {$number} não está no grupo-base.");
                        return;
                    }
                }

                foreach ($this->variable_numbers as $number) {
                    if (!in_array($number, $this->base_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} não está no grupo-base.");
                        return;
                    }
                }

                foreach ($this->variable_numbers as $number) {
                    if (in_array($number, $this->fixed_numbers, true)) {
                        $this->addError('variable_numbers', "A dezena variável {$number} também está nas dezenas fixas.");
                        return;
                    }
                }
            }

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
                if ($this->min_score !== null && $this->max_score !== null) {
                    $parameters['score_range'] = [(int) $this->min_score, (int) $this->max_score];
                }
                $tempDist = [];
                if ($this->min_hot !== null && $this->max_hot !== null) {
                    $tempDist['hot'] = [(int) $this->min_hot, (int) $this->max_hot];
                }
                if ($this->min_neutral !== null && $this->max_neutral !== null) {
                    $tempDist['neutral'] = [(int) $this->min_neutral, (int) $this->max_neutral];
                }
                if ($this->min_cold !== null && $this->max_cold !== null) {
                    $tempDist['cold'] = [(int) $this->min_cold, (int) $this->max_cold];
                }
                if (! empty($tempDist)) {
                    $parameters['temperature_distribution'] = $tempDist;
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
            } elseif ($this->method === 'reduced') {
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

            $this->closing->update([
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
                'notes' => $this->notes !== ''
                    ? $this->notes
                    : null,
            ]);

            session()->flash(
                'success',
                'Fechamento atualizado com sucesso.'
            );

            $this->redirectRoute('closings.show', $this->closing);
        } finally {
            $this->processing = false;
        }
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

            <a
                href="{{ route('closings.show', $closing) }}"
                class="transition hover:text-indigo-600"
            >
                Detalhes
            </a>

            <span>/</span>

            <span class="font-medium text-slate-700">
                Editar fechamento
            </span>
        </div>

        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
            Edição de Parâmetros
        </div>

        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900">
            Editar fechamento
        </h1>

        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 sm:text-base">
            Altere o grupo-base e os parâmetros do fechamento em rascunho antes da geração das apostas.
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

            <div class="mt-6 grid grid-cols-5 gap-2 sm:grid-cols-8 md:grid-cols-10">
                @foreach (range(1, 25) as $number)
                    @php
                        $selected = in_array($number, $base_numbers, true);
                    @endphp

                    <button
                        type="button"
                        wire:key="closing-number-{{ $number }}"
                        wire:click="toggleNumber({{ $number }})"
                        @class([
                            'flex aspect-square items-center justify-center rounded-xl border text-sm font-bold transition focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                            'border-indigo-600 bg-indigo-600 text-white shadow-md shadow-indigo-600/20' => $selected,
                            'border-slate-200 bg-slate-50 text-slate-700 hover:border-indigo-300 hover:bg-indigo-50 hover:text-indigo-700' => ! $selected,
                        ])
                    >
                        {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}
                    </button>
                @endforeach
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
                        <option value="reduced">Fechamento reduzido</option>
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

                        {{-- Faixa de Score (0 a 1000) --}}
                        <div class="pt-2 border-t border-indigo-100">
                            <label
                                for="min_score"
                                class="block text-sm font-semibold text-slate-700"
                            >
                                Faixa de Score / Pontuação (0 a 1.000 pts)
                            </label>
                            <p class="mt-1 text-xs text-slate-500">
                                Filtra apenas apostas cuja pontuação de qualidade esteja dentro da faixa desejada.
                            </p>
                            <div class="mt-2 flex gap-2">
                                <input
                                    id="min_score"
                                    type="number"
                                    wire:model="min_score"
                                    placeholder="Mín (ex: 600)"
                                    min="0"
                                    max="1000"
                                    step="10"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <input
                                    id="max_score"
                                    type="number"
                                    wire:model="max_score"
                                    placeholder="Máx (ex: 1000)"
                                    min="0"
                                    max="1000"
                                    step="10"
                                    class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                            </div>
                            @error('min_score')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                            @error('max_score')
                                <p class="mt-2 text-sm font-medium text-rose-600">
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        {{-- Distribuição de Temperatura --}}
                        <div class="pt-2 border-t border-indigo-100 space-y-3">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-700">
                                    Distribuição de Temperatura Recente
                                </h4>
                                <p class="mt-1 text-xs text-slate-500">
                                    Defina a quantidade de dezenas Quentes, Neutras e Frias por aposta (baseado nos últimos 20 concursos).
                                </p>
                            </div>

                            {{-- Quentes --}}
                            <div>
                                <label for="min_hot" class="flex items-center gap-1.5 text-xs font-semibold text-amber-700">
                                    <svg class="h-3.5 w-3.5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M12.395 2.553a1 1 0 00-1.45-.385c-.345.23-.614.558-.822.88-.527.82-1.124 1.93-1.64 3.12a20.08 20.08 0 01-1.393 2.748c-.5.845-.964 1.57-1.353 2.052a5.75 5.75 0 00-.737 1.258A6.002 6.002 0 0010 18a6.002 6.002 0 005.894-4.873c.07-.37.106-.75.106-1.127 0-1.197-.333-2.316-.913-3.268a15.733 15.733 0 00-1.89-2.544 19.86 19.86 0 00-.802-.835zM10 16a4 4 0 01-3.92-3.178c.036-.08.08-.16.13-.24.32-.51.72-1.17 1.18-1.95A18.09 18.09 0 008.66 8.01c.42-.98.88-1.87 1.34-2.58.3-.06.6.01.83.21.36.31.75.7 1.15 1.17.48.56.96 1.23 1.38 1.99.45.81.79 1.69.79 2.61A4.002 4.002 0 0110 16z" clip-rule="evenodd" />
                                    </svg>
                                    Dezenas Quentes (🔥 Mín/Máx)
                                </label>
                                <div class="mt-1.5 flex gap-2">
                                    <input
                                        id="min_hot"
                                        type="number"
                                        wire:model="min_hot"
                                        placeholder="Mín (ex: 4)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                    <input
                                        id="max_hot"
                                        type="number"
                                        wire:model="max_hot"
                                        placeholder="Máx (ex: 6)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>
                                @error('min_hot') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                @error('max_hot') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Neutras --}}
                            <div>
                                <label for="min_neutral" class="flex items-center gap-1.5 text-xs font-semibold text-slate-700">
                                    <span class="inline-block h-2 w-2 rounded-full bg-slate-400"></span>
                                    Dezenas Neutras (⚖️ Mín/Máx)
                                </label>
                                <div class="mt-1.5 flex gap-2">
                                    <input
                                        id="min_neutral"
                                        type="number"
                                        wire:model="min_neutral"
                                        placeholder="Mín (ex: 4)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                    <input
                                        id="max_neutral"
                                        type="number"
                                        wire:model="max_neutral"
                                        placeholder="Máx (ex: 7)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>
                                @error('min_neutral') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                @error('max_neutral') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Frias --}}
                            <div>
                                <label for="min_cold" class="flex items-center gap-1.5 text-xs font-semibold text-sky-700">
                                    <svg class="h-3.5 w-3.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="2" x2="12" y2="22"></line>
                                        <line x1="2" y1="12" x2="22" y2="12"></line>
                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                        <line x1="19.07" y1="4.93" x2="4.93" y2="19.07"></line>
                                    </svg>
                                    Dezenas Frias (❄️ Mín/Máx)
                                </label>
                                <div class="mt-1.5 flex gap-2">
                                    <input
                                        id="min_cold"
                                        type="number"
                                        wire:model="min_cold"
                                        placeholder="Mín (ex: 3)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                    <input
                                        id="max_cold"
                                        type="number"
                                        wire:model="max_cold"
                                        placeholder="Máx (ex: 5)"
                                        min="0"
                                        max="15"
                                        class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                </div>
                                @error('min_cold') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                                @error('max_cold') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                            </div>
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

                {{-- Campos para o método Reduced --}}
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

    {{-- Ações de Submissão --}}
    <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">
        <a
            href="{{ route('closings.show', $closing) }}"
            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
        >
            Cancelar
        </a>

        <button
            type="submit"
            wire:click="save"
            wire:loading.attr="disabled"
            wire:target="save"
            class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <svg
                wire:loading
                wire:target="save"
                class="h-4 w-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>

            <svg
                wire:loading.remove
                wire:target="save"
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
                Salvar Alterações
            </span>

            <span wire:loading wire:target="save">
                Salvando alterações...
            </span>
        </button>
    </div>
</div>
