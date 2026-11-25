# Status Atual do Projeto Loterias

**Última atualização:** 25/11/2026 (referência: commit `6f0e00e`, de 25/11/2026)

Este documento compara o que está implementado no código com a especificação mestre ([`prompt_base.MD`](prompt_base.MD)) e lista o que ainda falta. O roteiro original em etapas ([`prompt_4_fase.md`](prompt_4_fase.md)) está superado e serve apenas como registro histórico.

---

## 1. Visão geral

- **Stack:** PHP 8.3, Laravel 13, Livewire 3 + Volt, Tailwind 4, Vite, Breeze. SQLite no ambiente local sem Docker; MariaDB no Docker e em produção.
- **Testes:** 161 testes PHPUnit (3.488 asserções), todos passando.
- **Histórico de desenvolvimento:** 44 commits entre 12/08/2026 e 25/11/2026. Planos e walkthroughs das features de setembro estão em [`development_prompts/`](development_prompts/).

### 1.1 Modelo de dados

| Tabela / Model | Papel |
| --- | --- |
| `users` / `User` | Usuários, com `share_code` para conexões |
| `bets` / `Bet` | Apostas (dezenas em JSON), avulsas ou ligadas a um fechamento via `closing_id`; status, concurso, acertos e observação (`notes`) |
| `closings` / `Closing` | Fechamentos: método, grupo-base, parâmetros (JSON), `planned_bets`, status, concurso, data do sorteio e avisos da última geração (`generation_warnings`) |
| `historical_results` / `HistoricalResult` | Série histórica: dezenas, hash da combinação, ganhadores e rateios, score e número do ciclo |
| `lotofacil_settings` / `LotofacilSetting` | Preços por tamanho de aposta e prêmios fixos, por usuário |
| `consolidated_audits` / `ConsolidatedAudit` | Resultados das auditorias de cobertura processadas em fila |
| `connections` / `Connection` | Conexões (amizade) entre usuários |
| `closing_user`, `bet_user` | Compartilhamento de fechamentos e apostas (bolão) |

> Diferente do previsto em `prompt_4_fase.md`, não existe a tabela pivô `closing_bet`: cada aposta pertence a no máximo um fechamento.

### 1.2 Ciclo de vida do fechamento

`draft` → `processing` → `completed` → `placed` (concurso e data informados) → `checked` (conferido contra o resultado). Em caso de erro, `failed`.

- Fechamentos `completed` podem ser editados. Salvar apaga as apostas, limpa os avisos e volta o fechamento para `draft`.
- Ações disponíveis: refazer apostas, excluir apostas individualmente ou em lote (com renumeração), completar apostas faltantes até `planned_bets` e alertar dezenas do grupo-base sem cobertura.
- Apostas e fechamentos `placed`/`checked` ficam bloqueados para edição e exclusão.
- A página do fechamento mostra os avisos da geração (apostas aceitas fora dos filtros) e a **chance real de premiação** (ver 2.2).

### 1.3 Peças centrais do cálculo

| Classe | Responsabilidade |
| --- | --- |
| `Betting/ClosingGenerator` | Orquestra a geração: valida, escolhe o gerador, grava as apostas e os avisos devolvidos pelo gerador |
| `Betting/Generators/*` | Métodos integral, reduzido, roda, aleatório e equilibrado (`BetGeneratorInterface`) |
| `Betting/HistoricalDrawFilter` | Identifica combinações iguais a concursos passados; usado dentro dos geradores para substituí-las |
| `Betting/ClosingProbabilityService` | Probabilidade exata de premiação de um conjunto de apostas (enumeração dos 3.268.760 sorteios) |
| `BaseNumbersSuggestionService` | Sugestão estatística do grupo-base (repetidas do último concurso + dezenas novas) |
| `LotofacilStatisticsService` | Frequências, atraso, ciclos, temperatura (regra única), cotas de repetição, sequências consecutivas |
| `BetScoringService` | Score de aderência a padrões históricos (0–1000) |
| `LotteryPrizeCalculatorService` | Custo e prêmios de apostas de 15 a 20 dezenas (cálculo combinatório) |
| `StatisticsCache` | Cache versionado das estatísticas: qualquer mudança na série histórica invalida tudo |

---

## 2. Cobertura da especificação (`prompt_base.MD`)

Legenda: ✅ implementado · 🟡 parcial · ❌ não implementado

| Item | Tema | Status | Onde / Observação |
| ---: | --- | :---: | --- |
| 1 | Validação de dezenas, apostas e parâmetros | 🟡 | `StoreBetRequest`, `StoreClosingRequest`, `validate()` dos geradores e `ClosingGenerator`. Não há validação de semente, pois sementes não existem. |
| 2 | Geração e enumeração de combinações | ✅ | `Betting/Math/Combinatorics` e `phpexperts/combinatorics`, sob demanda (PHP Generators) |
| 3 | Probabilidade hipergeométrica de aposta simples | 🟡 | `ClosingProbabilityService::singleBetProbability()` e `LotofacilStatisticsService::repetitionProbability()` fazem o cálculo; não há tela própria com P(X=k) |
| 4 | Apostas equivalentes C(m,15) e custo | 🟡 | Prêmios de apostas múltiplas calculados por C(h,k)·C(m−h,15−k); custo por tamanho em `getBetCost()`; não há módulo explicativo |
| 5 | Fechamento integral | ✅ | `IntegralBetGenerator` |
| 6 | Interseção grupo-base × sorteio | ✅ | Conferência e `checkBaseNumbersCoverage()` |
| 7 | Fechamento reduzido | ✅ | `ReducedBetGenerator` + `SetCoveringService` (heurística gulosa, limite de 23 dezenas); concursos passados são excluídos dos candidatos, preservando a garantia |
| 8 | Modelo de cobertura combinatória | 🟡 | Cenários = subconjuntos do grupo-base; não há pesos por cenário |
| 9 | Set covering (mínimo e por orçamento) | 🟡 | Apenas heurística gulosa com `budget`; sem solução exata |
| 10 | Programação inteira | ❌ | — |
| 11 | Heurística gulosa | ✅ | `SetCoveringService` |
| 12 | Busca local | ❌ | — |
| 13 | Simulated annealing | ❌ | — |
| 14 | Algoritmo genético | ❌ | — |
| 15 | Programação por restrições | 🟡 | `OptimizerService` aplica filtros rígidos e limite de interseção entre apostas, mas sem solver |
| 16 | Sistemas de roda | 🟡 | `WheelBetGenerator` (fixas + variáveis); não calcula cobertura de pares e trincas |
| 17 | Pares/ímpares e faixas numéricas | ✅ | `BalancedBetGenerator`, `OptimizerService`, `BetScoringService` |
| 18 | Estatísticas históricas | ✅ | `LotofacilStatisticsService`: frequência, atraso, ciclos, temperatura (regra única e descritiva), repetição, score |
| 19 | Geração ponderada / filtros heurísticos | 🟡 | Pontuação no `BalancedBetGenerator` e no `SmartRandomBetService`; opção "Distribuir apostas" (mínima sobreposição); não há pesos configuráveis pelo usuário |
| 20 | Cálculo exato de cobertura | 🟡 | Cobertura por faixa (≥11 a 15) exata sobre o universo completo em `ClosingProbabilityService`; auditoria de garantia exata dentro do grupo-base em `CoverageAuditorService`; não lista os cenários de menor pontuação |
| 21 | Garantia condicional | 🟡 | A auditoria reporta a condição ("garante X se acertar Y no grupo-base"); não há verificador genérico de condições |
| 22 | Probabilidade de pelo menos uma premiação | ✅ | Cálculo exato em `ClosingProbabilityService` (até 150 apostas), exibido no fechamento |
| 23 | Simulação de sorteios | ✅ | `MonteCarloSimulationService` + backtesting histórico em `/simulacoes`; **sem semente e sem intervalo de confiança** |
| 24 | Comparação de algoritmos | ❌ | — |
| 25 | Custo e orçamento | ✅ | Configuração por usuário, custo do fechamento, `/financeiro` (ROI por concurso, fechamento e aposta) |
| 26 | Relatórios e transparência | 🟡 | Relatório da sugestão de grupo-base, avisos de filtros relaxados, chance real de premiação, auditoria e impressão; não há relatório unificado do fechamento |
| 27 | Testes | ✅ | 161 testes (Feature e Unit) |
| 28 | Desempenho | 🟡 | Geração sob demanda, bitmasks e enumeração exata (≈ 0,1 s por aposta); a geração de fechamentos roda de forma síncrona, sem progresso nem cancelamento |
| 29 | Classificação Exato / Heurístico / Simulado | 🟡 | A chance de premiação é rotulada como exata; ciclo, atraso e temperatura são rotulados como heurísticos/descritivos; ainda não é sistemático em todas as telas |
| 30 | Regras de comunicação | 🟡 | Score e temperatura trazem aviso de que não alteram a probabilidade; o otimizador e os textos antigos ainda falam em "jogos mais fortes" |

**Requisito transversal 3.11 (determinismo por semente aleatória):** ❌ Nenhum gerador, simulador ou otimizador aceita semente, então os resultados não são reproduzíveis.

### 2.1 Geração equilibrada (método mais usado)

1. **Grupo-base (opcional):** `BaseNumbersSuggestionService` combina repetidas do último concurso com dezenas novas. O tamanho pedido é sempre respeitado: as repetições são ajustadas para a faixa possível (as novas só podem vir das 10 dezenas fora do último concurso) e o relatório informa o ajuste. Paridade e moldura são reequilibradas a cada dezena escolhida. A temperatura **não** é usada na montagem da base.
2. **Cotas de repetição:** distribuídas pela probabilidade hipergeométrica de cada quantidade de repetidas (ex.: 8/9/10 repetidas → 30/41/29 de 100 apostas).
3. **Filtros:** paridade, soma, primos, Fibonacci, repetidas, score e temperatura. Quando não há combinações suficientes, a tolerância cresce até ±3 e, em último caso, a aposta é gerada sem filtros. **Toda aposta aceita fora da faixa recebe uma observação** e o fechamento mostra um resumo.
4. **Distribuir apostas (`spread_bets`):** compara até 25 combinações válidas por aposta e escolhe a de menor sobreposição com as já geradas. Ligado por padrão em fechamentos novos.
5. **Concursos passados:** combinações iguais a sorteios anteriores são descartadas e substituídas dentro do gerador.

### 2.2 Chance real de premiação

O quadro do fechamento mostra, para cada faixa (≥11 a 15), a probabilidade de pelo menos uma aposta atingi-la no próximo sorteio, no formato "1 em N", com:

- o número médio de apostas premiadas por sorteio;
- a média de dezenas em comum entre as apostas;
- a referência "sem sobreposição" (a mesma quantidade de apostas simples, como se não tivessem dezenas em comum).

O cálculo é exato, independe da série histórica e fica em cache por conjunto de apostas.

---

## 3. Auditoria estatística (25/11/2026)

Verificações feitas contra a série real (3.776 concursos) e por enumeração exata:

- **Integridade dos dados:** concursos 1 a 3776 sem lacunas, dezenas válidas, hashes conferidos, nenhum sorteio repetido. Nenhuma das 287 apostas salvas era igual a um concurso passado.
- **Distribuições conferem com a teoria:** paridade, moldura/centro e repetidas do concurso anterior seguem a distribuição hipergeométrica; a soma tem média 195 e desvio ≈ 18; a frequência das dezenas é compatível com sorteio uniforme (χ² = 24,9 com 24 graus de liberdade).
- **Nenhum critério tem poder preditivo comprovado.** Em teste sequencial sobre 3.726 concursos, todas as categorias saíram em ≈ 60% (o esperado para qualquer dezena): quentes, neutras e frias; repetidas e não repetidas; faltantes do ciclo; dezena excluída pela sugestão. O único desvio (atraso ≥ 3 concursos: 62%) enfraquece na segunda metade da série e, mesmo se real, vale ≈ +0,03 acerto por aposta.
- **O que muda o resultado é a distribuição das apostas.** O número médio de apostas premiadas é o mesmo em qualquer configuração (≈ 1,06 a cada 10 apostas simples) e a chance de 14 e 15 pontos não muda. Já a chance de **pelo menos um** prêmio depende da sobreposição: 10 apostas em base de 21 dezenas com os filtros usuais ficam em ≈ 45–53%; espalhadas pelas 25 dezenas, ≈ 71–74%.

### 3.1 Correções aplicadas em novembro de 2026

| Área | Correção |
| --- | --- |
| Prêmios | Tabela de apostas múltiplas trocada pelo cálculo combinatório (18/19/20 dezenas com 12 acertos superestimavam os prêmios de 11 pontos) |
| Sugestão de grupo-base | Tamanho pedido sempre respeitado, validação no servidor, equilíbrio recalculado a cada dezena |
| Concursos passados | Filtro dentro dos geradores (substitui em vez de descartar); reduzido mantém a garantia; "aleatório inteligente" sem cache desatualizado |
| Gerador equilibrado | Avisos de filtros relaxados; cotas de repetição pela probabilidade real; opção de mínima sobreposição |
| Score | Escala 0–1000 real (máximo bruto de 900 pontos), faixa da soma 175–215, sequências consecutivas sem depender de cache, ciclo e atraso rotulados como heurísticos; o score de cada concurso usa apenas concursos anteriores a ele (`ScoringPatternTracker`) |
| Temperatura | Regra única (frequência no período, atraso só desempata) em gerador, score e comparativo |
| Cache | `StatisticsCache` versionado; seeder invalida o cache |

---

## 4. Funcionalidades além da especificação

- Conferência de apostas e fechamentos efetivados (`placed` → `checked`) com cálculo de prêmios.
- Importação da série histórica da planilha da Caixa (`app:import-historical-results`) e CRUD de sorteios.
- Cálculo de score e ciclo dos concursos (`lotofacil:calculate-scores`).
- Dashboard de análises e comparativo de concursos com temperatura por concurso (janela móvel de 20 concursos).
- Sugestão de grupo-base por análise estatística, com relatório dos critérios e dos ajustes.
- Conexões entre usuários e compartilhamento de fechamentos e apostas (bolão).
- Impressão de fechamentos.
- Ambiente Docker de desenvolvimento e de produção ([`docker-prod.md`](docker-prod.md)).

---

## 5. Pontos de atenção

1. **Série histórica sem controle de acesso:** qualquer usuário autenticado pode criar, editar e excluir sorteios em `/sorteios`. Como os dados são globais, isso afeta a conferência, as estatísticas e o financeiro de todos. Falta um papel de administrador.
2. **Autorização espalhada:** não há Policies; as verificações de dono e compartilhamento ficam no `mount()` de cada página Volt.
3. **Geração síncrona:** fechamentos grandes (integral com 20 ou mais dezenas = 15.504 ou mais apostas) rodam na requisição web e podem estourar o timeout. Só a auditoria consolidada usa fila.
4. **Faixas de score salvas:** fechamentos criados antes da nova escala (0–1000 real) podem ter `score_range` calibrado na escala antiga; o total agora sai cerca de 11% maior para os mesmos critérios.
5. **Monte Carlo:** a auditoria por amostragem marca "garantia confirmada" com base em amostra, e a simulação financeira usa prêmios fixos estimados para 14 e 15 pontos.
6. **Após importar a série histórica:** rodar `php artisan lotofacil:calculate-scores` (o seeder grava sem calcular score e ciclo). O `DatabaseSeeder` cria apenas o usuário de desenvolvimento; a série exige `php artisan db:seed --class=HistoricalResultSeeder`.
7. **Arquivos soltos:** `parse_xlsx.py` na raiz parece sobra de desenvolvimento; `prompt_base.MD` termina com um resto de colagem ("Mensagem para o Agente / Adicionar / Ferramentas").

---

## 6. Próximos passos sugeridos

Em ordem de prioridade:

1. **Segurança e consistência**
   - [ ] Restringir o CRUD de sorteios a administradores.
   - [ ] Criar Policies para `Closing`, `Bet` e `HistoricalResult`.
2. **Reprodutibilidade e transparência** (Itens 3.11, 26, 29 e 30)
   - [ ] Aceitar e persistir semente aleatória em geradores, otimizador e simulações.
   - [ ] Rotular sistematicamente cada resultado como Exato, Ótimo comprovado, Viável, Heurístico ou Simulado.
   - [ ] Adicionar intervalo de confiança de 95% ao Monte Carlo e deixar de chamar amostragem de "garantia confirmada".
   - [ ] Criar um relatório de transparência do fechamento (método, parâmetros, semente, cobertura, garantias e limitações).
   - [ ] Revisar os textos do otimizador ("jogos mais fortes").
3. **Cálculos matemáticos** (Itens 3, 4 e 20)
   - [ ] Tela com P(X=k) de uma aposta simples e apostas equivalentes C(m,15) com custo.
   - [ ] Distribuição completa da melhor pontuação e cenários de menor pontuação.
4. **Desempenho** (Item 28)
   - [ ] Mover a geração de fechamentos grandes para jobs, com progresso e cancelamento.
5. **Otimização avançada** (Itens 12 a 14 e 24)
   - [ ] Busca local sobre a solução gulosa e sobre a distribuição das apostas (maximizar a chance de pelo menos um prêmio).
   - [ ] Simulated annealing.
   - [ ] Algoritmo genético.
   - [ ] Módulo de comparação entre métodos (mesmo grupo-base, orçamento e semente), usando `ClosingProbabilityService`.
6. **Rodas e cobertura** (Itens 8 e 16)
   - [ ] Calcular cobertura de pares e trincas nas rodas.
   - [ ] Pesos por cenário.
