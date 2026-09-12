# Plano de Implementação: Correção de Serialização do Cache no LotofacilStatisticsService

**Data de Criação:** 2026-09-12  
**Fase:** Correção de Bug / Caching e Geração de Fechamentos

---

## 1. Descrição do Problema

Quando o usuário tenta gerar apostas para um segundo ou terceiro fechamento equilibrado (ou qualquer fechamento que faça uso do último sorteio):
- No primeiro fechamento gerado, o método `LotofacilStatisticsService::getLastContest()` consulta o banco de dados e armazena o resultado em cache (`Cache::remember('last_contest', ...)`).
- Com o driver de cache definido como `database` e `cache.serializable_classes` mantido como `false` por padrão no Laravel 11/12/13, o valor recuperado do cache no segundo fechamento não pode ser deserializado como `App\Models\HistoricalResult`, retornando uma instância de `__PHP_Incomplete_Class`.
- O PHP dispara um `TypeError` fatal porque o retorno esperado de `getLastContest()` é estritamente `?HistoricalResult`.
- Isso causa falha na UI (mensagem amigável no ambiente local e erro 500 em produção).

---

## 2. Abordagem Escolhida (Opção 1)

Evitar armazenar o objeto Eloquent serializado (`HistoricalResult`) no cache.

Em vez disso:
1. Armazenar apenas os atributos brutos do modelo em formato de array associativo (`$lastContest->getAttributes()`) ou `null`.
2. Ao recuperar do cache, se houver dados, hidratar o modelo usando `(new HistoricalResult())->newFromBuilder($cachedAttributes)`.
3. Isso garante que:
   - Nenhum objeto PHP customizado dependa de `unserialize(..., ['allowed_classes' => ...])`. O cache armazena apenas tipos nativos seguros (arrays e escalares).
   - A assinatura do método `public function getLastContest(): ?HistoricalResult` permanece **100% inalterada**, garantindo retrocompatibilidade total com qualquer código consumidor.
   - O objeto retornado é uma instância legítima de `HistoricalResult` com `exists = true` e todos os acessores/casts normais funcionando.

---

## 3. Arquivos a serem modificados

1. `app/Services/LotofacilStatisticsService.php`:
   - Atualizar a implementação de `getLastContest()` para armazenar e hidratar via atributos simples.
2. `tests/Feature/Services/Betting/BalancedBetGeneratorTest.php`:
   - Adicionar teste garantindo que múltiplas chamadas consecutivas a `getLastContest()` (com cache preenchido) retornem uma instância válida de `HistoricalResult` e permitam a geração sequencial de fechamentos equilibrados sem erros de serialização.
3. `tests/Feature/Services/BetScoringServiceTest.php`:
   - Corrigir a pendência no mock de `getDecadesCycleAnalysis` para manter a suíte de testes passando.

---

## 4. Plano de Verificação

1. Limpar o cache atual do banco de dados: `php artisan cache:clear`.
2. Executar teste unitário e de integração via Docker:
   - `php artisan test --compact tests/Feature/Services/Betting/BalancedBetGeneratorTest.php`
   - `php artisan test --compact tests/Feature/Services/BetScoringServiceTest.php`
   - `php artisan test --compact`
3. Simular via Tinker chamadas repetidas a `getLastContest()`.
