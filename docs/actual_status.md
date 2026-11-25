# Status Atual do Projeto Loterias

**Última atualização:** 25/11/2026 (referência: commit `1faa61a`, de 22/09/2026)

Este documento compara o que está implementado no código com a especificação mestre ([`prompt_base.MD`](prompt_base.MD)) e lista o que ainda falta. O roteiro original em etapas ([`prompt_4_fase.md`](prompt_4_fase.md)) está superado e serve apenas como registro histórico.

---

## 1. Visão geral

- **Stack:** PHP 8.3, Laravel 13, Livewire 3 + Volt, Tailwind 4, Vite, Breeze. SQLite no ambiente local, MariaDB no Docker e em produção.
- **Testes:** 116 testes PHPUnit (2.350 asserções), todos passando.
- **Histórico de desenvolvimento:** 40 commits entre 12/08/2026 e 22/09/2026. Planos e walkthroughs das features mais recentes estão em [`development_prompts/`](development_prompts/).

### 1.1 Modelo de dados

| Tabela / Model | Papel |
| --- | --- |
| `users` / `User` | Usuários, com `share_code` para conexões |
| `bets` / `Bet` | Apostas (dezenas em JSON), avulsas ou ligadas a um fechamento via `closing_id`; status, concurso e acertos |
| `closings` / `Closing` | Fechamentos: método, grupo-base, parâmetros (JSON), `planned_bets`, status, concurso e data do sorteio |
| `historical_results` / `HistoricalResult` | Série histórica: dezenas, hash único da combinação, ganhadores e rateios, score e número do ciclo |
| `lotofacil_settings` / `LotofacilSetting` | Preços por tamanho de aposta e prêmios fixos, por usuário |
| `consolidated_audits` / `ConsolidatedAudit` | Resultados das auditorias de cobertura processadas em fila |
| `connections` / `Connection` | Conexões (amizade) entre usuários |
| `closing_user`, `bet_user` | Compartilhamento de fechamentos e apostas (bolão) |

> Diferente do previsto em `prompt_4_fase.md`, não existe a tabela pivô `closing_bet`: cada aposta pertence a no máximo um fechamento.

### 1.2 Ciclo de vida do fechamento

`draft` → `processing` → `completed` → `placed` (concurso e data informados) → `checked` (conferido contra o resultado). Em caso de erro, `failed`.

- Fechamentos `completed` podem ser editados. Salvar apaga as apostas e volta o fechamento para `draft`.
- Ações disponíveis: refazer apostas, excluir apostas individualmente ou em lote (com renumeração), completar apostas faltantes até `planned_bets` e alertar dezenas do grupo-base sem cobertura.
- Apostas e fechamentos `placed`/`checked` ficam bloqueados para edição e exclusão.

---

## 2. Cobertura da especificação (`prompt_base.MD`)

Legenda: ✅ implementado · 🟡 parcial · ❌ não implementado

| Item | Tema | Status | Onde / Observação |
| ---: | --- | :---: | --- |
| 1 | Validação de dezenas, apostas e parâmetros | 🟡 | `StoreBetRequest`, `StoreClosingRequest`, `validate()` dos geradores e `ClosingGenerator`. Não há validação de semente, pois sementes não existem. |
| 2 | Geração e enumeração de combinações | ✅ | `Betting/Math/Combinatorics` e `phpexperts/combinatorics`, sob demanda (PHP Generators) |
| 3 | Probabilidade hipergeométrica de aposta simples | ❌ | Não há módulo de cálculo; a página educativa é apenas texto |
| 4 | Apostas equivalentes C(m,15) e custo | 🟡 | `LotteryPrizeCalculatorService::getBetCost()` cobre o custo por tamanho; não há módulo explicativo |
| 5 | Fechamento integral | ✅ | `IntegralBetGenerator` |
| 6 | Interseção grupo-base × sorteio | ✅ | Conferência e `checkBaseNumbersCoverage()` |
| 7 | Fechamento reduzido | ✅ | `ReducedBetGenerator` + `SetCoveringService` (heurística gulosa, limite de 23 dezenas) |
| 8 | Modelo de cobertura combinatória | 🟡 | Cenários = subconjuntos do grupo-base; não há pesos por cenário |
| 9 | Set covering (mínimo e por orçamento) | 🟡 | Apenas heurística gulosa com `budget`; sem solução exata |
| 10 | Programação inteira | ❌ | — |
| 11 | Heurística gulosa | ✅ | `SetCoveringService` |
| 12 | Busca local | ❌ | — |
| 13 | Simulated annealing | ❌ | — |
| 14 | Algoritmo genético | ❌ | — |
| 15 | Programação por restrições | 🟡 | `OptimizerService` aplica filtros rígidos (pares, primos, Fibonacci, repetidas) e limite de interseção entre apostas, mas sem solver |
| 16 | Sistemas de roda | 🟡 | `WheelBetGenerator` (fixas + variáveis); não calcula cobertura de pares e trincas |
| 17 | Pares/ímpares e faixas numéricas | ✅ | `BalancedBetGenerator`, `OptimizerService`, `BetScoringService` |
| 18 | Estatísticas históricas | ✅ | `LotofacilStatisticsService`: frequência, atraso, ciclos, temperatura contextual por concurso, repetição, score |
| 19 | Geração ponderada / filtros heurísticos | 🟡 | Pontuação no `BalancedBetGenerator` e no `SmartRandomBetService`; não há pesos configuráveis pelo usuário |
| 20 | Cálculo exato de cobertura | 🟡 | `CoverageAuditorService::auditCoverage()` é exato **dentro do grupo-base**; não enumera os 3.268.760 sorteios |
| 21 | Garantia condicional | 🟡 | A auditoria reporta a condição ("garante X se acertar Y no grupo-base"); não há verificador genérico de condições |
| 22 | Probabilidade de pelo menos uma premiação | 🟡 | Estimada por Monte Carlo; não há cálculo exato |
| 23 | Simulação de sorteios | ✅ | `MonteCarloSimulationService` + backtesting histórico em `/simulacoes`; **sem semente e sem intervalo de confiança** |
| 24 | Comparação de algoritmos | ❌ | — |
| 25 | Custo e orçamento | ✅ | Configuração por usuário, custo do fechamento, `/financeiro` (ROI por concurso, fechamento e aposta) |
| 26 | Relatórios e transparência | 🟡 | Relatório estatístico do grupo-base, auditoria e impressão; não há relatório unificado do fechamento |
| 27 | Testes | ✅ | 116 testes (Feature e Unit) |
| 28 | Desempenho | 🟡 | Geração sob demanda e bitmasks na auditoria; a geração de fechamentos roda de forma síncrona, sem progresso nem cancelamento |
| 29 | Classificação Exato / Heurístico / Simulado | ❌ | Não aparece de forma sistemática na interface |
| 30 | Regras de comunicação | 🟡 | Há avisos pontuais; temperatura, ciclo e atraso precisam ser rotulados sempre como heurísticas |

**Requisito transversal 3.11 (determinismo por semente aleatória):** ❌ Nenhum gerador, simulador ou otimizador aceita semente, então os resultados não são reproduzíveis.

---

## 3. Funcionalidades além da especificação

- Conferência de apostas e fechamentos efetivados (`placed` → `checked`) com cálculo de prêmios.
- Importação da série histórica da planilha da Caixa (`app:import-historical-results`) e CRUD de sorteios.
- Cálculo de score e ciclo dos concursos (`lotofacil:calculate-scores`).
- Dashboard de análises e comparativo de concursos com temperatura contextual (janela móvel de 20 concursos).
- Sugestão de grupo-base por análise estatística, com relatório dos critérios.
- Conexões entre usuários e compartilhamento de fechamentos e apostas (bolão).
- Impressão de fechamentos.
- Ambiente Docker de desenvolvimento e de produção ([`docker-prod.md`](docker-prod.md)).

---

## 4. Pontos de atenção

1. **Série histórica sem controle de acesso:** qualquer usuário autenticado pode criar, editar e excluir sorteios em `/sorteios`. Como os dados são globais, isso afeta a conferência, as estatísticas e o financeiro de todos. Falta um papel de administrador.
2. **Autorização espalhada:** não há Policies; as verificações de dono e compartilhamento ficam no `mount()` de cada página Volt.
3. **Geração síncrona:** fechamentos grandes (integral com 20 ou mais dezenas = 15.504 ou mais apostas) rodam na requisição web e podem estourar o timeout. Só a auditoria consolidada usa fila.
4. **Seeder:** o `DatabaseSeeder` cria apenas o usuário de desenvolvimento; a série histórica exige `php artisan db:seed --class=HistoricalResultSeeder`.
5. **Arquivos soltos:** `parse_xlsx.py` na raiz parece sobra de desenvolvimento; `prompt_base.MD` termina com um resto de colagem ("Mensagem para o Agente / Adicionar / Ferramentas").

---

## 5. Próximos passos sugeridos

Em ordem de prioridade:

1. **Segurança e consistência**
   - [ ] Restringir o CRUD de sorteios a administradores.
   - [ ] Criar Policies para `Closing`, `Bet` e `HistoricalResult`.
2. **Reprodutibilidade e transparência** (Itens 3.11, 26, 29 e 30)
   - [ ] Aceitar e persistir semente aleatória em geradores, otimizador e simulações.
   - [ ] Rotular cada resultado como Exato, Ótimo comprovado, Viável, Heurístico ou Simulado.
   - [ ] Adicionar intervalo de confiança de 95% ao Monte Carlo.
   - [ ] Criar um relatório de transparência do fechamento (método, parâmetros, semente, cobertura, garantias e limitações).
3. **Cálculos matemáticos** (Itens 3, 4 e 22)
   - [ ] Criar um módulo hipergeométrico (P(X=k), P(X≥11), "1 em N").
   - [ ] Calcular apostas equivalentes C(m,15) com custo.
   - [ ] Calcular exatamente a probabilidade de pelo menos uma premiação em um conjunto de apostas.
4. **Desempenho** (Item 28)
   - [ ] Mover a geração de fechamentos grandes para jobs, com progresso e cancelamento.
5. **Otimização avançada** (Itens 12 a 14 e 24)
   - [ ] Busca local sobre a solução gulosa.
   - [ ] Simulated annealing.
   - [ ] Algoritmo genético.
   - [ ] Módulo de comparação entre métodos (mesmo grupo-base, orçamento e semente).
6. **Rodas e cobertura** (Itens 8, 16 e 20)
   - [ ] Calcular cobertura de pares e trincas nas rodas.
   - [ ] Pesos por cenário.
   - [ ] Auditoria exata sobre o universo completo de sorteios.
