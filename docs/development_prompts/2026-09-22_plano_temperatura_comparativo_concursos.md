# Plano de Implementação - Temperatura Contextual por Concurso no Comparativo

**Data de Criação:** 22/09/2026  
**Fase:** Ajuste Estatístico Dinâmico por Sorteio - Lotofácil Comparativo  
**Status:** Aguardando Aprovação  

## 1. Visão Geral e Contexto

Na rota `/dashboard/analises-lotofacil/comparativo`, o usuário analisa um comparativo dos últimos N concursos (10, 25, 50 ou 100). Atualmente, a classificação de cada dezena sorteada como "quente" (vermelho), "neutra" (azul/índigo) ou "fria" (azul claro/sky) é aplicada de maneira estática a todas as linhas da tabela, pois é calculada uma única vez para o período todo e repassada indistintamente para todas as linhas da matriz.

Como a temperatura de uma dezena é uma grandeza dinâmica que varia a cada sorteio, o objetivo é que cada linha da matriz reflita o estado térmico em que as dezenas se encontravam no momento daquele concurso específico.

---

## 2. Diagnóstico Técnico

- **Classe:** `App\Services\LotofacilStatisticsService`
- **Método atual:** `getContestsComparisonAnalysis(int $limit = 25)`
  - Carrega apenas `$limit + 1` resultados (`$rawResults`).
  - Executa `$temperatureMap = $this->calculatePeriodTemperatures($results);` uma única vez.
  - No método `buildContestsComparisonList()`, itera sobre os sorteios e repassa `$temperatureMap[$num]['temperature']` para todos eles.
- **Solução proposta:**
  - Definir uma janela móvel de contexto histórico (por padrão, os **20 concursos anteriores** ao sorteio avaliado, com fallback suave quando o histórico inicial for menor).
  - Ajustar a consulta para carregar em lote os registros necessários sem causar consultas adicionais N+1 (`$limit + $historyWindowSize + 1`).
  - No loop de construção da lista de concursos (`buildContestsComparisonList`), para cada concurso no índice `$idx`:
    - Extrair a fatia de concursos que serve de histórico para aquele concurso (ex.: os 20 concursos imediatamente anteriores ao concurso avaliado: `slice($idx + 1, 20)`).
    - Calcular a temperatura relativa das dezenas com base nas frequências dessa fatia de histórico.
    - Atribuir a temperatura contextual calculada à grade do concurso em questão (`$numbersGrid[$num]['temperature']`).
  - Manter o `$temperatureMap` consolidado no retorno da análise para alimentar o card de resumo do último sorteio e as métricas agregadas do topo.

---

## 3. Impacto das Alterações

- **Performance:** Nenhuma consulta extra ao banco de dados; todos os concursos necessários para a janela são trazidos em uma única consulta indexada (`orderBy('contest_number', 'desc')`). O processamento em memória de frequências de fatias de até 20 itens para cada linha é instantâneo (abaixo de alguns milissegundos).
- **Compatibilidade:** A estrutura do array de retorno de `getContestsComparisonAnalysis()` permanece idêntica (nenhuma chave é removida ou renomeada). A view Blade já consome `$numData['temperature']` de cada concurso individualmente, então a interface passará a exibir as cores dinâmicas automaticamente sem quebras de layout.
- **Cache:** O resultado de `getContestsComparisonAnalysis` continua protegido por cache de 6 horas com invalidação no botão "Recalcular".

---

## 4. Arquivos Envolvidos

### Service
- `[MODIFY]` [LotofacilStatisticsService.php](file:///home/jean/ai_projects/loterias/app/Services/LotofacilStatisticsService.php)
  - Modificar `getContestsComparisonAnalysis()` para buscar a janela estendida de histórico em uma única consulta.
  - Modificar `buildContestsComparisonList()` para calcular a temperatura pontual de cada concurso usando a fatia de histórico anterior correspondente.
  - Extrair ou reutilizar a lógica de pontuação de frequências e limiares (`calculatePeriodTemperatures()`).

### Testes
- `[MODIFY]` [LotofacilContestComparisonTest.php](file:///home/jean/ai_projects/loterias/tests/Feature/LotofacilContestComparisonTest.php)
  - Adicionar teste unitário/feature garantindo que concursos diferentes no comparativo possuem classificações de temperatura distintas para uma mesma dezena conforme o histórico muda ao longo do tempo.

### Documentação / Diretório de Prompts
- `[NEW]` [2026-09-22_plano_temperatura_comparativo_concursos.md](file:///home/jean/ai_projects/loterias/docs/development_prompts/2026-09-22_plano_temperatura_comparativo_concursos.md)
  - Cópia do plano no diretório do projeto conforme exigido nas diretrizes globais do usuário.

---

## 5. Plano de Verificação

1. **Testes Automatizados:**
   - Executar `php artisan test --compact tests/Feature/LotofacilContestComparisonTest.php`.
   - Executar a suite completa de testes `php artisan test --compact` garantindo 0 regressões.
2. **Formatação de Código:**
   - Executar `vendor/bin/pint --format agent` nos arquivos alterados.
