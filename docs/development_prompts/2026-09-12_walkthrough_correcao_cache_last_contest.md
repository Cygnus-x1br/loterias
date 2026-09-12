# Walkthrough: Correção de Cache de Serialização do Concurso Anterior

**Data de Criação:** 2026-09-12  
**Fase:** Correção de Bug / Caching e Geração de Fechamentos

---

## 1. Resumo das Alterações

### Causa Raiz
Ao gerar o primeiro fechamento equilibrado com filtro de dezenas repetidas do concurso anterior, `LotofacilStatisticsService::getLastContest()` colocava no cache a instância completa do Eloquent Model `HistoricalResult`. Com o driver `CACHE_STORE=database` e a diretiva de segurança `cache.serializable_classes = false` padrão do Laravel 11/12/13, ao gerar o segundo ou terceiro fechamento, o cache tentava deserializar o modelo e retornava `__PHP_Incomplete_Class`. Por conta do tipo de retorno estrito `?HistoricalResult`, o PHP disparava um `TypeError`.

### Modificações Realizadas

1. **`app/Services/LotofacilStatisticsService.php`**:
   - Atualizado o método `getLastContest()`:
     - O callback do `Cache::remember('last_contest', ...)` agora armazena apenas os atributos primitivos do modelo (`$last->getAttributes()`).
     - Ao resgatar do cache, hidrata o modelo via `(new HistoricalResult)->newFromBuilder($cachedAttributes)`.
     - Nenhum tipo de classe customizada precisa ser deserializado no cache store, garantindo segurança e compatibilidade tanto em desenvolvimento quanto em produção.
     - A assinatura pública `public function getLastContest(): ?HistoricalResult` foi 100% mantida.

2. **`tests/Feature/Services/Betting/BalancedBetGeneratorTest.php`**:
   - Adicionado o teste `test_generates_multiple_balanced_closings_consecutively_without_cache_serialization_error`, validando a geração consecutiva de múltiplos fechamentos sem estourar exceções de serialização no cache.

3. **`tests/Feature/Services/BetScoringServiceTest.php`**:
   - Ajustados os mocks para suprir as novas dependências do ciclo de dezenas e análise de atraso introduzidas no serviço de scoring.

---

## 2. Validação e Testes

- **Cache limpo:** `php artisan cache:clear` executado com sucesso.
- **Tinker:** Chamadas consecutivas a `app(LotofacilStatisticsService::class)->getLastContest()` testadas e validadas retornando instâncias válidas de `App\Models\HistoricalResult`.
- **Testes automatizados executados:**
  - `php artisan test --compact tests/Feature/Services/Betting/BalancedBetGeneratorTest.php` (13 testes passando).
  - `php artisan test --compact tests/Feature/Services/BetScoringServiceTest.php` (2 testes passando).
  - `php artisan test --compact` (108 testes passando, 2302 asserções).
- **Padronização:** `vendor/bin/pint --dirty --format agent` aplicado e validado.
