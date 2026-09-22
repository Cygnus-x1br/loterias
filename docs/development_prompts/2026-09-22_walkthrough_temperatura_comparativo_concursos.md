# Walkthrough - Temperatura Contextual das Dezenas no Comparativo de Concursos

**Data:** 22/09/2026  
**Fase:** Ajuste Estatístico Dinâmico por Sorteio - Lotofácil Comparativo  

---

## 1. Resumo das Alterações

Foi implementada a diferenciação contextual da classificação térmica das 25 dezenas para cada linha/sorteio exibido na rota `/dashboard/analises-lotofacil/comparativo`.

Anteriormente, uma única tabela de temperatura calculada para todo o bloco de concursos era compartilhada de forma estática com todas as linhas da matriz. Agora, cada sorteio avalia a janela histórica imediatamente anterior à sua realização (janela móvel de até 20 concursos prévios), garantindo que a classificação em **quente** (frequência alta), **neutra** (frequência média) ou **fria** (frequência baixa) reflita com exatidão o momento em que o sorteio ocorreu.

---

## 2. Detalhes Técnicos

### [LotofacilStatisticsService.php](file:///home/jean/ai_projects/loterias/app/Services/LotofacilStatisticsService.php)
1. **Busca otimizada em lote:**
   - Em `getContestsComparisonAnalysis()`, a consulta ao banco de dados agora busca `$limit + $historyWindow + 1` concursos em uma única query indexada (`orderBy('contest_number', 'desc')`), prevenindo qualquer consulta N+1.
2. **Janela histórica móvel por sorteio:**
   - Em `buildContestsComparisonList()`, para cada concurso indexado em `$idx`:
     - O serviço extrai a fatia `$resultsArray->slice($idx + 1, $historyWindow)` correspondente aos concursos anteriores ao sorteio avaliado.
     - Aplica `calculatePeriodTemperatures()` especificamente sobre essa fatia histórica.
     - Alimenta o `numbers_grid[$num]['temperature']` com o resultado dinâmico daquele sorteio.
   - O card consolidado no topo ("Último Concurso Realizado") e os resumos de médias continuam utilizando a amostragem global do período selecionado.

---

## 3. Testes e Validação

### Testes Automatizados
- **Arquivo:** [LotofacilContestComparisonTest.php](file:///home/jean/ai_projects/loterias/tests/Feature/LotofacilContestComparisonTest.php)
- **Novo teste adicionado:** `test_contests_have_distinct_contextual_temperatures`
  - Simula uma sequência histórica onde uma mesma dezena é ausente em sorteios anteriores (classificada como `cold`) e passa a ser sorteada com alta frequência nos sorteios seguintes (evoluindo para `hot`).
  - Assegura que diferentes linhas da tabela no comparativo retornam classificações térmicas distintas para a mesma dezena.
- **Resultado:**
  - `LotofacilContestComparisonTest`: 5 testes passaram com sucesso (25 asserções).
  - Suite completa de testes: **110 testes passaram com sucesso (2.308 asserções)**.

### Formatação de Código
- Executado `vendor/bin/pint --format agent` de acordo com os padrões do projeto.
- Cache da aplicação limpo via `php artisan cache:clear` para que novos acessos já calculem as temperaturas contextuais de imediato.
