# Documentação do Projeto "Sistema Loterias"

Este documento detalha o status atual do projeto "Sistema Loterias", o escopo completo, as convenções de design e os próximos passos de desenvolvimento.

## 1. Status Atual e Implementações Realizadas

Até o momento, a fundação do projeto foi estabelecida, com foco na estrutura de geração de apostas e validação de parâmetros.

### Tecnologias Utilizadas

*   **Backend:** PHP, Laravel, Livewire
*   **Frontend:** Blade, Vite, Tailwind CSS
*   **Banco de Dados:** MariaDB
*   **Autenticação:** Laravel Breeze

### Convenções de Design e Padrões de Projeto

*   **Arquitetura de Serviços:** Utilização de classes de serviço (`App\Services`) para encapsular a lógica de negócio, como a geração de apostas (`Betting`).
*   **Injeção de Dependência:** Uso do contêiner de serviços do Laravel para gerenciar dependências.
*   **Interfaces:** Definição de interfaces (`BetGeneratorInterface`) para garantir contratos claros e permitir a troca de implementações (ex: diferentes geradores de apostas).
*   **Generators (PHP):** Utilização de `Generator` para lidar com a geração de grandes volumes de dados (apostas) de forma eficiente em memória, evitando o carregamento de todas as apostas de uma vez.
*   **Form Requests:** Centralização da lógica de validação de requisições HTTP em classes `Form Request`.
*   **PSR-12:** Aderência aos padrões de codificação do PHP.

### Implementações Concluídas (Conhecimento do Modelo)

*   **`BetGeneratorInterface`:** Interface que define o contrato para os geradores de apostas, incluindo os métodos `validate(Closing $closing): void` e `generate(Closing $closing): Generator`.
    *   **Localização:** `app/Services/Betting/Generators/BetGeneratorInterface.php`
*   **`ReducedBetGenerator`:** Implementação do gerador de apostas para o método "Reduzido".
    *   **Localização:** `app/Services/Betting/Generators/ReducedBetGenerator.php`
    *   **Funcionalidade:**
        *   Implementa `BetGeneratorInterface`.
        *   Método `validate()`: Valida os parâmetros de entrada (`base_numbers`, `bet_size`, `guarantee_hits`, `guarantee_points`) para o fechamento reduzido.
        *   Método `generate()`: Gera combinações de `bet_size` dezenas a partir do `base_numbers` usando a biblioteca `phpexperts/combinatorics`. Limita o número de apostas geradas pelo `planned_bets` (ou 100 por padrão) e evita duplicatas.
        *   **Observação:** A lógica de "fechamento reduzido" atual é uma simplificação e não otimiza a garantia de acertos de forma avançada.
*   **`ClosingGenerator`:** Serviço responsável por orquestrar a geração e persistência dos fechamentos.
    *   **Localização:** `app/Services/Betting/ClosingGenerator.php` (assumido com base na discussão anterior).
    *   **Funcionalidade:** Recebe um objeto `Closing`, determina o tipo de gerador a ser usado (ex: `ReducedBetGenerator`), invoca o método `generate()` e lida com a persistência das apostas geradas.
*   **Integração de Biblioteca de Combinatória:** Utilização da biblioteca `phpexperts/combinatorics` para a geração eficiente de combinações.
    *   **Instalação:** `composer require phpexperts/combinatorics`
    *   **Uso:** A classe `PhpExperts\Combinatorics\CombinationsGenerator` é instanciada e seu método `generate()` é utilizado para obter um iterador de combinações.

### Problemas Resolvidos Durante o Desenvolvimento

*   Incompatibilidade de tipo de retorno entre `ReducedBetGenerator::generate()` e `BetGeneratorInterface::generate()`.
*   Erro "Class not found" para a biblioteca de combinatória devido a nome de classe incorreto e uso de método estático inexistente.
*   Erro "Class contains 1 abstract method" devido à ausência do método `validate()` em `ReducedBetGenerator`.
*   Problemas de cache do Laravel e conexão com o banco de dados (`mariadb`) durante a execução de comandos Artisan.
*   Avisos de "Undefined type" na IDE relacionados à biblioteca de combinatória.

## 2. Escopo Completo do Projeto "Sistema Loterias"

O projeto visa criar uma plataforma completa para gerenciamento e análise de apostas em loterias, com as seguintes funcionalidades planejadas:

### Entidades Principais

*   `User`
*   `Bet` (Aposta)
*   `Closing` (Fechamento)
*   `ClosingBet` (Apostas associadas a um fechamento)
*   `Simulation` (Simulação de resultados)
*   `Analysis` (Análise de dados)
*   `HistoricalResult` (Resultados históricos de sorteios)

### Módulos Funcionais

*   **Cadastro e Gerenciamento de Apostas:**
    *   Criação manual de apostas (seleção de dezenas).
    *   Listagem, visualização, edição e exclusão de apostas.
    *   Filtros e paginação.
*   **Gerenciamento de Fechamentos:**
    *   Criação de fechamentos com diferentes métodos (Integral, Reduzido, Aleatório, Equilibrado, Sistema de Roda).
    *   Associação de apostas existentes a um fechamento.
    *   Listagem, visualização, edição, duplicação e exclusão de fechamentos.
    *   Armazenamento de parâmetros de fechamento em JSON para flexibilidade.
*   **Geração de Combinações:**
    *   Implementação de diversos algoritmos de geração de apostas.
*   **Análise de Cobertura e Garantias:**
    *   Verificação de cenários cobertos e não cobertos.
    *   Comparação entre diferentes fechamentos.
*   **Otimização e Simulações:**
    *   Uso de algoritmos avançados (set covering, heurísticas, programação inteira, busca local, simulated annealing, algoritmos genéticos) para otimização.
    *   Simulações estatísticas.
*   **Dados Históricos e Estatísticas:**
    *   Inclusão de todas as séries já sorteadas para fins estatísticos.
    *   Análise de repetição de combinações e números isolados.
    *   Filtro de dezenas do grupo-base com base em resultados anteriores (ex: repetir 10 números do sorteio anterior).
*   **Conferência de Apostas:**
    *   Indicação de apostas ou conjuntos de apostas efetivados.
    *   Associação com números de sorteio.
    *   Sistema de conferência de resultados.

## 3. Roteiro de Próximas Implementações

Com base no escopo completo e no status atual, o roteiro a seguir prioriza a construção da fundação de persistência e funcionalidades básicas antes de mergulhar em algoritmos mais complexos.

### Etapa 1: Fundação de Domínio (Persistência Inicial)

*   **Migrations:**
    *   `create_users_table` (se já não existir)
    *   `create_bets_table` (para armazenar `user_id`, `numbers` (JSON), `origin`, `method`, `status`, `hits`, `observations`, `timestamps`)
    *   `create_closings_table` (para armazenar `user_id`, `name`, `method`, `base_numbers` (JSON), `bet_count`, `budget`, `status`, `parameters` (JSON), `timestamps`)
    *   `create_closing_bet_table` (tabela pivô para relacionamento N:N entre `closings` e `bets`)
*   **Models:**
    *   `User` (se já não existir)
    *   `Bet` (com casts para `numbers` como array)
    *   `Closing` (com casts para `base_numbers` e `parameters` como array)
*   **Relacionamentos:**
    *   `User hasMany Bet`
    *   `User hasMany Closing`
    *   `Closing belongsTo User`
    *   `Closing belongsToMany Bet`
    *   `Bet belongsToMany Closing`
*   **Form Requests:**
    *   `StoreBetRequest` (validações para dezenas: entre 1-25, não repetidas, quantidade correta)
    *   `StoreClosingRequest` (validações para nome, orçamento, método, parâmetros básicos)
    *   `UpdateClosingRequest` (similar ao StoreClosingRequest)
*   **Enums/Constantes:** Para `status` e `method` de apostas e fechamentos.
*   **Policies Básicas:** Para autorização de acesso a `Bet` e `Closing`.

### Etapa 2: Módulo "Nova Aposta" (CRUD Básico de Apostas)

*   **Frontend (Livewire/Blade):**
    *   Componente Livewire para "Nova Aposta":
        *   Permitir ao usuário selecionar 15 dezenas (com contador visual).
        *   Exibir mensagens de validação em tempo real.
        *   Botão para salvar a aposta.
    *   Componente Livewire para "Todas as Apostas":
        *   Listar as apostas do usuário.
        *   Funcionalidades de visualização, edição e exclusão.
        *   Filtros e paginação básicos.
*   **Backend (Livewire Component Actions):**
    *   Conectar o frontend aos `Form Requests` e ao `Bet` model para persistência.
    *   Implementar a lógica de salvamento, atualização e exclusão.

### Etapa 3: Módulo "Meus Fechamentos" (CRUD Básico de Fechamentos)

*   **Frontend (Livewire/Blade):**
    *   Componente Livewire para "Criar Fechamento":
        *   Formulário para nome, método, grupo-base, orçamento, parâmetros (JSON).
        *   Opção para associar apostas existentes ao fechamento.
    *   Componente Livewire para "Listar Fechamentos":
        *   Listar os fechamentos do usuário.
        *   Funcionalidades de visualização de detalhes, edição, duplicação e exclusão.
*   **Backend (Livewire Component Actions):**
    *   Conectar o frontend aos `Form Requests` e ao `Closing` model para persistência.
    *   Implementar a lógica de salvamento, atualização, duplicação e exclusão (com confirmação).
    *   **Edição de Fechamento:** Habilitar a edição de parâmetros como `planned_bets` (para geração integral), `guarantee_hits`, `guarantee_points` (para reduzido), etc.

### Etapa 4: Geração de Combinações (Conexão com Algoritmos)

*   **`IntegralBetGenerator`:**
    *   Criar `app/Services/Betting/Generators/IntegralBetGenerator.php`.
    *   Implementar `BetGeneratorInterface`.
    *   Método `validate()`: Incluir validação para o número de combinações (C(n,k)) para evitar sobrecarga, talvez com um limite configurável.
    *   Método `generate()`: Usar `PhpExperts\Combinatorics\CombinationsGenerator` para gerar todas as combinações de `bet_size` a partir de `base_numbers`.
*   **`RandomBetGenerator`:**
    *   Criar `app/Services/Betting/Generators/RandomBetGenerator.php`.
    *   Implementar `BetGeneratorInterface`.
    *   Método `generate()`: Gerar um número `X` de apostas aleatórias, garantindo que as dezenas estejam dentro do `base_numbers` e sejam únicas por aposta.
*   **Conexão da Geração:**
    *   Integrar os geradores (`ReducedBetGenerator`, `IntegralBetGenerator`, `RandomBetGenerator`) ao `ClosingGenerator`.
    *   Quando um fechamento for "gerado", o `ClosingGenerator` invocará o `generate()` do gerador apropriado.
    *   As apostas resultantes serão salvas na tabela `bets` e associadas ao `closing` via `closing_bet`.

### Etapa 5: Dados Históricos e Estatísticas

*   **Migrations:**
    *   `create_historical_results_table` (para armazenar `lottery_name`, `draw_number`, `draw_date`, `drawn_numbers` (JSON), `timestamps`).
*   **Model:**
    *   `HistoricalResult` (com cast para `drawn_numbers` como array).
*   **Serviço de Importação:**
    *   `app/Services/LotteryData/HistoricalDataImporter.php`: Serviço para importar resultados históricos de sorteios (pode ser via API ou arquivo CSV).
*   **Funcionalidades de Análise:**
    *   **Repetição de Combinações:** Serviço para verificar quantas vezes uma combinação de dezenas já apareceu na série histórica.
    *   **Repetição de Números Isolados:** Serviço para analisar a frequência de cada dezena na série histórica.
    *   **Filtro de Dezenas do Grupo-Base:** Implementar lógica para sugerir dezenas para o `base_numbers` com base em resultados anteriores (ex: dezenas mais sorteadas, dezenas do último sorteio).

### Etapa 6: Conferência de Apostas

*   **Migrations:**
    *   Adicionar campos a `bets` ou `closing_bet` para `draw_id` (ID do sorteio apostado), `is_effective` (booleano se a aposta foi efetivada), `checked_at` (timestamp da conferência).
*   **Model:**
    *   Atualizar `Bet` e `ClosingBet` models.
*   **Serviço de Conferência:**
    *   `app/Services/Betting/BetChecker.php`:
        *   Método `check(Bet $bet, HistoricalResult $result)`: Compara as dezenas da aposta com as dezenas sorteadas e retorna o número de acertos.
        *   Método `checkClosing(Closing $closing, HistoricalResult $result)`: Confere todas as apostas de um fechamento.
*   **Frontend (Livewire/Blade):**
    *   Interface para associar apostas/fechamentos a um sorteio específico.
    *   Botão para "Conferir Apostas" que exibe os acertos.

### Etapa 7: Refinamento do Fechamento Reduzido Avançado

*   **Pesquisa:** Estudar algoritmos de fechamento reduzido (ex: algoritmos de cobertura, heurísticas).
*   **`ReducedBetGenerator` (Refatoração):**
    *   Modificar o método `generate()` para implementar um algoritmo que utilize `guarantee_hits` e `guarantee_points` para otimizar a seleção das apostas, buscando o menor número de apostas para a garantia desejada.
    *   Pode ser necessário introduzir classes auxiliares ou até mesmo bibliotecas mais especializadas para combinatória otimizada.

### Etapa 8: Análise de Cobertura, Otimização e Simulações

*   **Serviços de Análise:**
    *   `app/Services/Betting/CoverageAnalyzer.php`: Para verificar a cobertura de cenários.
    *   `app/Services/Betting/SimulationRunner.php`: Para executar simulações estatísticas.
*   **Algoritmos Avançados:**
    *   Implementar ou integrar soluções para set covering, heurísticas, programação inteira, etc., conforme a necessidade de otimização.

Este roteiro fornece uma sequência lógica e incremental para o desenvolvimento do Sistema Loterias, começando pela fundação e adicionando complexidade de forma controlada.

