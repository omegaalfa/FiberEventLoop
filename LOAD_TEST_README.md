# 🚀 Synthetic Load Testing - FiberEventLoop

## Overview

Three powerful CLI scripts for synthetic webhook load testing with **FiberEventLoop**:

### Scripts Disponíveis

| Script | Purpose | Features |
|--------|---------|----------|
| **load_test.php** | Basic load test | Simple, straightforward throughput testing |
| **load_test_optimized.php** | Production-ready | Fast, efficient, accurate metrics |
| **load_test_advanced.php** | Advanced scenarios | Failure simulation, retry logic, detailed stats |

## ⚡ Quick Start

### Comando Básico

```bash
# Test padrão: 10,000 webhooks
php load_test_optimized.php

# Test rápido: 1,000 webhooks
php load_test_optimized.php --webhooks=1000

# Test grande: 100,000 webhooks
php load_test_optimized.php --webhooks=100000 --workers=50
```

### Opções Disponíveis

```
--webhooks=N    Total number of webhooks (default: 10000)
--latency=N     Simulated latency in milliseconds (default: 10)
--workers=N     Concurrent workers (default: 10)
--verbose       Show detailed webhook processing output
--help          Show help message
```

## 📊 Example Results

### Test: 10,000 webhooks, 10 workers, 10ms latency

```
══════════════════════════════════════════════════════════════════════
                             Final Report
══════════════════════════════════════════════════════════════════════

Test Duration: 12.64s
Total Processed: 10,000
  ✓ Successful: 10,000 (100.00%)
  ✗ Failed: 0 (0.00%)

Performance:
  Throughput: 790 webhooks/sec
  Avg Latency: 10ms
  Total Time: 12.64s

Event Loop:
  Iterations: 10,250
  Work Cycles: 10,250
  Empty Iterations: 0

Status: ✅ SUCCESS
```

## 🎯 Common Scenarios

### Scenario 1: High Throughput Test

```bash
php load_test_optimized.php \
  --webhooks=100000 \
  --workers=50 \
  --latency=5
```

**Expected:**
- Throughput: 2,000+ webhooks/sec
- Duration: ~50 seconds
- Success Rate: 100%

### Scenario 2: Stress Test

```bash
php load_test_optimized.php \
  --webhooks=500000 \
  --workers=100 \
  --latency=2
```

**Expected:**
- Throughput: 5,000+ webhooks/sec
- Duration: ~100 seconds
- Memory: 100-200MB

### Scenario 3: Realistic Webhook Processing

```bash
php load_test_advanced.php \
  --webhooks=50000 \
  --workers=20 \
  --latency=10 \
  --failure-rate=0.05
```

**Expected:**
- Success Rate: 95%
- Automatic retries for failures
- Detailed latency statistics

### Scenario 4: Quick Validation

```bash
php load_test_optimized.php --webhooks=1000
```

**Expected:**
- Duration: ~2 seconds
- Throughput: 500+ webhooks/sec
- No failures

### Scenario 5: Low Latency High Performance

```bash
php load_test_optimized.php \
  --webhooks=50000 \
  --workers=100 \
  --latency=1
```

**Expected:**
- Throughput: 3,000+ webhooks/sec
- Latency: ~1ms
- High CPU usage

## 📈 Performance Metrics

### What Each Metric Means

| Metric | Meaning | Good Value |
|--------|---------|-----------|
| **Throughput** | Webhooks processed per second | > 1,000 /sec |
| **Latency** | Processing time per webhook | < 20ms |
| **Success Rate** | % of successful processing | 100% or > 99% |
| **Iterations** | Event loop iterations | Lower is better (more efficient) |
| **Work Cycles** | Number of actual work performed | Should match webhooks processed |

## 🔍 Interpreting Results

### Good Performance Indicators
✅ Success Rate: 100%
✅ Throughput: > 1,000 webhooks/sec
✅ Latency: < 15ms
✅ Memory stable

### Warning Signs
⚠️ Success Rate: < 95%
⚠️ Throughput: < 500 webhooks/sec  
⚠️ Latency: > 50ms
⚠️ Memory growing unbounded

## 📋 Using load_test_advanced.php

The advanced version includes realistic failure scenarios:

```bash
# Test with 10% failure rate
php load_test_advanced.php --webhooks=50000 --failure-rate=0.1

# Test with verbose output
php load_test_advanced.php --webhooks=5000 --verbose

# Failure rate between 0.0 and 1.0
php load_test_advanced.php --webhooks=25000 --failure-rate=0.05
```

### Advanced Features
- **Failure Simulation**: Randomly fail webhooks to test resilience
- **Automatic Retry**: Failed webhooks are retried up to 3 times
- **Detailed Latency**: Min/Avg/Max latency statistics
- **Webhook Types**: Different event types (order, payment, etc.)
- **Total Time Tracking**: Includes queue + processing time

## 🛠️ Using run_load_test.sh

Bash script with preset configurations:

```bash
./run_load_test.sh quick      # 1,000 webhooks
./run_load_test.sh standard   # 10,000 webhooks
./run_load_test.sh stress     # 100,000 webhooks
./run_load_test.sh demo       # 5,000 webhooks + 20 workers
./run_load_test.sh custom --webhooks=50000 --workers=30
```

## 💾 System Requirements

- PHP 8.1+ (8.4 recommended)
- Composer autoloader
- FiberEventLoop installed
- 512MB+ RAM (depending on load)
- Linux/macOS or WSL on Windows

## 🚨 Troubleshooting

### Low Throughput

```bash
# Increase workers
php load_test_optimized.php --webhooks=50000 --workers=50

# Reduce latency (if testing allows)
php load_test_optimized.php --webhooks=50000 --latency=5

# Check system resources
free -h
ps aux | grep php
```

### High Memory Usage

```bash
# Reduce webhook count
php load_test_optimized.php --webhooks=10000 --workers=5

# Monitor memory during test
watch -n 1 'ps aux | grep php'
```

### Class Not Found Error

```bash
# Ensure Composer is installed
composer install

# Run from project root
cd /path/to/FiberEventLoop
php load_test_optimized.php
```

## 📚 Advanced Usage

### Running Multiple Tests in Sequence

```bash
#!/bin/bash
for size in 5000 10000 50000; do
  echo "Testing with $size webhooks..."
  php load_test_optimized.php --webhooks=$size --workers=10
  sleep 2
done
```

### Monitoring During Test

```bash
# In terminal 1
php load_test_optimized.php --webhooks=100000

# In terminal 2 (live monitoring)
watch -n 1 'ps aux | grep php'
```

### Comparing Different Configurations

```bash
# Test 1: Standard
php load_test_optimized.php --webhooks=50000 --workers=10

# Test 2: More workers
php load_test_optimized.php --webhooks=50000 --workers=50

# Test 3: Lower latency
php load_test_optimized.php --webhooks=50000 --latency=5
```

## 🔗 Integration with CI/CD

### GitHub Actions Example

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
      - run: php load_test_optimized.php --webhooks=10000
```

### GitLab CI Example

```yaml
load_test:
  stage: test
  script:
    - composer install
    - php load_test_optimized.php --webhooks=50000 --workers=20
  artifacts:
    reports:
      performance: load_test_results.json
```

## 📞 Support

For issues or questions:
1. Check [LOAD_TESTING.md](./LOAD_TESTING.md) for detailed guide
2. Review [README.md](./README.md) for FiberEventLoop documentation
3. Run with `--verbose` flag for detailed output
4. Check test files in `tests/` directory

## 📝 Related Documentation

- [LOAD_TESTING.md](./LOAD_TESTING.md) - Comprehensive testing guide
- [TESTING.md](./TESTING.md) - Unit test documentation
- [README.md](./README.md) - FiberEventLoop overview
- [TESTING_GUIDE.md](./TESTING_GUIDE.md) - Testing best practices
