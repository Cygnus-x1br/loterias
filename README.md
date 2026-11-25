# Sistema Loterias — Lotofácil

Sistema web para geração, fechamento, conferência e análise combinatória de apostas da **Lotofácil** (25 dezenas, sorteio de 15, premiação de 11 a 15 acertos).

O sistema **não promete prever resultados**. Todas as dezenas são tratadas como equiprováveis; estatísticas históricas, temperaturas, ciclos e atrasos são usados apenas como filtros heurísticos, conforme as regras de comunicação definidas em [`docs/prompt_base.MD`](docs/prompt_base.MD) (Item 30).

## Stack

- PHP 8.3, Laravel 13
- Livewire 3 + Volt (páginas single-file), Alpine.js
- Tailwind CSS 4, Vite
- Autenticação: Laravel Breeze (Livewire)
- `phpexperts/combinatorics` (geração de combinações via iteradores) e `phpoffice/phpspreadsheet` (importação do histórico em XLSX)
- Banco: SQLite em desenvolvimento local, MariaDB no Docker/produção; testes em SQLite
- Testes: PHPUnit

## Funcionalidades

| Área | Rota | O que faz |
| --- | --- | --- |
| Dashboard | `/dashboard` | Visão geral, último concurso, ciclo de dezenas, atrasos e distribuição térmica |
| Apostas | `/apostas` | Cadastro manual e listagem de apostas avulsas; apostas `placed`/`checked` ficam bloqueadas |
| Fechamentos | `/fechamentos` | Criação, edição, geração, impressão, exclusão pontual, "refazer" e "completar apostas faltantes" |
| Otimização | `/fechamentos/otimizacao`, `/fechamentos/{id}/otimizar` | Filtros rígidos (pares, primos, Fibonacci, repetidas do último sorteio), pontuação e seleção diversificada |
| Cobertura combinatória | `/fechamentos/cobertura-combinatoria` | Página educativa sobre fechamentos e cobertura |
| Auditoria consolidada | `/fechamentos/auditoria-consolidada` | Auditoria de cobertura exata ou Monte Carlo, executada em fila (job) |
| Análises | `/dashboard/analises-lotofacil`, `.../comparativo` | Frequência, atraso, ciclos, temperatura por concurso (janela móvel de 20 concursos) |
| Simulações | `/simulacoes` | Simulação Monte Carlo e backtesting histórico de fechamentos |
| Financeiro | `/financeiro` | Custo × prêmio por concurso, ROI, por fechamento e por aposta |
| Sorteios | `/sorteios` | CRUD da série histórica (dezenas, ganhadores e rateios) |
| Configurações | `/configuracoes` | Preços das apostas (15 a 20 dezenas) e prêmios fixos por usuário |
| Conta | `/profile` | Perfil, senha, conexões entre usuários (código de compartilhamento) |

### Métodos de fechamento

Implementados como *strategy* em `app/Services/Betting/Generators`, orquestrados por `ClosingGenerator`:

- **integral**: todas as combinações C(m,15) do grupo-base
- **reduced**: fechamento reduzido por heurística gulosa de *set covering* (`SetCoveringService`, até 23 dezenas)
- **wheel**: sistema de roda com dezenas fixas e variáveis
- **random**: apostas aleatórias dentro do grupo-base
- **balanced**: geração equilibrada (pares/ímpares, moldura/centro, repetidas do último concurso, ciclo e atraso)

Combinações de 15 dezenas que já saíram em concursos anteriores são descartadas automaticamente.

### Ciclo de vida de um fechamento

`draft` → `processing` → `completed` → `placed` (concurso e data informados) → `checked` (conferido contra o resultado). Em caso de erro, `failed`. Fechamentos podem ser compartilhados com usuários conectados (bolão).

## Instalação

### Local (SQLite)

```bash
composer setup          # install, .env, key:generate, migrate, npm install, npm run build
php artisan db:seed     # usuário inicial de desenvolvimento
php artisan db:seed --class=HistoricalResultSeeder   # série histórica (database/seeders/historical_results.json)
composer dev            # server + queue:listen + pail + vite
```

O worker de fila (`queue:listen`, já incluído em `composer dev`) é necessário para a auditoria consolidada.

### Docker (desenvolvimento)

`docker-compose.yml` sobe PHP-FPM 8.3, Nginx (porta 8000), MariaDB 11, Redis 7 e Vite.

### Produção

`Dockerfile.prod` + `docker-compose.prod.yml`. O entrypoint (`docker/entrypoint.prod.sh`) roda as migrations e gera os caches. Detalhes em [`docs/docker-prod.md`](docs/docker-prod.md).

## Comandos Artisan

```bash
php artisan app:import-historical-results docs/Lotofácil.xlsx   # importa a série histórica da planilha da Caixa
php artisan lotofacil:calculate-scores                          # recalcula score e ciclo dos resultados históricos
php artisan users:generate-share-codes                          # gera códigos de compartilhamento faltantes
```

## Testes

```bash
php artisan test --compact
php artisan test --compact --filter=NomeDoTeste
vendor/bin/pint --dirty     # formatação
```

## Documentação

| Arquivo | Conteúdo |
| --- | --- |
| [`docs/prompt_base.MD`](docs/prompt_base.MD) | Especificação mestre: definições matemáticas e os 30 itens/módulos do sistema |
| [`docs/metodos_matematicos.md`](docs/metodos_matematicos.md) | Fundamentação: hipergeométrica, fechamentos, cobertura, set covering, metaheurísticas |
| [`docs/prompt_4_fase.md`](docs/prompt_4_fase.md) | Roteiro original em etapas (fase inicial do projeto) |
| [`docs/actual_status.md`](docs/actual_status.md) | Snapshot de status de agosto/2026 (desatualizado) |
| [`docs/docker-prod.md`](docs/docker-prod.md) | Receita do ambiente Docker de produção |
| [`docs/development_prompts/`](docs/development_prompts/) | Planos e walkthroughs de cada feature implementada |
