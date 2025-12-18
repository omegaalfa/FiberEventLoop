<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

/**
 * Testes unitários do FiberEventLoop
 * 
 * Cobre funcionalidades básicas de event loop, timers e métricas.
 */
class FiberEventLoopTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Testa execução básica do loop
     */
    public function testLoopExecutesAndStops(): void
    {
        $executed = false;

        $this->loop->after(function() use (&$executed) {
            $executed = true;
        }, 0.01);

        $this->loop->run();

        $this->assertTrue($executed, 'Callback deveria ter sido executado');
    }

    /**
     * Testa timer após (after)
     */
    public function testAfterTimer(): void
    {
        $results = [];
        $startTime = microtime(true);

        $this->loop->after(function() use (&$results, $startTime) {
            $results[] = microtime(true) - $startTime;
        }, 0.1);

        $this->loop->run();

        $this->assertCount(1, $results, 'Timer deveria ter executado uma vez');
        $this->assertGreaterThanOrEqual(0.09, $results[0], 'Timer executou muito cedo');
        $this->assertLessThan(0.2, $results[0], 'Timer executou muito atrasado');
    }

    /**
     * Testa timer repetido (repeat)
     */
    public function testRepeatTimer(): void
    {
        $count = 0;

        $this->loop->repeat(0.01, function() use (&$count) {
            $count++;
        }, times: 3);

        $this->loop->run();

        $this->assertEquals(3, $count, 'Timer repetido deveria ter executado 3 vezes');
    }

    /**
     * Testa repeat com limite de execuções
     */
    public function testRepeatWithLimitedTimes(): void
    {
        $results = [];

        $id = $this->loop->repeat(0.01, function() use (&$results) {
            $results[] = count($results);
        }, times: 5);

        $this->loop->run();

        $this->assertCount(5, $results);
        $this->assertEquals([0, 1, 2, 3, 4], $results);
    }

    /**
     * Testa repeat com cancelamento para evitar infinito
     */
    public function testRepeatWithCancellation(): void
    {
        $count = 0;

        $timerId = $this->loop->repeat(0.01, function() use (&$count, &$timerId) {
            $count++;
            if ($count >= 5) {
                $this->loop->cancel($timerId);
            }
        });

        $this->loop->run();

        $this->assertEquals(5, $count);
    }

    /**
     * Testa cancelamento de timer
     */
    public function testCancelTimer(): void
    {
        $executed = false;

        $timerId = $this->loop->after(function() use (&$executed) {
            $executed = true;
        }, 0.1);

        $this->loop->after(function() use (&$timerId) {
            $this->loop->cancel($timerId);
        }, 0.01);

        $this->loop->run();

        $this->assertFalse($executed, 'Timer cancelado não deveria ter executado');
    }

    /**
     * Testa stop() do loop
     */
    public function testStopLoop(): void
    {
        $results = [];

        $this->loop->repeat(0.01, function() use (&$results) {
            $results[] = 1;
            if (count($results) >= 3) {
                // Stop não para imediatamente, para após essa iteração
            }
        });

        $this->loop->after(function() {
            $this->loop->stop();
        }, 0.05);

        $this->loop->run();

        // Deve ter parado em algum ponto
        $this->assertLessThanOrEqual(10, count($results));
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * Testa defer
     */
    public function testDefer(): void
    {
        $executed = false;

        $this->loop->defer(function() use (&$executed) {
            $executed = true;
        });

        $this->loop->run();

        $this->assertTrue($executed, 'Deferred callback deveria ter executado');
    }

    /**
     * Testa múltiplos defers
     */
    public function testMultipleDefers(): void
    {
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $this->loop->defer(function() use (&$results, $i) {
                $results[] = $i;
            });
        }

        $this->loop->run();

        $this->assertCount(5, $results, 'Todos os defers deveriam ter executado');
    }

    /**
     * Testa erros capturados
     */
    public function testErrorsCapturing(): void
    {
        $this->loop->repeat(0.01, function() {
            throw new \Exception('Erro de teste');
        }, times: 2);

        $this->loop->run();

        $errors = $this->loop->getErrors();
        $this->assertCount(2, $errors, 'Deveria ter capturado 2 erros');
        
        foreach ($errors as $error) {
            $this->assertStringContainsString('Erro de teste', $error);
        }
    }

    /**
     * Testa getMetrics
     */
    public function testGetMetrics(): void
    {
        $this->loop->after(fn() => null, 0.01);
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertArrayHasKey('iterations', $metrics);
        $this->assertArrayHasKey('empty_iterations', $metrics);
        $this->assertArrayHasKey('work_cycles', $metrics);
        $this->assertArrayHasKey('last_work_time', $metrics);

        $this->assertGreaterThan(0, $metrics['iterations']);
        $this->assertGreaterThanOrEqual(0, $metrics['empty_iterations']);
    }

    /**
     * Testa otimização nível latency
     */
    public function testOptimizationLevelLatency(): void
    {
        $this->loop->setOptimizationLevel('latency');
        
        $executed = false;
        $this->loop->after(fn() => $executed = true, 0.01);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa otimização nível throughput
     */
    public function testOptimizationLevelThroughput(): void
    {
        $this->loop->setOptimizationLevel('throughput');
        
        $count = 0;
        $this->loop->repeat(0.01, fn() => $count++, times: 5);
        $this->loop->run();

        $this->assertEquals(5, $count);
    }

    /**
     * Testa otimização nível efficient
     */
    public function testOptimizationLevelEfficient(): void
    {
        $this->loop->setOptimizationLevel('efficient');
        
        $executed = false;
        $this->loop->after(fn() => $executed = true, 0.01);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa otimização nível balanced (padrão)
     */
    public function testOptimizationLevelBalanced(): void
    {
        $this->loop->setOptimizationLevel('balanced');
        
        $executed = false;
        $this->loop->after(fn() => $executed = true, 0.01);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa otimização nível benchmark
     */
    public function testOptimizationLevelBenchmark(): void
    {
        $this->loop->setOptimizationLevel('benchmark');
        
        $count = 0;
        $this->loop->repeat(0.01, fn() => $count++, times: 3);
        $this->loop->run();

        $this->assertEquals(3, $count);
    }

    /**
     * Testa múltiplas operações simultâneas
     */
    public function testMultipleOperationsSimultaneously(): void
    {
        $results = [];

        // Vários timers concorrentes
        for ($i = 0; $i < 3; $i++) {
            $this->loop->after(function() use (&$results, $i) {
                $results[] = "after_$i";
            }, 0.01 * ($i + 1));
        }

        $this->loop->run();

        $this->assertCount(3, $results);
        $this->assertContains('after_0', $results);
        $this->assertContains('after_1', $results);
        $this->assertContains('after_2', $results);
    }

    /**
     * Testa scheduling com múltiplos timers (sem precisão extrema)
     */
    public function testSchedulingMultipleTimers(): void
    {
        $results = [];
        $start = microtime(true);

        $this->loop->after(function() use (&$results, $start) {
            $results[] = 1;
        }, 0.01);

        $this->loop->after(function() use (&$results, $start) {
            $results[] = 2;
        }, 0.02);

        $this->loop->after(function() use (&$results, $start) {
            $results[] = 3;
        }, 0.03);

        $this->loop->run();

        $this->assertCount(3, $results);
        // Verifica que pelo menos foram agendados (ordem pode variar levemente)
        $this->assertContains(1, $results);
        $this->assertContains(2, $results);
        $this->assertContains(3, $results);
    }

    /**
     * Testa loop sem trabalho (deve parar imediatamente)
     */
    public function testLoopWithNoWork(): void
    {
        $startTime = microtime(true);
        $this->loop->run();
        $elapsed = microtime(true) - $startTime;

        // Sem trabalho, deve parar quase imediatamente
        $this->assertLessThan(0.1, $elapsed);
    }

    /**
     * Testa cancelamento de operação por ID
     */
    public function testCancelById(): void
    {
        $executed1 = false;
        $executed2 = false;

        $id1 = $this->loop->after(fn() => $executed1 = true, 0.05);
        $id2 = $this->loop->after(fn() => $executed2 = true, 0.06);

        $this->loop->cancel($id1);

        $this->loop->run();

        $this->assertFalse($executed1, 'Timer cancelado não deveria ter executado');
        $this->assertTrue($executed2, 'Timer não cancelado deveria ter executado');
    }

    /**
     * Testa exceção durante execução não para o loop
     */
    public function testExceptionDoesNotStopLoop(): void
    {
        $executed = false;

        $this->loop->after(function() {
            throw new \Exception('Erro');
        }, 0.01);

        $this->loop->after(function() use (&$executed) {
            $executed = true;
        }, 0.02);

        $this->loop->run();

        $this->assertTrue($executed, 'Loop deveria continuar após exceção');
        $this->assertCount(1, $this->loop->getErrors());
    }

    /**
     * Testa estado inicial do loop
     */
    public function testInitialLoopState(): void
    {
        $this->assertEmpty($this->loop->getErrors());
        $this->assertEmpty($this->loop->getMetrics()['iterations']);
    }

    /**
     * Testa callback com closure binding
     */
    public function testCallbackWithClosureBinding(): void
    {
        $result = null;

        $this->loop->after(function() use (&$result) {
            $result = 'executed';
        }, 0.01);

        $this->loop->run();

        $this->assertEquals('executed', $result);
    }
}
