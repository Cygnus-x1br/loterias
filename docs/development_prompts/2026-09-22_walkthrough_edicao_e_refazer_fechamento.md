# Walkthrough - Edição de Fechamento Concluído, Refazer Apostas, Exclusão Pontual e Completar Apostas Faltantes

**Data:** 22/09/2026  
**Fase:** Gerenciamento Avançado de Apostas de Fechamento (`Closing`)

---

## 1. Visão Geral das Mudanças

Implementamos melhorias no gerenciamento de Fechamentos (`Closing`), atendendo integralmente às seguintes demandas:

1. **Permissão de Edição para Fechamentos Concluídos (`completed`)**:
   - O botão "Editar Fechamento" agora é exibido tanto em `draft`, `failed` quanto em `completed`.
   - Ao editar um fechamento já concluído, um alerta informativo comunica que salvar novas dezenas/parâmetros excluirá as apostas existentes e retornará o fechamento para `draft`.
   - Ao salvar (`save()`), as apostas antigas são removidas e o fechamento retorna para o status `draft`, pronto para nova geração.

2. **Ação "Refazer Apostas"**:
   - Botão direto no painel de ações de `show.blade.php` com confirmação amigável.
   - Permite resetar todas as apostas geradas (`$closing->bets()->delete()`) e retornar o status do fechamento para `draft` com um clique.

3. **Exclusão de Apostas Específicas (Individual e em Lote)**:
   - Checkboxes de seleção por linha e opção "Selecionar todas" na página atual.
   - Botão de exclusão pontual (lixeira) em cada linha de aposta com confirmação.
   - Barra de ações em lote no topo da tabela (*"X aposta(s) selecionada(s) -> Excluir selecionadas"*).
   - Reorganização automática dos nomes das apostas restantes (`Aposta 1`, `Aposta 2`...) via `ClosingGenerator::renumberBets()`.

4. **Gatilho Automático de Cobertura da Base**:
   - Imediatamente após a exclusão de qualquer aposta, o gatilho `checkBaseNumbersCoverage()` é disparado.
   - Se alguma dezena do grupo-base ficar descoberta (sem apostas), o sistema exibe instantaneamente o alerta destacando as dezenas órfãs.

5. **Ação "Completar Apostas Faltantes"**:
   - Sempre que o número de apostas for menor que o planejado (`$closing->planned_bets`), um card de aviso é exibido com o botão **"Completar apostas faltantes"**.
   - O método `ClosingGenerator::completeBets()` gera as combinações complementares priorizando dezenas da base descobertas, evitando duplicações e garantindo que combinações de 15 dezenas já sorteadas na história não sejam utilizadas.

---

## 2. Arquivos Modificados / Criados

- [`app/Services/Betting/ClosingGenerator.php`](file:///home/jean/ai_projects/loterias/app/Services/Betting/ClosingGenerator.php):
  - Adicionado `resetToDraft(Closing $closing): void`
  - Adicionado `renumberBets(Closing $closing): void`
  - Adicionado `completeBets(Closing $closing): int` com priorização inteligente de dezenas descobertas.
- [`resources/views/livewire/pages/closings/show.blade.php`](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/show.blade.php):
  - Adicionadas propriedades de seleção `$selectedBets` e `$selectAll`.
  - Adicionados métodos Volt `resetBets()`, `completeBets()`, `deleteBet()`, `deleteSelectedBets()`, `updatedSelectAll()`.
  - Botão "Editar Fechamento" habilitado para status `completed`.
  - Botão "Refazer apostas" adicionado no painel de ações.
  - Alerta de apostas incompletas e botão "Completar apostas faltantes".
  - Checkboxes e coluna de ações com lixeira por linha na tabela de apostas vinculadas.
- [`resources/views/livewire/pages/closings/edit.blade.php`](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/edit.blade.php):
  - Liberação do `mount()` e `save()` para status `completed`.
  - Exclusão de apostas antigas e retorno a `draft` ao salvar alterações em fechamento concluído.
  - Alerta visual no topo informando o comportamento do salvamento.
- [`tests/Feature/Pages/ClosingsEditTest.php`](file:///home/jean/ai_projects/loterias/tests/Feature/Pages/ClosingsEditTest.php):
  - Atualizado para testar a edição de fechamento concluído e a exibição do botão.
- [`tests/Feature/ClosingRefactorAndCompleteTest.php`](file:///home/jean/ai_projects/loterias/tests/Feature/ClosingRefactorAndCompleteTest.php):
  - Novos testes cobrindo reset para draft, exclusão individual com gatilho de dezenas descobertas, exclusão em lote e complementação de apostas até o total planejado.

---

## 3. Verificação e Testes

- **Testes Específicos**:
  - `tests/Feature/ClosingRefactorAndCompleteTest.php`: 4 testes, 27 asserções — **PASSOU**
  - `tests/Feature/Pages/ClosingsEditTest.php`: 5 testes, 27 asserções — **PASSOU**
  - `tests/Feature/ClosingPlacedAndCheckTest.php`: 5 testes, 33 asserções — **PASSOU**
  - `tests/Feature/Services/Betting/ClosingGeneratorTest.php`: 7 testes, 20 asserções — **PASSOU**
- **Suíte Completa de Testes**:
  - 114 testes, 2.344 asserções — **100% PASSOU**
- **Formatação de Código**:
  - `vendor/bin/pint --format agent` executado e aplicado sem pendências.
