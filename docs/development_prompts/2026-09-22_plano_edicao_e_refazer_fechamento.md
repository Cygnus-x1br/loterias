# Plano de Implementação - Edição de Fechamento Concluído, Refazer Apostas, Exclusão Pontual e Completar Apostas Faltantes

## 1. Contexto e Objetivos

Atualmente, o fluxo de vida de um fechamento (`Closing`) possui os status:
- `draft`: rascunho (ainda sem apostas geradas);
- `processing`: em processamento de geração;
- `completed`: concluído (apostas geradas com sucesso);
- `placed`: apostado (o usuário informou concurso e data do sorteio);
- `checked`: conferido (resultado conferido contra histórico);
- `failed`: falha na geração.

O sistema bloqueia a edição de parâmetros quando o status não for `draft` ou `failed` (`ClosingsEditTest` e `edit.blade.php`).
Além disso:
1. Em métodos como **fechamento reduzido** (`reduced`), onde a heurística de cobertura é satisfeita antes de consumir o orçamento, ou quando apostas geradas coincidem com concursos históricos e são descartadas, a quantidade de apostas geradas pode ser inferior à quantidade planejada (`planned_bets`).
2. O usuário precisa de:
   - **Poder editar o fechamento mesmo quando já concluído (`completed`)** para alterar dezenas-base, método ou parâmetros;
   - **Poder refazer as apostas em lote**: limpar todas as apostas criadas do fechamento e resetar o fechamento para `draft` para configurar ou gerar do zero;
   - **Poder selecionar uma ou mais apostas específicas no fechamento para excluir individualmente**: permitindo ao usuário eliminar apostas que ele julgue mal distribuídas;
   - **Gatilho de auditoria de cobertura da base**: ao excluir apostas (individualmente ou em lote), disparar imediatamente a verificação (`checkBaseNumbersCoverage()`) para alertar o usuário caso alguma dezena do grupo-base tenha ficado descoberta (sem nenhuma aposta que a contenha);
   - **Poder completar apostas faltantes**: quando o total de apostas for menor do que o especificado em `planned_bets` (seja por geração incompleta inicial ou após a exclusão de apostas indesejadas), acionar a opção de completar as apostas faltantes até atingir a meta, priorizando inclusive cobrir eventuais dezenas da base que estejam sem cobertura e mantendo a unicidade sem duplicatas.

---

## 2. Decisões Arquiteturais e de Design

### 2.1. Edição de Fechamento Concluído (`completed`)
- **Liberação do Botão na visualização (`show.blade.php`)**:
  - Exibir o botão "Editar Fechamento" tanto para `draft`, `failed` quanto para `completed`.
  - Se o fechamento estiver `placed` ou `checked`, ele já tem apostas registradas em concurso real. Para editá-lo, o usuário primeiro precisará clicar em "Refazer / Desfazer Apostas" (com confirmação explícita de que os dados do concurso/conferência e apostas vinculadas serão excluídos).
- **Tratamento na tela de edição (`edit.blade.php`)**:
  - Permitir carregar a tela de edição se o status for `draft`, `failed` ou `completed`.
  - Se o status for `completed`, exibir um alerta amigável informando: *"Este fechamento já possui apostas geradas. Salvar novas alterações apagará as apostas geradas anteriormente e retornará o fechamento para o status de rascunho para que você possa gerar os novos jogos."*
  - Ao executar `save()`, caso haja apostas vinculadas (`$this->closing->bets()->exists()`), remover as apostas (`$this->closing->bets()->delete()`), atualizar os novos dados e setar o status para `draft`.

### 2.2. Ação "Refazer Apostas" (Reset em Lote para Rascunho)
- Disponível em `show.blade.php` para fechamentos com status `completed` (ou `placed` / `checked` com confirmação):
  - Um botão com ícone de reciclagem/reinício: **"Refazer Apostas"**.
  - Confirmação explícita: *"Tem certeza que deseja apagar todas as apostas geradas deste fechamento? O fechamento retornará para o estado de rascunho e você poderá alterar as dezenas ou gerar novamente."*
  - Método no Volt `resetBets()`:
    1. Executa `$closing->bets()->delete()`;
    2. Reseta `$closing->update(['status' => 'draft', 'contest_number' => null, 'draw_date' => null])`;
    3. Notifica o usuário e recarrega a página no estado de rascunho.

### 2.3. Exclusão de Apostas Específicas / Selecionadas
- Na tabela de apostas vinculadas em `show.blade.php`:
  - **Exclusão Individual por Linha**: botão com ícone de lixeira em cada linha da tabela de aposta para o proprietário do fechamento (com confirmação rápida `wire:confirm`).
  - **Seleção Múltipla com Checkbox**:
    - Checkbox no cabeçalho ("Selecionar todas da página") e checkbox em cada linha vinculados à propriedade Livewire `public array $selectedBets = [];`.
    - Barra de ação em lote flutuante ou destacada no topo da tabela quando houver apostas selecionadas: *"X aposta(s) selecionada(s)"* + botão **"Excluir Apostas Selecionadas"**.
  - **Métodos Volt no `show.blade.php`**:
    - `deleteBet(int $betId)`: exclui uma única aposta vinculada ao fechamento do usuário.
    - `deleteSelectedBets()`: exclui em lote todas as apostas com IDs presentes em `$this->selectedBets`.
  - **Gatilho Imediato de Auditoria da Base**:
    - Logo após a exclusão de qualquer aposta, executa `$this->checkBaseNumbersCoverage()` e `$this->renumberBets()`.
    - Caso alguma dezena da base tenha ficado sem nenhuma aposta (dezenas não utilizadas), a UI imediatamente exibe o alerta amarelo de cobertura: *"Atenção: Foram utilizadas apenas X das Y dezenas do seu grupo base nas apostas geradas. Dezenas não utilizadas: [...]"*.
    - Se o total de apostas ficar abaixo de `planned_bets`, o alerta e o botão **"Completar Apostas Faltantes"** passam a ser exibidos automaticamente.

### 2.4. Ação "Completar Apostas Faltantes"
- **Detecção de Apostas Incompletas**:
  - Se `$closing->planned_bets !== null` e `$totalBets < $closing->planned_bets` nos status `completed`, `placed` ou `checked`:
    - Exibir banner destacado no topo da lista: *"Atenção: Existem {X} de {Y} apostas planejadas ({Z} aposta(s) faltante(s))."*
    - Exibir o botão: **"Completar Apostas Faltantes"**.
- **Lógica Inteligente no Backend (`ClosingGenerator::completeBets`)**:
  - Adicionar o método `completeBets(Closing $closing): int` no `ClosingGenerator`.
  - Identifica as combinações já salvas no fechamento (`$closing->bets->pluck('numbers')`).
  - Calcula quantas faltam: `$needed = $closing->planned_bets - $totalBets`.
  - **Prioridade de Cobertura de Dezenas Descobertas**:
    - Se houver dezenas da base que ficaram descobertas após as exclusões (`$unusedNumbers`), a geração das apostas complementares prioriza fortemente essas dezenas para que 100% da base volte a ser contemplada nas apostas!
  - Garante:
    1. Não duplica nenhuma aposta já existente no fechamento;
    2. Não utiliza combinação de 15 dezenas que já tenha sido sorteada na história da Lotofácil (filtro de histórico);
    3. Respeita o tamanho da aposta (`bet_size`) e o grupo-base;
    4. Numera sequencialmente as novas apostas (`Aposta X`).
  - Atualiza o fechamento, reavalia a cobertura e exibe mensagem de sucesso: *"{N} aposta(s) gerada(s) para completar o fechamento com sucesso!"*

---

## 3. Arquivos Envolvidos e Propostas de Alteração

### 3.1. [MODIFY] `app/Services/Betting/ClosingGenerator.php`
- Adicionar método `completeBets(Closing $closing): int`:
  - Valida se `$closing->planned_bets` existe e se `$closing->bets()->count() < $closing->planned_bets`.
  - Identifica dezenas da base não contempladas nas apostas atuais.
  - Gera combinações adicionais únicas que priorizem as dezenas descobertas e respeitem os filtros de histórico.
  - Persiste as novas apostas e retorna a quantidade criada.
- Adicionar método `resetToDraft(Closing $closing): void`:
  - Centraliza a exclusão segura de apostas (`$closing->bets()->delete()`) e reset do status para `draft`.
- Adicionar método `renumberBets(Closing $closing): void`:
  - Reorganiza a numeração das apostas do fechamento (ex: `Aposta 1`, `Aposta 2`...) após exclusões para manter a sequência limpa.

### 3.2. [MODIFY] `resources/views/livewire/pages/closings/show.blade.php`
- **Edição**:
  - Exibir "Editar Fechamento" também para status `completed`.
- **Refazer Apostas**:
  - Botão "Refazer Apostas" com confirmação para status `completed`.
- **Seleção e Exclusão de Apostas Específicas**:
  - Propriedades Livewire: `public array $selectedBets = [];` e `public bool $selectAll = false;`.
  - Checkboxes na tabela de apostas e botão de exclusão individual na coluna de ações da linha.
  - Barra superior de ações em lote para exclusão das selecionadas.
  - Métodos `deleteBet(int $betId)` e `deleteSelectedBets()`.
  - Chamada imediata a `$this->checkBaseNumbersCoverage()` após exclusão para alertar se todas as dezenas da base continuam cobertas.
- **Card e Botão "Completar Apostas Faltantes"**:
  - Exibido sempre que `$closing->planned_bets && $totalBets < $closing->planned_bets`.
  - Implementar método `completeBets()` que aciona `ClosingGenerator::completeBets()` e atualiza a interface.

### 3.3. [MODIFY] `resources/views/livewire/pages/closings/edit.blade.php`
- **Permitir edição para fechamentos `completed`**:
  - No `mount()`: permitir status `completed` além de `draft` e `failed`.
  - No `save()`: permitir status `completed`. Se houver apostas existentes, excluir e resetar `status = 'draft'`.
  - Alerta visual no topo do formulário avisando o usuário sobre o comportamento ao salvar.

### 3.4. [MODIFY] `tests/Feature/Pages/ClosingsEditTest.php`
- Atualizar teste `test_cannot_edit_completed_closing`:
  - Fechamento `completed` agora pode ser editado;
  - Ao salvar, apostas anteriores são apagadas e o status volta para `draft`.
- Atualizar teste de exibição do botão "Editar Fechamento" para validar presença em `completed`.

### 3.5. [NEW] `tests/Feature/ClosingRefactorAndCompleteTest.php`
- Testar:
  1. Ação "Refazer Apostas" (`resetBets`);
  2. Exclusão de aposta individual (`deleteBet`);
  3. Exclusão de apostas em lote (`deleteSelectedBets`);
  4. Gatilho de cobertura da base (`checkBaseNumbersCoverage`) detectando dezenas órfãs após exclusão;
  5. Ação "Completar Apostas Faltantes" (`completeBets`), validando que atinge o total de `planned_bets` e preenche as dezenas descobertas sem duplicatas.

---

## 4. Plano de Verificação

### Testes Automatizados
- Executar os testes novos e existentes relacionados a fechamentos:
  ```bash
  php artisan test --compact tests/Feature/Pages/ClosingsEditTest.php
  php artisan test --compact tests/Feature/ClosingPlacedAndCheckTest.php
  php artisan test --compact tests/Feature/ClosingRefactorAndCompleteTest.php
  ```
- Executar a suíte completa de testes do projeto para garantir zero regressões:
  ```bash
  php artisan test --compact
  ```
- Executar o Pint para padronização de código:
  ```bash
  vendor/bin/pint --format agent
  ```
