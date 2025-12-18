<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

/**
 * Testes de Performance e Stress do FiberEventLoop
 * 
 * Valida comportamento sob carga alta e mede performance
 */
class PerformanceTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Testa latência de defers
     */
    public function testDeferLatency(): void
    {
        $latencies = [];
        $start = microtime(true);

        $this->loop->defer(function() use (&$latencies, $start) {
            $latencies[] = (microtime(true) - $start) * 1000; // em ms
        });

        $this->loop->run();

        $avgLatency = array_sum($latencies) / count($latencies);

        echo "\nDefer Latency: " . round($avgLatency, 3) . " ms\n";

        $this->assertLessThan(10, $avgLatency, 'Latência deveria ser < 10ms');
    }

    /**
     * Testa latência de timers
     */
    public function testTimerLatency(): void
    {
        $latencies = [];
        $targetDelay = 0.01; // 10ms

        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            
            $this->loop->after(function() use (&$latencies, $start, $targetDelay) {
                $actualDelay = (microtime(true) - $start) * 1000; // em ms
                $targetMs = $targetDelay * 1000;
                $latencies[] = abs($actualDelay - $targetMs);
            }, $targetDelay);
        }

        $this->loop->run();

        $avgLatency = array_sum($latencies) / count($latencies);

        echo "\nTimer Latency (10ms target): " . round($avgLatency, 2) . " ms error\n";

        // Permite até 50% de erro
        $this->assertLessThan(5, $avgLatency, 'Erro médio deveria ser < 5ms');
    }

    /**
     * Testa CPU usage em idle
     */
    public function testIdleCpuUsage(): void
    {
        $this->loop->setOptimizationLevel('efficient');

        $start = microtime(true);
        
        // Uma operação que termina rápido
        $this->loop->after(fn() => null, 0.01);
        $this->loop->run();

        $elapsed = microtime(true) - $start;

        echo "\nIdle test completed in: " . round($elapsed, 3) . "s\n";

        // Deveria terminar rápido (idle adaptativo funciona)
        $this->assertLessThan(0.5, $elapsed);
    }

    /**
     * Testa escalabilidade de timers (reduzido)
     */
    public function testTimerScalability(): void
    {
        $start = microtime(true);

        // Agenda 200 timers (reduzido de 5000)
        for ($i = 0; $i < 200; $i++) {
            $this->loop->after(fn() => null, 0.001 + (mt_rand(0, 100) * 0.0001));
        }

        $this->loop->run();

        $elapsed = microtime(true) - $start;

        echo "\n200 Timers scheduled and executed in: " . round($elapsed, 2) . "s\n";

        // Teste passa se não travou
        $this->assertLessThan(10, $elapsed);
    }

    /**
     * Testa taxa de iterações
     */
    public function testIterationRate(): void
    {
        $start = microtime(true);
        $target = 0.1; // 100ms

        $this->loop->after(fn() => $this->loop->stop(), $target);
        $this->loop->run();

        $elapsed = microtime(true) - $start;
        $metrics = $this->loop->getMetrics();
        $iterationRate = $metrics['iterations'] / $elapsed;

        echo "\nIteration Rate: " . number_format($iterationRate, 0) . " iterations/sec\n";
        echo "Total iterations: " . $metrics['iterations'] . "\n";
        echo "Empty iterations: " . $metrics['empty_iterations'] . "\n";
        echo "Work cycles: " . $metrics['work_cycles'] . "\n";

        // Taxa de iterações deveria ser alta
        $this->assertGreaterThan(1000, $iterationRate);
    }

    /**
     * Testa repetições de alto volume (reduzido)
     */
    public function testHighVolumeRepeats(): void
    {
        $start = microtime(true);

        // Repeat 100 vezes
        $this->loop->repeat(0.001, fn() => null, times: 100);

        $this->loop->run();

        $elapsed = microtime(true) - $start;

        echo "\nHigh Volume Repeat (100x) completed in: " . round($elapsed, 3) . "s\n";

        // Teste passa se não travou
        $this->assertLessThan(1, $elapsed);
    }

    /**
     * Testa memória sob carga (reduzido)
     */
    public function testMemoryUnderLoad(): void
    {
        $memBefore = memory_get_usage();

        // Cria 1000 operações (reduzido de 10k)
        for ($i = 0; $i < 1000; $i++) {
            $this->loop->defer(fn() => null);
        }

        $memAfter = memory_get_usage();
        $memUsed = ($memAfter - $memBefore) / 1024 / 1024; // em MB

        echo "\nMemory usage for 1000 defers: " . round($memUsed, 2) . " MB\n";

        $this->loop->run();
        
        // Validação básica
        $this->assertLessThan(50, $memUsed, 'Não deveria usar mais de 50MB');
    }

    /**
     * Testa distribuição de carga (reduzido)
     */
    public function testLoadDistribution(): void
    {
        $distribution = [];
        $iterations = 100;  // reduzido de 1000

        for ($i = 0; $i < $iterations; $i++) {
            $this->loop->defer(function() use (&$distribution) {
                $distribution[] = 1;
            });
        }

        $start = microtime(true);
        $this->loop->run();
        $elapsed = microtime(true) - $start;

        $throughput = $iterations / $elapsed;

        echo "\nLoad Distribution: " . number_format($throughput, 0) . " ops/sec\n";
        echo "Processed: " . count($distribution) . " items\n";

        $this->assertCount($iterations, $distribution);
    }

    /**
     * Testa precisão de timing com carga (reduzida)
     */
    public function testTimingAccuracyUnderLoad(): void
    {
        $deviations = [];
        $targetMs = 50;
        $targetSec = $targetMs / 1000;

        // Executa 10 timers (reduzido de 100)
        for ($i = 0; $i < 10; $i++) {
            $start = microtime(true);
            
            $this->loop->after(function() use (&$deviations, $start, $targetMs) {
                $actual = (microtime(true) - $start) * 1000;
                $deviations[] = abs($actual - $targetMs);
            }, $targetSec);
        }

        // Carga reduzida (100 defers)
        for ($i = 0; $i < 100; $i++) {
            $this->loop->defer(fn() => null);
        }

        $this->loop->run();

        $avgDeviation = array_sum($deviations) / count($deviations);
        $maxDeviation = max($deviations);

        echo "\nTiming under load (50ms target):\n";
        echo "  Avg deviation: " . round($avgDeviation, 2) . " ms\n";
        echo "  Max deviation: " . round($maxDeviation, 2) . " ms\n";

        // Sob carga, permitir mais desvio
        $this->assertLessThan(100, $avgDeviation);
    }

    /**
     * Testa erro recovery sob carga
     */
    public function testErrorRecoveryUnderLoad(): void
    {
        $successful = 0;
        $failed = 0;

        for ($i = 0; $i < 100; $i++) {
            if ($i % 10 === 0) {
                // 10% de falhas
                $this->loop->defer(function() use (&$failed) {
                    $failed++;
                    throw new \Exception('Erro');
                });
            } else {
                $this->loop->defer(function() use (&$successful) {
                    $successful++;
                });
            }
        }

        $this->loop->run();

        echo "\nError recovery under load:\n";
        echo "  Successful: " . $successful . "\n";
        echo "  Failed: " . $failed . "\n";

        $this->assertEquals(90, $successful);
        $this->assertEquals(10, $failed);
        $this->assertCount(10, $this->loop->getErrors());
    }

    /**
     * Testa estabilidade de longa duração
     */
    public function testLongRunningStability(): void
    {
        $count = 0;
        $start = microtime(true);
        $duration = 0.5; // 500ms

        $repeatId = $this->loop->repeat(0.001, function() use (&$count) {
            $count++;
        });

        $this->loop->after(function() use ($repeatId) {
            $this->loop->cancel($repeatId);
        }, $duration);

        $this->loop->run();

        $elapsed = microtime(true) - $start;
        $throughput = $count / $elapsed;

        echo "\nLong running (500ms):\n";
        echo "  Iterations: " . $count . "\n";
        echo "  Throughput: " . number_format($throughput, 0) . " ops/sec\n";
        echo "  Actual time: " . round($elapsed, 3) . "s\n";

        $this->assertGreaterThan(0, $count);
        $this->assertGreaterThan(100, $throughput);
    }

    /**
     * Testa picos de carga (reduzido)
     */
    public function testLoadSpike(): void
    {
        // Operações normais
        for ($i = 0; $i < 50; $i++) {
            $this->loop->defer(fn() => null);
        }

        // Pico (300 operações, reduzido de 1000)
        $this->loop->after(function() {
            for ($i = 0; $i < 300; $i++) {
                $this->loop->defer(fn() => null);
            }
        }, 0.001);

        $start = microtime(true);
        $this->loop->run();
        $elapsed = microtime(true) - $start;

        echo "\nLoad spike handling completed in: " . round($elapsed, 3) . "s\n";

        // Teste passa se não travou
        $this->assertLessThan(1, $elapsed);
    }
}
