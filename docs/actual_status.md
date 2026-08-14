# Status Atual do Projeto Loterias

Este documento apresenta uma avaliação detalhada do que já foi implementado no projeto "Sistema Loterias" e o que ainda falta ser desenvolvido, com base na arquitetura, nos documentos de referência matemática, nos prompts base e nas novas diretrizes e features solicitadas.

## 1. Avaliação do que já foi implementado

A infraestrutura básica e de persistência do domínio principal já foram estabelecidas. O foco tem sido construir uma fundação sólida para a geração e gestão de apostas e fechamentos.

### 1.1 Modelos, Migrations e Factories Desenvolvidos

A base de dados foi modelada para suportar a arquitetura sugerida na Fase 1 e 2 do projeto.

**Migrations:**
- `0001_01_01_000000_create_users_table.php` (Tabela de usuários)
- `2026_08_11_184635_create_bets_table.php` (Tabela de apostas)
- `2026_08_11_194049_create_closings_table.php` (Tabela de fechamentos)
- `2026_08_11_195912_add_closing_id_to_bets_table.php` (Relacionamento entre apostas e fechamentos)
- Migrations auxiliares (`create_cache_table`, `create_jobs_table`)

**Models:**
- `app/Models/User.php`
- `app/Models/Bet.php` (Com casts para os arrays de dezenas)
- `app/Models/Closing.php` (Com casts para arrays e JSON parameters)

**Factories:**
- `database/factories/UserFactory.php`
- `database/factories/BetFactory.php`
- `database/factories/ClosingFactory.php`

### 1.2 Estrutura de Geração de Fechamentos (Services)

O sistema de geração foi arquitetado com base no padrão *Strategy*, definindo contratos claros para cada tipo de gerador de apostas, cumprindo com a separação de responsabilidades recomendada:

- `app/Services/Betting/Generators/BetGeneratorInterface.php`: Define o contrato para os geradores, exigindo métodos como `validate` e `generate`.
- `app/Services/Betting/ClosingGenerator.php`: Orquestrador responsável por receber um objeto `Closing` e decidir qual gerador utilizar, gerenciando também a persistência.

**Geradores Implementados:**
- `IntegralBetGenerator.php`: Geração do fechamento integral combinatório $C(n,k)$.
- `ReducedBetGenerator.php`: Geração inicial para fechamentos reduzidos, com implementação simplificada usando combinatória.
- `RandomBetGenerator.php`: Geração de apostas aleatórias válidas dentro de um escopo.
- `WheelBetGenerator.php`: Implementação de Sistema de Roda com dezenas fixas e variáveis.
- `BalancedBetGenerator.php`: Geração balanceada usando heurísticas de pares/ímpares, somas, primos, etc.

*Metodologia Utilizada:* A geração utiliza a biblioteca `phpexperts/combinatorics` para iteradores em memória (Generators PHP), garantindo que grandes volumes de combinações não estourem a memória RAM do servidor.

### 1.3 Requests e Validações

O projeto já utiliza as convenções do Laravel com *Form Requests* para garantir a integridade dos dados na entrada:
- `app/Http/Requests/StoreBetRequest.php`
- `app/Http/Requests/StoreClosingRequest.php`

### 1.4 Frontend e UI (Livewire/Blade Volt)

Telas para o gerenciamento de fechamentos já foram criadas:
- `resources/views/livewire/pages/closings/create.blade.php`: Componente de criação.
- `resources/views/livewire/pages/closings/index.blade.php`: Listagem dos fechamentos.
- `resources/views/livewire/pages/closings/show.blade.php`: Visualização detalhada.

---

## 2. Quadro de Acompanhamento (Desenvolvido vs Pendente)

| Módulo/Funcionalidade | Status | Observação |
| :--- | :---: | :--- |
| **Persistência Básica (Models, Migrations, Factories)** | Concluído | Relacionamentos básicos criados. |
| **Serviços de Geradores de Fechamento** | Concluído | Algoritmos básicos integrados. |
| **Integração Livewire (Telas de Fechamento)** | Concluído | Criação, listagem e exibição implementados. |
| **Validação Dinâmica de Parâmetros (Form Requests)** | Concluído | Regras definidas para diferentes métodos de fechamento. |
| **Edição de Fechamentos Salvos** | Pendente | Permitir alterar parâmetros após criado (qtde jogos, ímpares, etc). |
| **Módulo Histórico de Sorteios via CSV** | Pendente | Importação e tabela `historical_results` para série histórica via CSV. |
| **Estatísticas e Análise Histórica** | Pendente | Repetição de combinações, sequências e números isolados em toda a série. |
| **Filtro de Dezenas Baseado em Resultados** | Pendente | Sugerir/filtrar dezenas no grupo base a partir dos últimos sorteios. |
| **Efetivação de Apostas e Sorteios** | Pendente | Marcar fechamento/aposta como "efetivado" e vincular ao número do concurso/sorteio. |
| **Sistema de Conferência de Apostas** | Pendente | Algoritmo que cruza resultados do concurso efetivado com as apostas cadastradas e os prêmios. |
| **Custo das Apostas e Premiações (Config)** | Pendente | Campo para definir valor da aposta, premiação fixa e calcular o custo total do fechamento. |
| **Novos Itens de Menu / Páginas** | Pendente | Menus: "Cálculos matemáticos", "Cobertura combinatória", "Otimização" e "Simulações". |
| **Simulações e Análise de Cobertura** | Pendente | Cálculos hipergeométricos e cobertura combinatória (Módulo 5). |
| **Otimização Avançada (Set Covering / Heurísticas)** | Pendente | Refatorar gerador reduzido, implementar busca gulosa, algoritmos de otimização. |

---

## 3. Lista de Tarefas a Executar (Próximos Passos)

Com base nas definições atuais, a seguinte ordem de execução é sugerida para cobrir as funcionalidades pendentes:

### Fase 1: Histórico, Estatísticas e Filtragem
- [ ] Criar a tabela (Migration) e Model `HistoricalResult` para armazenar o histórico de resultados.
- [ ] Implementar comando/interface para a importação de arquivos CSV com os resultados históricos.
- [ ] Desenvolver a camada de serviço de estatística:
  - Analisar repetição de números isolados em todas as séries.
  - Analisar quantas sequências e quais combinações já se repetiram na série histórica.
- [ ] Adicionar na UI de criação de fechamentos a opção de usar os últimos resultados como base (ex: "repetir 10 números do sorteio anterior").

### Fase 2: Gestão de Fechamentos e Custos
- [ ] Adicionar configuração de custos: definir valor unitário de apostas e prêmios fixos, e calcular o custo total de um fechamento.
- [ ] Adicionar a funcionalidade de editar um fechamento salvo (ajustar parâmetros de geração como qtde. de ímpares, total de jogos, etc).

### Fase 3: Efetivação e Conferência
- [ ] Adicionar campos em `bets` / `closings` para registrar efetivação (ex: booleano `is_effective`) e número do concurso apostado.
- [ ] Implementar o módulo/Sistema de Conferência de Apostas que compara o histórico (concurso) com as apostas efetivadas, demonstrando os prêmios (acertos).

### Fase 4: Otimização, Matemática e Estrutura de Menu
- [ ] Implementar os novos itens de menu na UI principal:
  - Cálculos matemáticos
  - Cobertura combinatória
  - Otimização
  - Simulações
- [ ] Desenvolver o analisador de cobertura (Coverage Combinatória e Simulações).
- [ ] Iniciar a implementação das ferramentas de Otimização no gerador reduzido (Set Covering, Busca Gulosa, etc).

---

## 4. Sugestões de Implementação e Melhorias de Desempenho

1. **Geração Assíncrona via Jobs/Filas:** Para fechamentos integrais com muitas dezenas (ex: 20 dezenas geram 15.504 apostas), a requisição web pode sofrer timeout. O método `generate` no `ClosingGenerator` deve disparar *Jobs* para gerar as apostas de forma assíncrona.

2. **Otimização do ReducedBetGenerator:** O fechamento reduzido verdadeiro (Set Covering) é custoso e matematicamente complexo. Começar utilizando abordagens heurísticas (ex. "Gulosa") evitará travamentos do servidor e proverá um MVP muito mais rápido.

3. **Componentização Blade/Volt:** Algumas lógicas de validação e front-end no `create.blade.php` estão extensas. Sugere-se extrair partes da UI em componentes anônimos menores.
