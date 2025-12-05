<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';


use Omegaalfa\FiberEventLoop\FiberEventLoop;
use Revolt\EventLoop;

/**
 * Benchmark Suite: FiberLoop vs Revolt EventLoop
 *
 * Testa performance em cenários reais de uso
 */
class EventLoopBenchmark
{
    private const ITERATIONS = 1000;
    private const FILE_SIZE = 1024 * 100; // 100KB
    private const WARMUP_RUNS = 3;

    private array $results = [];
    private string $testFile;

    public function __construct()
    {
        $this->testFile = __DIR__ . '/data.txt';
        $this->createTestFile();
    }

    public function __destruct()
    {
        if (file_exists($this->testFile)) {
            @unlink($this->testFile);
        }
    }

    /**
     * Cria arquivo de teste
     */
    private function createTestFile(): void
    {
        $data = str_repeat('A', self::FILE_SIZE);
        file_put_contents($this->testFile, $data);
    }

    /**
     * Executa benchmark completo
     */
    public function run(): void
    {
        echo "╔══════════════════════════════════════════════════════════════╗\n";
        echo "║          BENCHMARK: FiberLoop vs Revolt EventLoop           ║\n";
        echo "╚══════════════════════════════════════════════════════════════╝\n\n";

        echo "Configuração:\n";
        echo "  - Iterações: " . number_format(self::ITERATIONS) . "\n";
        echo "  - Tamanho do arquivo: " . number_format(self::FILE_SIZE / 1024, 2) . " KB\n";
        echo "  - Warmup runs: " . self::WARMUP_RUNS . "\n\n";

        // Warmup
        echo "Aquecendo...\n";
        for ($i = 0; $i < self::WARMUP_RUNS; $i++) {
            $this->benchmarkFiberLoopTimers(100);
            $this->benchmarkRevoltTimers(100);
        }
        echo "Aquecimento concluído!\n\n";

        // Testes
        $this->benchmarkTimers();
        $this->benchmarkDeferred();
        $this->benchmarkFileRead();
        $this->benchmarkRepeated();
        $this->benchmarkMixed();
        $this->benchmarkMemoryUsage();

        $this->printResults();
        $this->printSummary();
    }

    /**
     * Benchmark 1: Timers simples
     */
    private function benchmarkTimers(): void
    {
        echo "[1/6] Testando Timers (setTimeout)... ";

        $fiberTime = $this->benchmarkFiberLoopTimers(self::ITERATIONS);
        $revoltTime = $this->benchmarkRevoltTimers(self::ITERATIONS);

        $this->results['timers'] = [
            'fiber' => $fiberTime,
            'revolt' => $revoltTime,
            'winner' => $fiberTime < $revoltTime ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberTime - $revoltTime),
            'faster' => $fiberTime < $revoltTime
                ? ($revoltTime / $fiberTime)
                : ($fiberTime / $revoltTime)
        ];

        echo "✓\n";
    }

    private function benchmarkFiberLoopTimers(int $iterations): float
    {
        $loop = new FiberEventLoop();
        $count = 0;

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $loop->after(function() use (&$count) {
                $count++;
            }, 0.001);
        }

        $loop->run();

        return microtime(true) - $start;
    }

    private function benchmarkRevoltTimers(int $iterations): float
    {
        $count = 0;

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            EventLoop::delay(0.001, function() use (&$count) {
                $count++;
            });
        }

        EventLoop::run();

        return microtime(true) - $start;
    }

    /**
     * Benchmark 2: Deferred callbacks
     */
    private function benchmarkDeferred(): void
    {
        echo "[2/6] Testando Deferred Callbacks... ";

        $fiberTime = $this->benchmarkFiberLoopDeferred(self::ITERATIONS);
        $revoltTime = $this->benchmarkRevoltDeferred(self::ITERATIONS);

        $this->results['deferred'] = [
            'fiber' => $fiberTime,
            'revolt' => $revoltTime,
            'winner' => $fiberTime < $revoltTime ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberTime - $revoltTime),
            'faster' => $fiberTime < $revoltTime
                ? ($revoltTime / $fiberTime)
                : ($fiberTime / $revoltTime)
        ];

        echo "✓\n";
    }

    private function benchmarkFiberLoopDeferred(int $iterations): float
    {
        $loop = new FiberEventLoop();
        $count = 0;

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $loop->defer(function() use (&$count) {
                $count++;
            });
        }

        $loop->run();

        return microtime(true) - $start;
    }

    private function benchmarkRevoltDeferred(int $iterations): float
    {
        $count = 0;

        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            EventLoop::defer(function() use (&$count) {
                $count++;
            });
        }

        EventLoop::run();

        return microtime(true) - $start;
    }

    /**
     * Benchmark 3: Leitura de arquivo
     */
    private function benchmarkFileRead(): void
    {
        echo "[3/6] Testando Leitura de Arquivo... ";

        $fiberTime = $this->benchmarkFiberLoopFileRead();
        $revoltTime = $this->benchmarkRevoltFileRead();

        $this->results['file_read'] = [
            'fiber' => $fiberTime,
            'revolt' => $revoltTime,
            'winner' => $fiberTime < $revoltTime ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberTime - $revoltTime),
            'faster' => $fiberTime < $revoltTime
                ? ($revoltTime / $fiberTime)
                : ($fiberTime / $revoltTime)
        ];

        echo "✓\n";
    }

    private function benchmarkFiberLoopFileRead(): float
    {
        $loop = new FiberEventLoop();
        $data = '';

        $start = microtime(true);

        $loop->onReadFile($this->testFile, function($chunk) use (&$data) {
            $data .= $chunk;
        });

        $loop->run();

        return microtime(true) - $start;
    }

    private function benchmarkRevoltFileRead(): float
    {
        $data = '';

        $start = microtime(true);

        $stream = fopen($this->testFile, 'rb');
        EventLoop::onReadable($stream, function($id, $stream) use (&$data) {
            $chunk = fread($stream, 8192);
            if ($chunk === false || $chunk === '') {
                EventLoop::cancel($id);
                fclose($stream);
                return;
            }
            $data .= $chunk;
        });

        EventLoop::run();

        return microtime(true) - $start;
    }

    /**
     * Benchmark 4: Operações repetidas
     */
    private function benchmarkRepeated(): void
    {
        echo "[4/6] Testando Operações Repetidas... ";

        $iterations = 100;

        $fiberTime = $this->benchmarkFiberLoopRepeated($iterations);
        $revoltTime = $this->benchmarkRevoltRepeated($iterations);

        $this->results['repeated'] = [
            'fiber' => $fiberTime,
            'revolt' => $revoltTime,
            'winner' => $fiberTime < $revoltTime ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberTime - $revoltTime),
            'faster' => $fiberTime < $revoltTime
                ? ($revoltTime / $fiberTime)
                : ($fiberTime / $revoltTime)
        ];

        echo "✓\n";
    }

    private function benchmarkFiberLoopRepeated(int $times): float
    {
        $loop = new FiberEventLoop();
        $count = 0;

        $start = microtime(true);

        $loop->repeat(0.001, function() use (&$count) {
            $count++;
        }, $times);

        $loop->run();

        return microtime(true) - $start;
    }

    private function benchmarkRevoltRepeated(int $times): float
    {
        $count = 0;

        $start = microtime(true);

        $id = EventLoop::repeat(0.001, function($id) use (&$count, $times) {
            $count++;
            if ($count >= $times) {
                EventLoop::cancel($id);
            }
        });

        EventLoop::run();

        return microtime(true) - $start;
    }

    /**
     * Benchmark 5: Cenário misto
     */
    private function benchmarkMixed(): void
    {
        echo "[5/6] Testando Cenário Misto... ";

        $fiberTime = $this->benchmarkFiberLoopMixed();
        $revoltTime = $this->benchmarkRevoltMixed();

        $this->results['mixed'] = [
            'fiber' => $fiberTime,
            'revolt' => $revoltTime,
            'winner' => $fiberTime < $revoltTime ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberTime - $revoltTime),
            'faster' => $fiberTime < $revoltTime
                ? ($revoltTime / $fiberTime)
                : ($fiberTime / $revoltTime)
        ];

        echo "✓\n";
    }

    private function benchmarkFiberLoopMixed(): float
    {
        $loop = new FiberEventLoop();
        $count = 0;

        $start = microtime(true);

        // Mix de operações
        for ($i = 0; $i < 100; $i++) {
            $loop->defer(function() use (&$count) {
                $count++;
            });
        }

        for ($i = 0; $i < 50; $i++) {
            $loop->after(function() use (&$count) {
                $count++;
            }, 0.001);
        }

        $loop->repeat(0.001, function() use (&$count) {
            $count++;
        }, 10);

        $loop->run();

        return microtime(true) - $start;
    }

    private function benchmarkRevoltMixed(): float
    {
        $count = 0;

        $start = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            EventLoop::defer(function() use (&$count) {
                $count++;
            });
        }

        for ($i = 0; $i < 50; $i++) {
            EventLoop::delay(0.001, function() use (&$count) {
                $count++;
            });
        }

        $repeats = 0;
        $id = EventLoop::repeat(0.001, function($id) use (&$count, &$repeats) {
            $count++;
            if (++$repeats >= 10) {
                EventLoop::cancel($id);
            }
        });

        EventLoop::run();

        return microtime(true) - $start;
    }

    /**
     * Benchmark 6: Uso de memória
     */
    private function benchmarkMemoryUsage(): void
    {
        echo "[6/6] Testando Uso de Memória... ";

        $fiberMemory = $this->measureFiberLoopMemory();
        $revoltMemory = $this->measureRevoltMemory();

        $this->results['memory'] = [
            'fiber' => $fiberMemory,
            'revolt' => $revoltMemory,
            'winner' => $fiberMemory < $revoltMemory ? 'FiberLoop' : 'Revolt',
            'diff' => abs($fiberMemory - $revoltMemory)
        ];

        echo "✓\n\n";
    }

    private function measureFiberLoopMemory(): int
    {
        gc_collect_cycles();
        $before = memory_get_usage(true);

        $loop = new FiberEventLoop();
        for ($i = 0; $i < 1000; $i++) {
            $loop->defer(function() {});
        }
        $loop->run();

        $after = memory_get_usage(true);

        return $after - $before;
    }

    private function measureRevoltMemory(): int
    {
        gc_collect_cycles();
        $before = memory_get_usage(true);

        for ($i = 0; $i < 1000; $i++) {
            EventLoop::defer(function() {});
        }
        EventLoop::run();

        $after = memory_get_usage(true);

        return $after - $before;
    }

    /**
     * Imprime resultados detalhados
     */
    private function printResults(): void
    {
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "                      RESULTADOS DETALHADOS                     \n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        foreach ($this->results as $test => $data) {
            $testName = ucwords(str_replace('_', ' ', $test));
            echo "┌─ {$testName} " . str_repeat('─', 60 - strlen($testName)) . "\n";

            if ($test === 'memory') {
                echo "│ FiberLoop:  " . $this->formatBytes($data['fiber']) . "\n";
                echo "│ Revolt:     " . $this->formatBytes($data['revolt']) . "\n";
                echo "│ Diferença:  " . $this->formatBytes($data['diff']) . "\n";
                echo "│ Vencedor:   {$data['winner']}\n";
            } else {
                echo "│ FiberLoop:  " . number_format($data['fiber'], 6) . "s\n";
                echo "│ Revolt:     " . number_format($data['revolt'], 6) . "s\n";
                echo "│ Diferença:  " . number_format($data['diff'], 6) . "s\n";
                echo "│ {$data['winner']} é " . number_format($data['faster'], 2) . "x mais rápido\n";
            }
            echo "└" . str_repeat('─', 63) . "\n\n";
        }
    }

    /**
     * Imprime resumo final
     */
    private function printSummary(): void
    {
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "                           RESUMO FINAL                         \n";
        echo "═══════════════════════════════════════════════════════════════\n\n";

        $fiberWins = 0;
        $revoltWins = 0;

        foreach ($this->results as $test => $data) {
            if ($data['winner'] === 'FiberLoop') {
                $fiberWins++;
            } else {
                $revoltWins++;
            }
        }

        $totalTests = count($this->results);

        echo "Total de testes: {$totalTests}\n";
        echo "Vitórias FiberLoop: {$fiberWins} (" . number_format(($fiberWins / $totalTests) * 100, 1) . "%)\n";
        echo "Vitórias Revolt: {$revoltWins} (" . number_format(($revoltWins / $totalTests) * 100, 1) . "%)\n\n";

        if ($fiberWins > $revoltWins) {
            echo "🏆 VENCEDOR GERAL: FiberLoop\n";
        } elseif ($revoltWins > $fiberWins) {
            echo "🏆 VENCEDOR GERAL: Revolt EventLoop\n";
        } else {
            echo "🤝 EMPATE TÉCNICO\n";
        }

        echo "\n═══════════════════════════════════════════════════════════════\n";
    }

    /**
     * Formata bytes para leitura humana
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Executa o benchmark
try {
    $benchmark = new EventLoopBenchmark();
    $benchmark->run();
} catch (Throwable $e) {
    echo "ERRO: {$e->getMessage()}\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}