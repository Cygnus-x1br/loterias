# Status Atual do Projeto Loterias

**Última atualização:** 25/11/2026 (referência: estado do `main` em 25/11/2026)

Este documento compara o que está implementado no código com a especificação mestre ([`prompt_base.MD`](prompt_base.MD)) e lista o que ainda falta. O roteiro original em etapas ([`prompt_4_fase.md`](prompt_4_fase.md)) está superado e serve apenas como registro histórico.

---

## 1. Visão geral

- **Stack:** PHP 8.3, Laravel 13, Livewire 3 + Volt, Tailwind 4, Vite, Breeze. SQLite no ambiente local sem Docker; MariaDB no Docker e em produção.
- **Testes:** 249 testes PHPUnit, todos passando.
- **Histórico de desenvolvimento:** 49 commits entre 12/08/2026 e 25/11/2026. Planos e walkthroughs das features de setembro estão em [`development_prompts/`](development_prompts/).

### 1.1 Modelo de dados

| Tabela / Model | Papel |
| --- | --- |
| `users` / `User` | Usuários, com `share_code` para conexões |
| `bets` / `Bet` | Apostas (dezenas em JSON), avulsas ou ligadas a um fechamento via `closing_id`; status, concurso, acertos e observação (`notes`) |
| `closings` / `Closing` | Fechamentos: método, grupo-base, parâmetros (JSON), `planned_bets`, status, concurso alvo (`target_contest_number`), concurso apostado (`contest_number`), data do sorteio e avisos da última geração (`generation_warnings`) |
| `historical_results` / `HistoricalResult` | Série histórica: dezenas, hash da combinação, ganhadores e rateios, score e número do ciclo |
| `lotofacil_settings` / `LotofacilSetting` | Preços por tamanho de aposta e prêmios fixos, por usuário |
| `consolidated_audits` / `ConsolidatedAudit` | Resultados das auditorias de cobertura processadas em fila |
| `number_indications` / `NumberIndication` | Dezenas mais e menos indicadas registradas antes de cada concurso e conferidas depois (acompanhamento do critério heurístico) |
| `connections` / `Connection` | Conexões (amizade) entre usuários |
| `closing_user`, `bet_user` | Compartilhamento de fechamentos e apostas (bolão) |

> Diferente do previsto em `prompt_4_fase.md`, não existe a tabela pivô `closing_bet`: cada aposta pertence a no máximo um fechamento.

### 1.2 Ciclo de vida do fechamento

`draft` → `processing` → `completed` → `placed` (concurso e data informados) → `checked` (conferido contra o resultado). Em caso de erro, `failed`.

- Todo fechamento tem um **concurso alvo**, preenchido por padrão com o próximo concurso. Ele é diferente do concurso apostado (`contest_number`), que só é gravado ao marcar "Apostado" e é o que o financeiro considera.
- Fechamentos `completed` podem ser editados. Salvar apaga as apostas, limpa os avisos e volta o fechamento para `draft`.
- **Repetir para outro concurso:** cria uma cópia do fechamento (configuração e, opcionalmente, as mesmas apostas) para outro concurso, sem herdar concurso apostado, conferência nem compartilhamento. Disponível ao dono em fechamentos concluídos, apostados e conferidos (`ClosingGenerator::repeatForContest`).
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
| `BaseNumbersSuggestionService` | Sugestão estatística do grupo-base (repetidas do último concurso + dezenas novas), com dupla avaliação: evita as dezenas menos indicadas |
| `NumberIndicationService` | Registra as dezenas mais e menos indicadas antes de cada concurso, confere após o sorteio e resume o acompanhamento |
| `ScoringPatternTracker` | Contagem incremental de dezenas, pares, trios e sequências para o score sem informação de concursos futuros |
| `LotofacilStatisticsService` | Frequências, atraso, ciclos, temperatura (regra única), cotas de repetição, sequências consecutivas |
| `BetScoringService` | Score de aderência a padrões históricos (0–1000) |
| `LotteryPrizeCalculatorService` | Custo e prêmios de apostas de 15 a 20 dezenas (cálculo combinatório) |
| `StatisticsCache` | Cache versionado das estatísticas: qualquer mudança na série histórica invalida tudo |

### 1.4 Tela de novo fechamento

- Os quadros das dezenas mostram uma barra verde na parte inferior nas dezenas sorteadas no último concurso, além do indicador de temperatura.
- O painel "Referência para planejamento" traz as estatísticas do último concurso e as dezenas com atraso (concursos sem sair, do maior para o menor), sinalizadas como referência descritiva.
- Entre as dezenas atrasadas, as que ainda faltam para fechar o ciclo atual ganham uma borda índigo (com legenda e tooltip); sem borda quando o ciclo está completo.
- O concurso alvo vem preenchido com o próximo concurso e alimenta a comparação de grupos da sugestão estatística.

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
| 12 | Busca local | ✅ | `LocalSearchService`: subida de encosta sobre um conjunto de apostas existente, com os seis movimentos da especificação (trocar dezena, substituir por aposta que cubra cenários mal cobertos, trocar dezenas entre apostas, remover redundante, inserir, substituir a de menor ganho marginal). Avalia F = Σ w·C_k − λ·|B| − μ·R sobre os cenários (sorteios de 15 dezenas dentro do grupo-base, até 21 dezenas), nunca gera combinação já sorteada e é reproduzível por semente. Para por iterações, falta de melhoria, cobertura desejada ou tempo. Por padrão mantém o número de apostas (orçamento fixo). Disponível na tela do fechamento ("Melhorar com busca local", com prévia e confirmação antes de aplicar) e como método "Equilibrado + busca local" na comparação. Resultado heurístico |
| 13 | Simulated annealing | ❌ | — |
| 14 | Algoritmo genético | ❌ | — |
| 15 | Programação por restrições | 🟡 | `OptimizerService` aplica filtros rígidos e limite de interseção entre apostas, mas sem solver |
| 16 | Sistemas de roda | 🟡 | `WheelBetGenerator` (fixas + variáveis); não calcula cobertura de pares e trincas |
| 17 | Pares/ímpares e faixas numéricas | ✅ | `BalancedBetGenerator`, `OptimizerService`, `BetScoringService` |
| 18 | Estatísticas históricas | ✅ | `LotofacilStatisticsService`: frequência, atraso, ciclos, temperatura (regra única e descritiva), repetição, score |
| 19 | Geração ponderada / filtros heurísticos | 🟡 | Filtros de pares/ímpares, soma, primos, Fibonacci, score, temperatura e repetidas; pontuação no `BalancedBetGenerator` e no `SmartRandomBetService`; "Distribuir apostas" (mínima sobreposição); **pesos por dezena** configuráveis (0 evita, 1 neutro, até 5; rotulados como distribuição artificial de geração) nas telas de criar e editar. Faltam pesos por par/padrão e limite de concentração por dezena |
| 20 | Cálculo exato de cobertura | 🟡 | Cobertura por faixa (≥11 a 15) exata sobre o universo completo em `ClosingProbabilityService`; auditoria de garantia exata dentro do grupo-base em `CoverageAuditorService`; não lista os cenários de menor pontuação |
| 21 | Garantia condicional | 🟡 | A auditoria reporta a condição ("garante X se acertar Y no grupo-base"); não há verificador genérico de condições |
| 22 | Probabilidade de pelo menos uma premiação | ✅ | Cálculo exato em `ClosingProbabilityService` (até 150 apostas), exibido no fechamento |
| 23 | Simulação de sorteios | ✅ | `MonteCarloSimulationService` + backtesting histórico em `/simulacoes`; **sem semente e sem intervalo de confiança** |
| 24 | Comparação de algoritmos | ✅ | Tela "Comparar métodos" (`/fechamentos/comparar`) e `ClosingComparisonService`: gera, sem salvar, apostas dos métodos aleatório, equilibrado, equilibrado + distribuir e reduzido com o mesmo grupo-base, orçamento (2 a 30 apostas) e semente (`mt_srand`), avaliados sobre os 3.268.760 sorteios. Mostra quantidade, custo, chance exata de ≥ 11 a 15 pontos, score (menor, média e maior), redundância, distribuição das dezenas, garantia de 14 pontos (exata, até 20 dezenas), tempo e memória, com selo de classificação. Não compara integral nem roda (dependem de parâmetros próprios) |
| 25 | Custo e orçamento | ✅ | Configuração por usuário, custo do fechamento, `/financeiro` (ROI por concurso, fechamento e aposta) |
| 26 | Relatórios e transparência | ✅ | `ClosingTransparencyReportService` e seção "Relatório de transparência" na tela do fechamento: método, grupo-base, apostas, custo, parâmetros, faixa de score, garantia e condições, cenários avaliados, método de validação, limitações, aleatoriedade e aviso de não-previsão. A semente aparece como "não registrada" até a implementação do requisito 3.11 |
| 27 | Testes | ✅ | 249 testes (Feature e Unit) |
| 28 | Desempenho | 🟡 | Geração sob demanda, bitmasks e enumeração exata (≈ 0,1 s por aposta). Fechamentos com 1.000 apostas ou mais (inclui o integral com 20 dezenas ou mais) rodam em segundo plano (`GenerateClosingBetsJob`) com barra de progresso, cancelamento sem deixar apostas parciais e mensagem de erro; exigem o worker da fila ativo. Faltam: informar o tempo e os cenários do modo exato, paralelização e progresso nas auditorias e no otimizador |
| 29 | Classificação Exato / Heurístico / Simulado | ✅ | Enum `ResultClassification` (Exato, Ótimo comprovado, Viável, Heurístico, Simulado) e selo `<x-result-badge>` no relatório, na chance real de premiação (exato), na auditoria combinatória (exata ou simulada), na auditoria consolidada, no otimizador (heurístico) e no Monte Carlo (simulado); o backtesting é rotulado como resultado histórico. Ciclo, atraso e temperatura seguem rotulados como descritivos |
| 30 | Regras de comunicação | ✅ | Textos revisados: o otimizador não fala mais em "jogos mais fortes"; a verificação por Monte Carlo deixou de dizer "Garante" e mostra "Sem falhas na amostra"; o Monte Carlo "estima" em vez de "prever"; os textos educativos descrevem a garantia como condicional. Score e temperatura continuam com aviso de que não alteram a probabilidade |

**Requisito transversal 3.11 (determinismo por semente aleatória):** 🟡 A sugestão do grupo-base é determinística e aceita semente; os geradores de apostas, o otimizador e as simulações ainda não.

### 2.1 Geração equilibrada (método mais usado)

1. **Grupo-base (opcional):** `BaseNumbersSuggestionService` combina repetidas do último concurso com dezenas novas. O tamanho pedido é sempre respeitado: as repetições são ajustadas para a faixa possível (as novas só podem vir das 10 dezenas fora do último concurso) e o relatório informa o ajuste. Paridade e moldura são reequilibradas a cada dezena escolhida. A temperatura **não** é usada na montagem da base.
   - **Dupla avaliação:** a mesma pontuação heurística (frequência ×0,1 + atraso ×2 + bônus do ciclo) define as 10 dezenas mais indicadas e as 5 menos indicadas. As menos indicadas ficam fora do grupo sempre que o tamanho e as repetições pedidas permitem; o relatório mostra quais ficaram fora e quais precisaram entrar.
   - **Resultado determinístico:** sem ruído nem sorteios internos (empates pela menor dezena), os mesmos dados e parâmetros geram sempre o mesmo grupo. O botão "Outra opção" usa uma semente reproduzível para variar.
   - **Vários fechamentos para o mesmo concurso:** a sugestão considera os fechamentos do usuário com o mesmo concurso alvo (ou já apostados nele). As dezenas que ficaram fora deles têm prioridade, inclusive sobre a regra das menos indicadas, e o grupo nunca repete um grupo existente (quando o tamanho permite). O relatório informa quantos fechamentos existiam e quais dezenas foram priorizadas.
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

### 2.3 Acompanhamento das dezenas mais e menos indicadas

- Ao cadastrar o último resultado, o sistema registra em `number_indications`, para o próximo concurso, a pontuação das 25 dezenas, as 10 mais indicadas e as 5 menos indicadas. Quando o resultado desse concurso chega, confere quantas de cada grupo saíram. Indicações conferidas não são alteradas.
- O painel "Acompanhamento" em Análises Lotofácil mostra as indicações do próximo concurso e a taxa de saída de cada grupo, com margem de 95%, comparada aos 60% esperados para qualquer dezena. Até 100 concursos conferidos, o resultado é tratado como amostra pequena.
- `php artisan lotofacil:indications` confere pendências e registra o próximo concurso (necessário após importações sem eventos do model; o seeder já o executa).
- Motivo: no teste retroativo, as 5 dezenas de menor pontuação saíram em 58,9% (±0,7) dos concursos seguintes, contra 60% esperados, com o efeito presente nas duas metades da série. Como foram feitos vários testes sobre os mesmos dados, o critério só será considerado confirmado se o efeito se mantiver nos concursos registrados daqui em diante.

---

## 3. Auditoria estatística (25/11/2026)

Verificações feitas contra a série real (3.776 concursos) e por enumeração exata:

- **Integridade dos dados:** concursos 1 a 3776 sem lacunas, dezenas válidas, hashes conferidos, nenhum sorteio repetido. Nenhuma das 287 apostas salvas era igual a um concurso passado.
- **Distribuições conferem com a teoria:** paridade, moldura/centro e repetidas do concurso anterior seguem a distribuição hipergeométrica; a soma tem média 195 e desvio ≈ 18; a frequência das dezenas é compatível com sorteio uniforme (χ² = 24,9 com 24 graus de liberdade).
- **Nenhum critério tem poder preditivo comprovado.** Em teste sequencial sobre 3.726 concursos, todas as categorias saíram em ≈ 60% (o esperado para qualquer dezena): quentes, neutras e frias; repetidas e não repetidas; faltantes do ciclo; dezena excluída pela sugestão. Dois desvios pequenos apareceram: atraso ≥ 3 concursos (62%, enfraquece na segunda metade da série) e as 5 dezenas de menor pontuação heurística (58,9%, presente nas duas metades). O segundo está em acompanhamento fora da amostra (seção 2.3); mesmo se real, vale ≈ 1 ponto percentual.
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
| Grupo-base | Dupla avaliação (mais × menos indicadas) e registro das indicações para acompanhamento; sugestão determinística com semente opcional; grupos diferentes para vários fechamentos do mesmo concurso |

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
3. **Geração em segundo plano exige worker:** fechamentos com 1.000 apostas ou mais usam a fila (`composer dev` já sobe o `queue:listen`; em produção o contêiner `loterias_queue_prod`). Sem worker, o fechamento fica "Na fila" até ser cancelado. Auditoria combinatória e otimizador ainda rodam na requisição.
4. **Faixas de score salvas:** fechamentos criados antes da nova escala (0–1000 real) podem ter `score_range` calibrado na escala antiga; o total agora sai cerca de 11% maior para os mesmos critérios.
5. **Monte Carlo:** a auditoria por amostragem marca "garantia confirmada" com base em amostra, e a simulação financeira usa prêmios fixos estimados para 14 e 15 pontos.
6. **Após importar a série histórica:** rodar `php artisan lotofacil:calculate-scores` (o seeder grava sem calcular score e ciclo) e `php artisan lotofacil:indications` se a importação não passou pelo model. O `DatabaseSeeder` cria apenas o usuário de desenvolvimento; a série exige `php artisan db:seed --class=HistoricalResultSeeder`.
7. **Arquivos soltos:** `parse_xlsx.py` na raiz parece sobra de desenvolvimento; `prompt_base.MD` termina com um resto de colagem ("Mensagem para o Agente / Adicionar / Ferramentas").

---

## 6. Próximos passos sugeridos

Em ordem de prioridade:

1. **Segurança e consistência**
   - [ ] Restringir o CRUD de sorteios a administradores.
   - [ ] Criar Policies para `Closing`, `Bet` e `HistoricalResult`.
2. **Reprodutibilidade e transparência** (Itens 3.11, 26, 29 e 30)
   - [ ] Aceitar e persistir semente aleatória nos geradores de apostas, no otimizador e nas simulações (a sugestão do grupo-base já aceita).
   - [x] Rotular sistematicamente cada resultado como Exato, Ótimo comprovado, Viável, Heurístico ou Simulado.
   - [ ] Adicionar intervalo de confiança de 95% ao Monte Carlo e deixar de chamar amostragem de "garantia confirmada" (o rótulo já foi corrigido na interface; falta o intervalo de confiança).
   - [x] Criar um relatório de transparência do fechamento (método, parâmetros, semente, cobertura, garantias e limitações). A semente só aparece como "não registrada" até a semente ser persistida.
   - [x] Revisar os textos do otimizador ("jogos mais fortes").
3. **Cálculos matemáticos** (Itens 3, 4 e 20)
   - [ ] Tela com P(X=k) de uma aposta simples e apostas equivalentes C(m,15) com custo.
   - [ ] Distribuição completa da melhor pontuação e cenários de menor pontuação.
4. **Desempenho** (Item 28)
   - [x] Mover a geração de fechamentos grandes para jobs, com progresso e cancelamento (limite de 1.000 apostas em `ClosingGenerator::QUEUE_THRESHOLD`; requer o worker da fila).
5. **Critério das dezenas menos indicadas**
   - [ ] Reavaliar o acompanhamento após 100 concursos conferidos e decidir se a dupla avaliação continua na sugestão.
6. **Otimização avançada** (Itens 12 a 14 e 24)
   - [x] Busca local sobre a solução gulosa e sobre a distribuição das apostas (maximizar a chance de pelo menos um prêmio). Feita sobre qualquer conjunto de apostas; falta ligar ao reduzido guloso na geração.
   - [ ] Simulated annealing.
   - [ ] Algoritmo genético.
   - [x] Módulo de comparação entre métodos (mesmo grupo-base, orçamento e semente), usando `ClosingProbabilityService`. Inclua os métodos de otimização avançada nele quando existirem.
7. **Rodas e cobertura** (Itens 8 e 16)
   - [ ] Calcular cobertura de pares e trincas nas rodas.
   - [ ] Pesos por cenário.
