# Exibir Relatório de Análise Estatística

Ao utilizar o gerador de base por Análise Estatística, o usuário precisa entender o raciocínio por trás da seleção das dezenas. Atualmente, o sistema escolhe silenciosamente as dezenas e as preenche na interface.

O objetivo desta implementação é armazenar os critérios da última geração estatística e exibi-los abaixo do bloco de botões da seleção de dezenas.

## Proposed Changes

Os arquivos modificados serão os componentes Livewire baseados em Volt responsáveis por criar e editar fechamentos.

### Componentes Livewire de Fechamento

Ambos os arquivos compartilham lógica muito semelhante, então as alterações serão espelhadas em ambos.

#### [MODIFY] [create.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/create.blade.php)
#### [MODIFY] [edit.blade.php](file:///home/jean/ai_projects/loterias/resources/views/livewire/pages/closings/edit.blade.php)

- **Nova Propriedade**: Adicionar `public ?array $statisticalReport = null;` para reter as informações sobre a seleção estatística.
- **Limpar o Relatório**: Modificar `setBaseNumbersFromResult($numbers, $isStatistical = false)` para incluir lógica de limpeza de relatório. Se `$isStatistical == false`, o relatório deve ser zerado `$this->statisticalReport = null;`.
- **Salvar Informações Estatísticas**: No método `generateStatisticalBaseNumbers()`, antes do final, preencher a variável `$statisticalReport`:
  ```php
  $this->statisticalReport = [
      'total_base' => $totalBase,
      'repetitions' => count($selectedRepeated),
      'new_numbers' => count($selectedNew),
      'repeated_list' => $selectedRepeated,
      'new_list' => $selectedNew,
      'criteria' => [
          'repeated' => "Foram selecionadas " . count($selectedRepeated) . " dezenas do concurso anterior priorizando a maior frequência geral histórica.",
          'new' => "Foram selecionadas " . count($selectedNew) . " dezenas não sorteadas no último concurso, baseando-se na frequência geral e ponderadas para balancear a relação par/ímpar e moldura/centro do jogo."
      ]
  ];
  $this->setBaseNumbersFromResult($generatedGroup, true);
  ```
- **Apresentar na UI**: Imediatamente abaixo da div que contém o botão de "Sugerir por Análise Estatística", injetar a renderização condicional do relatório:
  ```blade
  @if($statisticalReport)
      <div class="mt-6 w-full rounded-2xl border border-emerald-100 bg-emerald-50/50 p-5">
          <h3 class="flex items-center gap-2 text-sm font-bold text-emerald-800 mb-3">
              <svg class="h-5 w-5 text-emerald-600" ...> ... </svg>
              Relatório da Geração Estatística
          </h3>
          <p class="text-sm text-slate-600 mb-4">
              A seleção das <strong>{{ $statisticalReport['total_base'] }} dezenas</strong> foi baseada nos seguintes critérios estatísticos:
          </p>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <!-- Bloco de dezenas repetidas -->
              <div class="rounded-xl bg-white p-4 border border-emerald-100 shadow-sm"> ... </div>
              <!-- Bloco de dezenas inéditas -->
              <div class="rounded-xl bg-white p-4 border border-emerald-100 shadow-sm"> ... </div>
          </div>
      </div>
  @endif
  ```

Também salvaremos uma cópia deste arquivo em `/docs/development_prompts/` como exigido nas regras.

## Verification Plan

### Manual Verification
1. Entrar na criação/edição de um fechamento.
2. Clicar em "Sugerir por Análise Estatística", definir os parâmetros e clicar em "Aplicar Sugestão".
3. Verificar se o relatório estatístico é apresentado abaixo, listando corretamente o número de dezenas repetidas e novas, com as mensagens informativas.
4. Clicar em "Usar último resultado" ou adicionar/remover uma dezena manualmente e verificar se o relatório desaparece, já que a base mudou e a explicação estatística não representa mais o estado atual.
