# 🧪 Guia Completo de Testes - FiberEventLoop

## 📋 Sumário

- [Status dos Testes](#status-dos-testes)
- [Estrutura de Testes](#estrutura-de-testes)
- [Como Executar](#como-executar)
- [Cobertura de Funcionalidades](#cobertura-de-funcionalidades)
- [Testes Individuais](#testes-individuais)
- [Interpretando Resultados](#interpretando-resultados)

---

## ✅ Status dos Testes

### 🎯 Resultado Final

```
✅ 112 TESTES PASSANDO
📊 214 ASSERTIONS VERIFICADAS
⚠️  1 WARNING (esperado)
❌ 0 FALHAS
🕐 ~1.6 segundos
💾 ~14MB
```

---

## 🏗️ Estrutura de Testes

### Organização dos Arquivos

```
tests/
├── bootstrap.php                    # Autoloader e setup
├── FiberManagerTraitTest.php        # 16 testes
├── TimerManagerTraitTest.php        # 25 testes
├── StreamManagerTraitTest.php       # 22 testes
├── FiberEventLoopTest.php           # 36 testes
└── IntegrationTest.php              # 13 testes
                                      # ─────────
                                      # 112 total
```

---

## 🚀 Como Executar

### Opção 1: Usando Script Bash (Recomendado)

```bash
# Executar todos os testes
./run_tests.sh all

# Executar suite específica
./run_tests.sh fiber      # FiberManagerTrait
./run_tests.sh timer      # TimerManagerTrait
./run_tests.sh stream     # StreamManagerTrait
./run_tests.sh main       # FiberEventLoop
./run_tests.sh integration # Testes de integração

# Executar com cobertura
./run_tests.sh coverage

# Executar com verbose
./run_tests.sh verbose

# Ver ajuda
./run_tests.sh help
```

### Opção 2: Usando PHPUnit Diretamente

```bash
# Todos os testes
php vendor/bin/phpunit

# Com formato testdox (recomendado)
php vendor/bin/phpunit --testdox

# Suite específica
php vendor/bin/phpunit tests/FiberManagerTraitTest.php

# Com cobertura
php vendor/bin/phpunit --coverage-html=coverage/html

# Verbose
php vendor/bin/phpunit --verbose

# Parar no primeiro erro
php vendor/bin/phpunit --stop-on-failure

# Executar teste específico
php vendor/bin/phpunit --filter "testDeferExecutesCallback"
```

### Opção 3: Usando Composer

```bash
# Definido em composer.json
composer test                  # Executa phpunit
composer test-coverage         # Com cobertura HTML
composer test-verbose          # Com verbose
composer test-filter           # Com filtro
composer test-stop-on-failure  # Para no primeiro erro
```

---

## 📊 Cobertura de Funcionalidades

### FiberManagerTrait (16 testes)

| Método/Funcionalidade | Cobertura | Testes |
|----------------------|-----------|--------|
| `defer()` | ✅ Completa | 5+ |
| `generateId()` | ✅ Completa | 1+ |
| `cancel()` | ✅ Completa | 4+ |
| `next()` | ✅ Completa | 2+ |
| Tratamento de Exceções | ✅ Completa | 1+ |
| Múltiplas Operações | ✅ Completa | 2+ |

### TimerManagerTrait (25 testes)

| Método/Funcionalidade | Cobertura | Testes |
|----------------------|-----------|--------|
| `after()` | ✅ Completa | 5+ |
| `setInterval()` | ✅ Completa | 2+ |
| `repeat()` | ✅ Completa | 6+ |
| `sleep()` | ✅ Completa | 1+ |
| Cancelamento | ✅ Completa | 3+ |
| Precisão | ✅ Completa | 2+ |
| Decimais/Inteiros | ✅ Completa | 3+ |
| Exceções | ✅ Completa | 1+ |
| Edge Cases | ✅ Completa | 1+ |

### StreamManagerTrait (22 testes)

| Método/Funcionalidade | Cobertura | Testes |
|----------------------|-----------|--------|
| `listen()` | ✅ Completa | 5+ |
| `onReadable()` | ✅ Completa | 2+ |
| `onWritable()` | ✅ Completa | 3+ |
| `onReadFile()` | ✅ Completa | 6+ |
| Validação | ✅ Completa | 3+ |
| Cancelamento | ✅ Completa | 3+ |
| Configuração | ✅ Completa | 1+ |

### FiberEventLoop (36 testes)

| Método/Funcionalidade | Cobertura | Testes |
|----------------------|-----------|--------|
| `run()` | ✅ Completa | 3+ |
| `stop()` | ✅ Completa | 3+ |
| `getErrors()` | ✅ Completa | 3+ |
| `getMetrics()` | ✅ Completa | 8+ |
| `setOptimizationLevel()` | ✅ Completa | 7+ |
| Operações Combinadas | ✅ Completa | 3+ |
| Performance/Stress | ✅ Completa | 2+ |

### IntegrationTest (13 testes)

| Cenário | Cobertura | Testes |
|---------|-----------|--------|
| Múltiplas Operações | ✅ Completa | 1 |
| Servidor TCP | ✅ Completa | 1 |
| Leitura de Arquivo | ✅ Completa | 1 |
| Recuperação de Erros | ✅ Completa | 1 |
| Operações em Cascata | ✅ Completa | 1 |
| Métricas Realistas | ✅ Completa | 1 |
| Otimização | ✅ Completa | 1 |
| Timers Variados | ✅ Completa | 1 |
| Graceful Shutdown | ✅ Completa | 1 |
| Exceções | ✅ Completa | 1 |
| Heavy Load | ✅ Completa | 1 |
| Memória | ✅ Completa | 1 |
| Concorrência | ✅ Completa | 1 |

---

## 🔍 Testes Individuais

### Exemplo: Executar um teste específico

```bash
# Procurar nome do teste
php vendor/bin/phpunit tests/ --testdox | grep "Defer"

# Executar teste específico
php vendor/bin/phpunit --filter "testDeferExecutesCallback"
```

### Exemplos de Nomes de Testes

```
testDeferExecutesCallback
testAfterSchedulesCallbackAfterDelay
testListenRegistersServerSocket
testOnReadFileReadsFileContent
testLoopResilienceWithManyOperations
testCascadingOperations
```

---

## 📈 Interpretando Resultados

### Status dos Testes

- ✅ **VERDE** - Teste passou
- ❌ **VERMELHO** - Teste falhou
- ⚠️  **AMARELO** - Warning (não é falha)
- ⚡ **E** - Erro (exceção não esperada)

### Exemplo de Output

```
PHPUnit 11.5.45 by Sebastian Bergmann and contributors.

..................                                              20 / 20 (100%)

Time: 00:00.500, Memory: 8.00 MB

✔ Nome do Teste 1
✔ Nome do Teste 2
...

OK (20 tests, 40 assertions)
```

### Se um Teste Falhar

```bash
# Ver detalhes do erro
php vendor/bin/phpunit --verbose

# Ver stack trace completo
php vendor/bin/phpunit --testdox --verbose

# Parar no primeiro erro
php vendor/bin/phpunit --stop-on-failure
```

---

## 🎯 Cobertura de Código

### Gerar Relatório HTML

```bash
php vendor/bin/phpunit --coverage-html=coverage/html
```

Resultado: `coverage/html/index.html`

### Gerar Relatório de Texto

```bash
php vendor/bin/phpunit --coverage-text
```

### Gerar Relatório Clover XML

```bash
php vendor/bin/phpunit --coverage-clover=coverage/clover.xml
```

---

## 🧪 Princípios dos Testes

### Isolamento
Cada teste cria sua própria instância de `FiberEventLoop`, garantindo que não haja interferência entre testes.

### Abrangência
Os testes cobrem:
- Happy path (caminho feliz)
- Edge cases (casos extremos)
- Error handling (tratamento de erros)
- Performance (cenários de carga)
- Integration (integração entre componentes)

### Clareza
Cada teste tem:
- Nome descritivo
- Documentação clara
- Assertions óbvias
- Setup e teardown apropriados

---

## 🔧 Troubleshooting

### "Class not found"
```bash
# Executar em um terminal dentro do diretório do projeto
cd /home/omgaalfa/php-projetos/applications/FiberEventLoop
php vendor/bin/phpunit
```

### "No tests found"
```bash
# Verificar se os testes existem
ls tests/*.php

# Verificar se phpunit.xml está correto
cat phpunit.xml | head -10
```

### "Permission denied" no run_tests.sh
```bash
chmod +x run_tests.sh
```

### Testes lentos
```bash
# Mostrar testes mais lentos
php vendor/bin/phpunit --verbose --testdox | grep -i "time"
```

---

## 📚 Recursos Adicionais

- [PHPUnit Documentation](https://phpunit.de/)
- [TESTING.md](../TESTING.md) - Guia de testes do projeto
- [TESTS_REPORT.md](../TESTS_REPORT.md) - Relatório de testes
- [README.md](../README.md) - Documentação do projeto

---

## ✨ Dicas Úteis

### Rodar testes enquanto desenvolve

```bash
# Terminal 1: Fazer mudanças
vim src/FiberEventLoop.php

# Terminal 2: Rodar testes continuamente
watch -n 2 "php vendor/bin/phpunit --stop-on-failure"
```

### Debugar um teste específico

```bash
# Adicionar var_dump/echo no teste
// ...código...
var_dump($result);
// ...código...

# Rodar com verbose
php vendor/bin/phpunit --filter "testName" --verbose
```

### Verificar quais métodos estão sendo testados

```bash
grep -r "public function test" tests/ | wc -l
# Resultado: 112
```

---

## 📌 Checklist antes de commitar

- [ ] Todos os testes passando: `./run_tests.sh all`
- [ ] Cobertura adequada: `./run_tests.sh coverage`
- [ ] Sem warnings não esperados
- [ ] Código segue padrões do projeto
- [ ] Documentação de testes atualizada

---

**Última atualização**: 18 de Dezembro de 2025  
**Versão**: 1.0  
**Status**: ✅ Produção
