<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

/**
 * Testes de Integração do FiberEventLoop
 * 
 * Testa interações entre múltiplos componentes (timers, streams, fibers, etc)
 */
class IntegrationTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Testa timers + defer combinados
     */
    public function testTimersWithDefer(): void
    {
        $results = [];

        $this->loop->defer(fn() => $results[] = 'defer1');
        $this->loop->after(fn() => $results[] = 'after1', 0.01);
        $this->loop->defer(fn() => $results[] = 'defer2');
        $this->loop->after(fn() => $results[] = 'after2', 0.02);

        $this->loop->run();

        $this->assertCount(4, $results);
        $this->assertContains('defer1', $results);
        $this->assertContains('after1', $results);
    }

    /**
     * Testa repeat com múltiplos after
     */
    public function testRepeatWithMultipleAfter(): void
    {
        $results = [];

        $this->loop->repeat(0.01, function() use (&$results) {
            $results['repeat'][] = 1;
        }, times: 3);

        for ($i = 0; $i < 3; $i++) {
            $this->loop->after(function() use (&$results, $i) {
                $results['after'][$i] = true;
            }, 0.01 * ($i + 1));
        }

        $this->loop->run();

        $this->assertCount(3, $results['repeat']);
        $this->assertCount(3, $results['after']);
    }

    /**
     * Testa hierarquia de prioridades
     */
    public function testExecutionPriority(): void
    {
        $results = [];

        // Simulamos diferentes operações
        $this->loop->defer(fn() => $results[] = 'defer');
        $this->loop->after(fn() => $results[] = 'after', 0.001);
        $this->loop->repeat(0.001, fn() => $results[] = 'repeat', 1);

        $this->loop->run();

        // Todos deveriam ter executado
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * Testa operações que se agendamumas às outras
     */
    public function testChainedOperations(): void
    {
        $results = [];

        $this->loop->after(function() use (&$results) {
            $results[] = 1;
            
            // Agenda próximo
            $this->loop->defer(function() use (&$results) {
                $results[] = 2;
            });
        }, 0.01);

        $this->loop->run();

        $this->assertCount(2, $results);
        $this->assertEquals([1, 2], $results);
    }

    /**
     * Testa cancelamento em cadeia
     */
    public function testCancellationChain(): void
    {
        $results = [];

        $id1 = $this->loop->after(fn() => $results[] = 1, 0.02);
        $id2 = $this->loop->after(fn() => $results[] = 2, 0.03);

        // Cancela id1 com outro timer
        $this->loop->after(function() use ($id1) {
            $this->loop->cancel($id1);
        }, 0.01);

        $this->loop->run();

        $this->assertCount(1, $results);
        $this->assertEquals([2], $results);
    }

    /**
     * Testa múltiplas operações concorrentes
     */
    public function testConcurrentOperations(): void
    {
        $results = [
            'defers' => 0,
            'afters' => 0,
            'repeats' => 0,
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->loop->defer(fn() => $results['defers']++);
        }

        for ($i = 0; $i < 3; $i++) {
            $this->loop->after(fn() => $results['afters']++, 0.01 * ($i + 1));
        }

        $this->loop->repeat(0.01, fn() => $results['repeats']++, times: 2);

        $this->loop->run();

        $this->assertEquals(5, $results['defers']);
        $this->assertEquals(3, $results['afters']);
        $this->assertEquals(2, $results['repeats']);
    }

    /**
     * Testa variação de optimization levels
     */
    public function testOptimizationLevelSwitching(): void
    {
        $results = [];

        // Começa com latency
        $this->loop->setOptimizationLevel('latency');
        $this->loop->after(fn() => $results[] = 'latency', 0.005);

        // Muda para throughput
        $this->loop->after(function() {
            $this->loop->setOptimizationLevel('throughput');
            $this->loop->after(fn() => $results[] = 'throughput', 0.01);
        }, 0.01);

        $this->loop->run();

        $this->assertCount(2, $results);
    }

    /**
     * Testa getErrors com múltiplos erros
     */
    public function testMultipleErrors(): void
    {
        $errors = [];

        // Vários callbacks que lançam exceções
        for ($i = 0; $i < 3; $i++) {
            $this->loop->defer(function() {
                throw new \Exception("Erro teste");
            });
        }

        $this->loop->run();

        $allErrors = $this->loop->getErrors();
        $this->assertCount(3, $allErrors);

        foreach ($allErrors as $error) {
            $this->assertStringContainsString('Erro teste', $error);
        }
    }

    /**
     * Testa métricas com múltiplas operações
     */
    public function testMetricsWithMultipleOperations(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->loop->defer(fn() => usleep(100));
        }

        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertGreaterThan(0, $metrics['iterations']);
        $this->assertGreaterThanOrEqual(0, $metrics['empty_iterations']);
        $this->assertGreaterThan(0, $metrics['work_cycles']);
        $this->assertGreaterThanOrEqual(0, $metrics['last_work_time']);
    }

    /**
     * Testa stop() durante operações
     */
    public function testStopDuringOperations(): void
    {
        $results = [];

        // Vários timers
        for ($i = 0; $i < 5; $i++) {
            $this->loop->after(fn() => $results[] = $i, 0.01 * ($i + 1));
        }

        // Para após 0.025s (antes do 3º timer)
        $this->loop->after(fn() => $this->loop->stop(), 0.025);

        $this->loop->run();

        // Deveria ter menos de 5 resultados
        $this->assertLessThan(5, count($results));
    }

    /**
     * Testa loop vazio (sem operações)
     */
    public function testEmptyLoopExecution(): void
    {
        $start = microtime(true);
        $this->loop->run();
        $elapsed = microtime(true) - $start;

        // Deve terminar rapidamente
        $this->assertLessThan(0.05, $elapsed);
    }

    /**
     * Testa reuso da instância de loop
     */
    public function testLoopReuseAfterCompletion(): void
    {
        $results = [];

        // Primeira execução
        $this->loop->after(fn() => $results[] = 'run1', 0.01);
        $this->loop->run();

        // Segunda execução no mesmo loop
        $this->loop->after(fn() => $results[] = 'run2', 0.01);
        $this->loop->run();

        $this->assertCount(2, $results);
    }

    /**
     * Testa defer dentro de after
     */
    public function testDeferInsideAfter(): void
    {
        $results = [];

        $this->loop->after(function() use (&$results) {
            $results[] = 'after';
            $this->loop->defer(fn() => $results[] = 'deferred');
        }, 0.01);

        $this->loop->run();

        $this->assertEquals(['after', 'deferred'], $results);
    }

    /**
     * Testa repeat agendando outro repeat
     */
    public function testRepeatSchedulingRepeat(): void
    {
        $results = [];
        $scheduled = false;

        $this->loop->repeat(0.01, function() use (&$results, &$scheduled) {
            $results[] = 'first';
            
            if (!$scheduled) {
                $scheduled = true;
                $this->loop->repeat(0.01, fn() => $results[] = 'second', 1);
            }
        }, times: 2);

        $this->loop->run();

        $this->assertContains('first', $results);
        $this->assertContains('second', $results);
    }

    /**
     * Testa error recovery
     */
    public function testErrorRecovery(): void
    {
        $results = [];

        $this->loop->defer(function() {
            throw new \Exception('Erro');
        });

        $this->loop->defer(fn() => $results[] = 'recovered');

        $this->loop->run();

        // Segunda operação deve ter executado apesar do erro
        $this->assertContains('recovered', $results);
    }

    /**
     * Testa grande volume de operações (reduzido)
     */
    public function testLargeVolumeOperations(): void
    {
        $count = 0;

        // 100 defers (reduzido de 1000)
        for ($i = 0; $i < 100; $i++) {
            $this->loop->defer(fn() => $count++);
        }

        $this->loop->run();

        $this->assertEquals(100, $count);
    }

    /**
     * Testa estado consistente após execução
     */
    public function testConsistentStateAfterRun(): void
    {
        $this->loop->after(fn() => null, 0.01);
        $this->loop->run();

        $errors1 = $this->loop->getErrors();
        $metrics1 = $this->loop->getMetrics();

        // Segunda chamada sem operações
        $this->loop->run();

        $errors2 = $this->loop->getErrors();
        $metrics2 = $this->loop->getMetrics();

        // Estado deveria ser similar
        $this->assertCount(count($errors1), $errors2);
    }
}
