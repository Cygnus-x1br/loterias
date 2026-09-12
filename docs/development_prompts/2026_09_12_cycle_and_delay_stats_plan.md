# Inclusão de Ciclo de Dezenas e Atraso na Análise Estatística

## Contexto
O algoritmo de geração do grupo base atualmente considera:
- Frequência histórica
- Paridade (Pares/Ímpares)
- Proporção Moldura/Centro

O usuário solicitou a inclusão das métricas de **Atraso Atual** e **Ciclo de Dezenas**, incluindo uma projeção matemática baseada no andamento do ciclo atual em relação à média histórica para determinar o peso das dezenas faltantes do ciclo.

## Proposta de Algoritmo

### 1. Projeção do Ciclo de Dezenas
A ideia é calcular o "peso" ou a cota de dezenas faltantes que devem ser priorizadas, baseando-se na urgência do fechamento do ciclo.

- `Média do Ciclo`: ~4.7 concursos (fornecido por `average_cycle_length`).
- `Concursos no Ciclo Atual`: `contests_in_current_cycle`.
- `Concursos Restantes Estimados`: `max(1, Média do Ciclo - Concursos no Ciclo Atual)`.
- **Projeção de Dezenas a Sair Hoje**: `Quantidade de Dezenas Faltantes / Concursos Restantes Estimados`.

Para integrar isso ao algoritmo atual (que é baseado em pontuação - *score*):
Se a projeção indicar que precisamos de, por exemplo, 3 dezenas do ciclo para fechar (ou progredir conforme a média), daremos um **grande bônus de pontuação (score)** para todas as dezenas faltantes do ciclo. Quanto mais "atrasado" estiver o ciclo (concursos atuais >= média), maior será o bônus, forçando o algoritmo a selecionar essas dezenas para o grupo base.

### 2. Atraso Atual das Dezenas
As dezenas que não saíram no último concurso têm um atraso de no mínimo 1. Algumas podem estar atrasadas há 3, 4 ou mais concursos.
- A métrica de Atraso será convertida em pontos. Por exemplo, `score += atraso * 2`.
- Isso significa que uma dezena atrasada há 4 concursos ganha +8 pontos, aumentando sua chance de ser escolhida no grupo de dezenas inéditas, respeitando ainda os filtros de moldura e paridade.

### 3. Alterações no Relatório da Interface
O array `$statisticalReport` será expandido para mencionar o Atraso e o Ciclo.
- No bloco de "Dezenas Inéditas", o texto passará a mencionar: *"Foram selecionadas X novas dezenas baseando-se na frequência geral, no maior atraso atual, e na projeção de fechamento do ciclo (Y dezenas faltantes no ciclo atual)..."*

## Proposed Changes

### [MODIFY] [create.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/create.blade.php)
### [MODIFY] [edit.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/edit.blade.php)

1. Adicionar chamadas aos métodos de estatística:
```php
$cycleAnalysis = $service->getDecadesCycleAnalysis();
$delayAnalysis = collect($service->getCurrentDelayAnalysis())->keyBy('number');
```

2. No cálculo da Projeção de Ciclo:
```php
$missingNumbers = $cycleAnalysis['missing_numbers'] ?? [];
$avgCycle = $cycleAnalysis['average_cycle_length'] ?: 4.7;
$currentContests = $cycleAnalysis['contests_in_current_cycle'] ?: 1;
$estimatedRemaining = max(1, $avgCycle - $currentContests);
$cycleBoost = count($missingNumbers) / $estimatedRemaining; 
// Se faltam muitas dezenas e o ciclo está acabando, o boost é alto.
```

3. No loop de `foreach ($nonDrawnNumbers as $num)` (cálculo de pontuação das inéditas):
```php
$delay = $delayAnalysis->get($num)['delay'] ?? 1;
$score += $delay * 2; // Bônus de atraso

if (in_array($num, $missingNumbers)) {
    // Bônus do ciclo. Multiplicamos por 3 para ter peso no score.
    $score += ($cycleBoost * 3); 
}
```

4. Atualizar o texto do `criteria['new']` no `$this->statisticalReport` para incluir que os novos parâmetros foram aplicados.

> [!NOTE]
> Essa abordagem de bônus garante que as dezenas do ciclo e as dezenas mais atrasadas subam para o topo do ranking de escolha das inéditas, mas ainda sejam balanceadas caso a paridade (par/ímpar) ou proporção moldura/centro fiquem muito distorcidas.

## Open Questions
- Você concorda em usar o sistema de "Bônus de Pontuação" (boost) para o atraso e o ciclo, mantendo-os em harmonia com os filtros de paridade/moldura? Ou prefere que as dezenas do ciclo sejam forçadamente incluídas no grupo base ignorando a moldura/paridade?

## Verification Plan
1. Executar a simulação na interface (`create.blade.php`).
2. Verificar se o sistema não retorna erros de execução ao chamar as novas rotinas estatísticas.
3. Analisar o novo relatório na tela, validando as informações textuais.
