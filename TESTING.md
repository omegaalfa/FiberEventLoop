# 🧪 Guia de Testes - FiberEventLoop

Este documento descreve como executar os testes e entender a cobertura de teste do FiberEventLoop.

## 📋 Índice

- [Configuração](#configuração)
- [Executar Testes](#executar-testes)
- [Estrutura de Testes](#estrutura-de-testes)
- [Cobertura de Código](#cobertura-de-código)
- [Testes de Performance](#testes-de-performance)
- [Troubleshooting](#troubleshooting)

---

## Configuração

### Pré-requisitos

- PHP 8.2+
- Composer
- PHPUnit 11.5+

### Instalação

```bash
# Instalar dependências (incluindo dev)
composer install

# Ou atualizar se já existente
composer update
```

---

## Executar Testes

### Rodar Todos os Testes

```bash
# Comando padrão
composer test

# Ou direto com PHPUnit
./vendor/bin/phpunit

# Com phpunit.xml.dist
./vendor/bin/phpunit -c phpunit.xml.dist
```

### Rodar Suites Específicas

```bash
# Apenas testes core
composer test -- --testsuite "Core Tests"
./vendor/bin/phpunit --testsuite "Core Tests"

# Apenas testes de componentes
composer test -- --testsuite "Component Tests"

# Apenas testes de integração
composer test -- --testsuite "Integration Tests"

# Apenas testes de performance
composer test -- --testsuite "Performance Tests"
```

### Rodar Testes Específicos

```bash
# Apenas uma classe de teste
./vendor/bin/phpunit tests/FiberEventLoopTest.php

# Apenas um método de teste
./vendor/bin/phpunit --filter testAfterTimer

# Testes que contêm "Timer"
./vendor/bin/phpunit --filter Timer

# Testes que contêm "Performance"
./vendor/bin/phpunit --filter Performance
```

### Opções Úteis

```bash
# Verbose (mostra cada teste)
./vendor/bin/phpunit --verbose
composer test -- --verbose

# Para no primeiro erro
./vendor/bin/phpunit --stop-on-failure
composer test -- --stop-on-failure

# Mostra apenas falhas
./vendor/bin/phpunit --no-coverage

# Executa com testdox (formato legível)
./vendor/bin/phpunit --testdox
./vendor/bin/phpunit --testdox-text=php://stdout
```

---

## Estrutura de Testes

### Arquivo: tests/FiberEventLoopTest.php

**Testa:** Funcionalidades core do FiberEventLoop

| Teste | Descrição |
|-------|-----------|
| `testLoopExecutesAndStops` | Loop executa e para corretamente |
| `testAfterTimer` | Timer `after()` executa com delay correto |
| `testRepeatTimer` | Timer `repeat()` executa número correto de vezes |
| `testRepeatWithLimitedTimes` | Repeat respeita limite de execuções |
| `testInfiniteRepeat` | Repeat sem limite executa indefinidamente |
| `testCancelTimer` | `cancel()` previne execução de timer |
| `testStopLoop` | `stop()` para o loop gracefully |
| `testDefer` | `defer()` executa na próxima iteração |
| `testMultipleDefers` | Múltiplos defers executam em ordem |
| `testErrorsCapturing` | Erros são capturados em `getErrors()` |
| `testGetMetrics` | Métricas são registradas corretamente |
| `testOptimizationLevel*` | Todos os modos de otimização funcionam |
| `testMultipleOperationsSimultaneously` | Múltiplas ops concorrem |
| `testPreciseScheduling` | Timers têm precisão de agendamento |
| `testLoopWithNoWork` | Loop termina rapidamente sem trabalho |
| `testCancelById` | Operações podem ser canceladas por ID |
| `testExceptionDoesNotStopLoop` | Exceções não param o loop |

**Total:** 24 testes

### Arquivo: tests/TimerManagerTraitTest.php

**Testa:** Funcionalidades específicas de timers

| Teste | Descrição |
|-------|-----------|
| `testSetInterval` | `setInterval()` funciona como alternativa |
| `testTimerPrecision` | Timers têm precisão temporal |
| `testMultipleRepeats` | Múltiplos repeats execem independentemente |
| `testCancelRepeat` | Repeat pode ser cancelado |
| `testZeroDelayAfter` | Delay zero executa imediatamente |
| `testNegativeDelayAfter` | Delay negativo é tratado |
| `testDecimalDelays` | Suporta delays decimais (0.1 = 100ms) |
| `testMultipleTimersInSameIteration` | Múltiplos timers executam na mesma iteração |
| `testTimerChaining` | Timers podem agendar outros timers |
| `testRepeatLargeCount` | Repeat com 50+ iterações funciona |
| `testVerySmallDelay` | Delays muito pequenos (1ms) funcionam |
| `testAfterWithIntegerSeconds` | Suporta integer e float |
| `testRepeatWithIntegerInterval` | Repeat com integer interval |
| `testMultipleAftersVariedDelays` | After com delays variados |
| `testRepeatDoesntExceedLimit` | Repeat não ultrapassa limite |

**Total:** 20 testes

### Arquivo: tests/StreamManagerTraitTest.php

**Testa:** Funcionalidades de TCP/Streams

| Teste | Descrição |
|-------|-----------|
| `testCreateServerSocket` | Socket de servidor pode ser criado |
| `testListenAcceptsConnection` | `listen()` aceita conexões TCP |
| `testOnReadableBasic` | `onReadable()` lê dados de stream |
| `testOnWritableBasic` | `onWritable()` escreve dados |
| `testEchoServer` | Echo server completo funciona |
| `testMultipleConnections` | Múltiplas conexões simultâneas |
| `testCancelListen` | `cancel()` para de aceitar conexões |
| `testCancelOnReadable` | `cancel()` para leitura de stream |
| `testInvalidStreamThrowsException` | Stream inválido lança exceção |
| `testLargeDataTransfer` | Transferência de arquivos grandes |
| `testConnectionClosed` | EOF é detectado corretamente |

**Total:** 11 testes

### Arquivo: tests/IntegrationTest.php

**Testa:** Interações entre componentes

| Teste | Descrição |
|-------|-----------|
| `testTimersWithDefer` | Timers e defers funcionam juntos |
| `testRepeatWithMultipleAfter` | Repeat + multiple afters |
| `testExecutionPriority` | Prioridade de execução é respeitada |
| `testChainedOperations` | Operações podem ser agendadas umas nas outras |
| `testCancellationChain` | Cancelamentos em cadeia |
| `testConcurrentOperations` | Grande volume de operações concorrem |
| `testOptimizationLevelSwitching` | Trocar modo de otimização em runtime |
| `testMultipleErrors` | Múltiplos erros são capturados |
| `testMetricsWithMultipleOperations` | Métricas são precisas com carga |
| `testStopDuringOperations` | Stop funciona durante execução |
| `testEmptyLoopExecution` | Loop vazio termina rapidamente |
| `testLoopReuseAfterCompletion` | Loop pode ser reusado |
| `testDeferInsideAfter` | Defer dentro de after |
| `testRepeatSchedulingRepeat` | Repeat agendando repeat |
| `testErrorRecovery` | Loop continua após erro |
| `testLargeVolumeOperations` | 1000+ operações simultâneas |
| `testConsistentStateAfterRun` | Estado é consistente após execução |

**Total:** 17 testes

### Arquivo: tests/PerformanceTest.php

**Testa:** Performance e comportamento sob carga

| Teste | Descrição |
|-------|-----------|
| `testDeferThroughput` | 10k defers/sec throughput |
| `testTimerThroughput` | 500+ timers/sec throughput |
| `testDeferLatency` | Latência de defers < 10ms |
| `testTimerLatency` | Latência de timers < 5ms |
| `testIdleCpuUsage` | Idle adaptativo funciona |
| `testTimerScalability` | 5000 timers escalável |
| `testIterationRate` | Taxa de iterações > 1000/sec |
| `testHighVolumeRepeats` | 100+ repeats simultâneos |
| `testMemoryUnderLoad` | < 10MB para 10k operações |
| `testLoadDistribution` | Distribuição uniforme de carga |
| `testTimingAccuracyUnderLoad` | Timing preciso sob carga |
| `testErrorRecoveryUnderLoad` | Recuperação de erro sob carga |
| `testLongRunningStability` | Estabilidade de longa duração |
| `testLoadSpike` | Picos de carga são tratados |

**Total:** 14 testes

---

## Cobertura de Código

### Gerar Relatório de Cobertura

```bash
# HTML report
./vendor/bin/phpunit --coverage-html=coverage/html

# Clover XML
./vendor/bin/phpunit --coverage-clover=coverage/clover.xml

# Text report (terminal)
./vendor/bin/phpunit --coverage-text

# Tudo junto
composer test -- --coverage-html=coverage --coverage-text
```

### Visualizar Cobertura

```bash
# Abrir relatório HTML no navegador
open coverage/html/index.html       # macOS
xdg-open coverage/html/index.html   # Linux
start coverage/html/index.html      # Windows
```

### Metas de Cobertura

```
Classes:    > 90%
Methods:    > 85%
Lines:      > 80%
```

---

## Testes de Performance

Os testes em `PerformanceTest.php` emitem relatórios durante execução:

```bash
./vendor/bin/phpunit tests/PerformanceTest.php --verbose
```

Saída esperada:

```
Defer Throughput: 500000 ops/sec
Timer Throughput: 2000 timers/sec
Defer Latency: 0.050 ms
Timer Latency (10ms target): 2.34 ms error
...
```

---

## Troubleshooting

### Erro: "Fatal error: Uncaught Error: Class not found"

**Causa:** Autoloader não carregado

**Solução:**
```bash
composer install
composer dump-autoload
```

### Erro: "Call to undefined function phpunit()"

**Solução:** Use caminho completo
```bash
./vendor/bin/phpunit
```

### Testes muito lentos

**Motivo:** Testes de performance são lentos por design

**Solução:** Use filter para rodar apenas testes rápidos
```bash
./vendor/bin/phpunit --exclude-group slow
```

### Erro: "Socket bind failed"

**Causa:** Porta já em uso

**Solução:** Aguarde ou mude porta dinâmica (testes usam 127.0.0.1:0)

### Testes falhando por timeout

**Solução:** Aumente timeout do PHPUnit
```bash
./vendor/bin/phpunit --timeout-for-large-tests=10
```

---

## CI/CD Integration

### GitHub Actions

```yaml
name: Tests

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php-version: ['8.2', '8.3']
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php-version }}
          extensions: sockets
      - run: composer install
      - run: composer test
```

---

## Boas Práticas

✅ **Faça:**
- Execute `composer test` antes de fazer commit
- Use `--stop-on-failure` durante desenvolvimento
- Mantenha cobertura > 80%
- Escreva testes para novo código

❌ **Não faça:**
- Ignores falhas de teste
- Remova testes para passar
- Aumente tolerância de timing

---

## Contribuindo com Testes

Ao adicionar novos recursos:

1. Escreva teste primeiro (TDD)
2. Implemente recurso
3. Teste passa
4. Mantenha cobertura acima de 80%

Exemplo estrutura de teste:

```php
public function testNewFeature(): void
{
    // Arrange (Preparar)
    $loop = new FiberEventLoop();
    $result = null;

    // Act (Agir)
    $loop->newMethod(function() use (&$result) {
        $result = 'executed';
    });
    $loop->run();

    // Assert (Verificar)
    $this->assertEquals('executed', $result);
}
```

---

## Relatório de Teste

Para gerar relatório completo:

```bash
./vendor/bin/phpunit \
  --verbose \
  --testdox \
  --coverage-text \
  --coverage-html=coverage
```

---

## Links Úteis

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Testing Best Practices](https://phpunit.de/getting-started.html)
- [GitHub Actions PHP](https://github.com/shivammathur/setup-php)

---

**Mantendo testes = Mantendo qualidade!** 🚀
