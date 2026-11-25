<?php

namespace App\Services\Betting;

/**
 * Classificação obrigatória de todo resultado produzido pelo sistema (item 29).
 */
enum ResultClassification: string
{
    case Exact = 'exact';
    case ProvenOptimal = 'proven_optimal';
    case Feasible = 'feasible';
    case Heuristic = 'heuristic';
    case Simulated = 'simulated';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exato',
            self::ProvenOptimal => 'Ótimo comprovado',
            self::Feasible => 'Viável',
            self::Heuristic => 'Heurístico',
            self::Simulated => 'Simulado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Exact => 'Obtido pela análise de todos os cenários relevantes ou por prova matemática.',
            self::ProvenOptimal => 'Solução cuja impossibilidade de melhoria foi demonstrada pelo método de otimização.',
            self::Feasible => 'Atende às restrições, mas sem prova de que é a melhor solução.',
            self::Heuristic => 'Encontrado por busca aproximada (gulosa, local, aleatória ou por pontuação), sem garantia de ser o melhor.',
            self::Simulated => 'Estimado por amostragem aleatória, sem enumeração completa dos cenários.',
        };
    }

    /**
     * Classes Tailwind do selo exibido na interface.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Exact => 'bg-emerald-100 text-emerald-800 border-emerald-200',
            self::ProvenOptimal => 'bg-teal-100 text-teal-800 border-teal-200',
            self::Feasible => 'bg-sky-100 text-sky-800 border-sky-200',
            self::Heuristic => 'bg-amber-100 text-amber-800 border-amber-200',
            self::Simulated => 'bg-violet-100 text-violet-800 border-violet-200',
        };
    }
}
