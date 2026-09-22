# Walkthrough - Bloqueio de Exclusão e Edição de Apostas Apostadas

Data: 2026-09-22

## Resumo da Implementação

Foi implementado o bloqueio completo de edição, exclusão ou reinício de apostas quando elas ou o fechamento estiverem marcados com o status **Apostado (`placed`)** ou **Conferido (`checked`)**.

---

## Modificações Realizadas

### 1. Apostas Individuais ([bets/index.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/bets/index.blade.php))
- **Método `delete(int $betId)`**:
  - Adicionada validação que impede a exclusão se `$bet->status` for `placed` ou `checked`, retornando mensagem de erro amigável ao usuário.
- **Método `deleteAllBets()`**:
  - Filtra apenas apostas cujo status **não** seja `placed` nem `checked` (`whereNotIn('status', ['placed', 'checked'])`), protegendo apostas já registradas em concursos.
- **Interface Blade**:
  - O botão de exclusão individual da aposta só é renderizado para apostas com status anterior (`@if (! in_array($bet->status, ['placed', 'checked'], true))`).

---

### 2. Fechamentos - Gerenciamento e Ciclo de Vida ([closings/show.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/show.blade.php))
- **Método `resetBets()`**:
  - Bloqueado se o fechamento estiver com status `placed` ou `checked`.
- **Método `completeBets()`**:
  - Bloqueado se o fechamento já estiver `placed` ou `checked`.
- **Método `deleteBet(int $betId)`**:
  - Bloqueado se o fechamento for `placed` ou `checked`, ou se a aposta individual já estiver `placed` ou `checked`.
- **Método `deleteSelectedBets()`**:
  - Bloqueado para fechamentos `placed` ou `checked`. Adicionalmente, apenas apostas não apostadas/conferidas são excluídas se houver seleção residual.
- **Interface Blade**:
  - O botão **Refazer apostas** deixa de ser exibido após o fechamento ser marcado como `placed` ou `checked`.
  - O painel/botão de **Completar apostas faltantes** só é exibido se o fechamento estiver estritamente com status `completed`.
  - Na tabela de apostas vinculadas, as caixas de seleção (`checkbox`), o cabeçalho de seleção em lote e a coluna de **Ações** (com o botão de exclusão individual de cada aposta) são automaticamente ocultados quando o fechamento for `placed` ou `checked`.

---

### 3. Camada de Serviço ([ClosingGenerator.php](file:///home/jean/ai_projects/loterias/app/Services/Betting/ClosingGenerator.php))
- **`resetToDraft(Closing $closing)`**:
  - Lança `LogicException` impedindo redefinir para rascunho fechamentos marcados como `placed` ou `checked`.
- **`completeBets(Closing $closing)`**:
  - Lança `LogicException` impedindo injetar novas apostas em fechamentos marcados como `placed` ou `checked`.

---

## Testes Automatizados

Foram adicionados testes cobrindo os cenários em [ClosingPlacedAndCheckTest.php](file:///home/jean/ai_projects/loterias/tests/Feature/ClosingPlacedAndCheckTest.php):
- `test_cannot_delete_individual_bet_when_placed_or_checked`: garante que apostas avulsas apostadas não são excluídas.
- `test_cannot_delete_or_reset_closing_bets_when_placed`: garante que apostas de fechamento apostado não são excluídas nem o fechamento reiniciado.

**Resultado da suíte completa de testes:**
`116 passed, 2350 assertions` (100% de sucesso).
