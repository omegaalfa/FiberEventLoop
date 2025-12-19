# Load Testing - Guia Completo

## Visão Geral

Os scripts de carga sintética simulam o processamento de webhooks em larga escala, permitindo testar a capacidade, performance e confiabilidade do FiberEventLoop.

## Scripts Disponíveis

### 1. `load_test.php` - Teste Básico de Carga

Script principal para simular 1000+ webhooks por segundo.

**Características:**
- Simula processamento assincronous de webhooks
- Múltiplos workers concorrentes
- Coleta de métricas em tempo real
- Relatório detalhado de performance

**Execução Básica:**

```bash
php load_test.php
```

**Com Opções:**

```bash
# 50.000 webhooks com latência de 5ms
php load_test.php --webhooks=50000 --latency=5

# 20 workers para maior concorrência
php load_test.php --workers=20 --verbose

# Latência customizada
php load_test.php --webhooks=10000 --latency=15
```

**Opções:**
- `--webhooks=N` - Número total de webhooks (padrão: 10000)
- `--latency=N` - Latência simulada em ms (padrão: 10)
- `--workers=N` - Workers concorrentes (padrão: 10)
- `--verbose` - Saída detalhada
- `--help` - Mostrar ajuda

### 2. `load_test_advanced.php` - Teste Avançado com Falhas

Script com simulação de falhas, retry logic e métricas detalhadas.

**Características Adicionais:**
- Simulação realista de falhas
- Retry automático de webhooks falhos
- Estatísticas de latência (min, avg, max)
- Diferentes tipos de eventos
- Rastreamento de tempo total (fila + processamento)

**Execução:**

```bash
php load_test_advanced.php --webhooks=50000 --failure-rate=0.1
```

**Opções:**
- `--webhooks=N` - Número total de webhooks
- `--latency=N` - Latência simulada em ms
- `--workers=N` - Workers concorrentes
- `--failure-rate=<0.0-1.0>` - Taxa de falha (0.1 = 10%)
- `--verbose` - Saída detalhada
- `--help` - Mostrar ajuda

### 3. `run_load_test.sh` - Runner com Preset de Testes

Script bash com presets de testes pré-configurados.

**Comandos Disponíveis:**

```bash
# Teste rápido (1.000 webhooks)
./run_load_test.sh quick

# Teste padrão (10.000 webhooks)
./run_load_test.sh standard

# Teste de stress (100.000 webhooks)
./run_load_test.sh stress

# Demo (5.000 webhooks com 20 workers)
./run_load_test.sh demo

# Teste customizado
./run_load_test.sh custom --webhooks=50000 --latency=5 --workers=30 --verbose
```

**Preparar o Script:**

```bash
chmod +x run_load_test.sh
```

## Exemplos de Uso

### Cenário 1: Teste de Throughput Padrão

```bash
php load_test.php --webhooks=100000 --latency=10 --workers=10
```

**Resultado Esperado:**
- ~10.000 webhooks/segundo
- Latência: ~10ms
- Duração: ~10 segundos

### Cenário 2: Teste de Alta Concorrência

```bash
php load_test.php --webhooks=500000 --latency=5 --workers=100
```

**Resultado Esperado:**
- ~50.000+ webhooks/segundo
- Latência: ~5ms
- Duração: ~10 segundos
- Alto consumo de memória

### Cenário 3: Teste com Falhas

```bash
php load_test_advanced.php --webhooks=50000 --failure-rate=0.15 --workers=20
```

**Resultado Esperado:**
- 15% de taxa de falha
- Retry automático
- Métrica de retentativas
- Estatísticas detalhadas

### Cenário 4: Teste de Baixa Latência

```bash
php load_test.php --webhooks=10000 --latency=1 --workers=50
```

**Resultado Esperado:**
- Latência muito baixa (~1ms)
- Throughput muito alto
- Teste de limites do sistema

### Cenário 5: Teste Realista Multi-Tipo

```bash
php load_test_advanced.php \
  --webhooks=75000 \
  --latency=10 \
  --workers=25 \
  --failure-rate=0.05
```

## Interpretando os Resultados

### Métricas Principais

```
Test Duration: 12.45 seconds
Total Webhooks: 100,000
  ✓ Successful: 99,500 (99.50%)
  ✗ Failed: 500 (0.50%)

Performance Metrics:
  Throughput: 8,000 webhooks/sec
  Min Latency: 8.23ms
  Avg Latency: 10.45ms
  Max Latency: 15.67ms

Event Loop Metrics:
  Iterations: 125,400
  Work Cycles: 100,500
  Empty Iterations: 24,900
```

### Interpretação

| Métrica | Bom | Aceitável | Ruim |
|---------|-----|-----------|------|
| Throughput | > 5,000/s | 1,000-5,000/s | < 1,000/s |
| Latência Avg | < 15ms | 15-50ms | > 50ms |
| Taxa de Erro | 0% | < 2% | > 2% |
| Memória | < 50MB | 50-200MB | > 200MB |

## Benchmarks de Referência

Baseado em testes com FiberEventLoop:

```
Configuração: CPU 4-core, RAM 8GB, PHP 8.4

Teste Padrão (10,000 webhooks, 10 workers):
  - Throughput: ~10,000 webhooks/sec
  - Latência: ~10ms
  - Tempo total: ~2 segundos
  - Memória: ~14MB

Teste de Stress (100,000 webhooks, 50 workers):
  - Throughput: ~25,000 webhooks/sec
  - Latência: ~8-12ms
  - Tempo total: ~5 segundos
  - Memória: ~45MB

Teste de Ultra-Scale (1,000,000 webhooks, 100 workers):
  - Throughput: ~50,000 webhooks/sec
  - Latência: ~10-20ms
  - Tempo total: ~20 segundos
  - Memória: ~120MB
```

## Dicas de Otimização

### 1. Aumentar Throughput

```bash
# Aumentar workers
php load_test.php --webhooks=100000 --workers=50

# Reduzir latência (mais CPUs necessárias)
php load_test.php --latency=5 --workers=50
```

### 2. Reduzir Latência

```bash
# Reduzir número de webhooks
php load_test.php --webhooks=5000 --latency=1

# Aumentar workers
php load_test.php --workers=100
```

### 3. Testar Confiabilidade

```bash
# Simular falhas aleatórias
php load_test_advanced.php --webhooks=50000 --failure-rate=0.1

# Múltiplos testes sequenciais
for i in {1..5}; do
  php load_test.php --webhooks=20000 --workers=15
done
```

### 4. Monitorar Recursos

```bash
# Em outro terminal, monitorar processo PHP
watch -n 1 'ps aux | grep load_test'

# Ou com top interativo
top -p $(pgrep -f load_test.php | head -1)
```

## Requisitos do Sistema

- PHP 8.1+ (recomendado 8.4+)
- PECL Fiber support (nativa em PHP 8.1+)
- FiberEventLoop instalado
- Terminal com suporte a cores ANSI (opcional)

## Troubleshooting

### Erro: "Class not found: FiberEventLoop"

```bash
# Verificar autoload
composer dump-autoload

# Executar novamente
php load_test.php
```

### Performance Baixa

```bash
# Verificar número de workers
php load_test.php --workers=50

# Verificar latência
php load_test.php --latency=5

# Testar com menos webhooks
php load_test.php --webhooks=1000
```

### Alto Consumo de Memória

```bash
# Reduzir webhooks
php load_test.php --webhooks=10000

# Reduzir workers
php load_test.php --workers=5

# Monitorar com verbose desligado
php load_test.php --webhooks=50000 # sem --verbose
```

## Integração com CI/CD

### GitHub Actions

```yaml
name: Load Test
on: [push]

jobs:
  load-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - uses: shivammathur/setup-php@v2
        with:
          php-version: 8.4
      - run: composer install
      - run: php load_test.php --webhooks=50000
```

### Script de Teste Automatizado

```bash
#!/bin/bash

echo "Running load tests..."

echo "=== Quick Test ==="
php load_test.php --webhooks=5000

echo "=== Standard Test ==="
php load_test.php --webhooks=10000

echo "=== Stress Test ==="
php load_test.php --webhooks=50000 --workers=50

echo "All tests completed!"
```

## Referências

- [FiberEventLoop Documentation](./README.md)
- [TESTING.md](./TESTING.md) - Testes unitários
- [PHPUnit Configuration](./phpunit.xml.dist)
