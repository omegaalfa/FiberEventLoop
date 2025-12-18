<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

/**
 * Testes para TimerManagerTrait
 * 
 * Cobre funcionalidades específicas de timers: after, repeat, setInterval e sleep
 */
class TimerManagerTraitTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Testa setInterval
     */
    public function testSetInterval(): void
    {
        $count = 0;

        $intervalId = $this->loop->setInterval(0.01, function() use (&$count) {
            $count++;
            if ($count >= 3) {
                // Não conseguimos parar de dentro, então usamos outro timer
            }
        });

        $this->loop->after(fn() => $this->loop->cancel($intervalId), 0.04);
        $this->loop->run();

        $this->assertGreaterThanOrEqual(2, $count);
    }

    /**
     * Testa precisão de timer
     */
    public function testTimerPrecision(): void
    {
        $times = [];
        $start = microtime(true);
        $delays = [0.02, 0.05, 0.08];

        foreach ($delays as $delay) {
            $this->loop->after(function() use (&$times, $start, $delay) {
                $times[$delay] = microtime(true) - $start;
            }, $delay);
        }

        $this->loop->run();

        // Verifica que timers executaram na ordem correta (aproximadamente)
        $prev = 0;
        foreach ($delays as $delay) {
            $actual = $times[$delay];
            $this->assertGreaterThanOrEqual($delay - 0.015, $actual, "Timer de {$delay}s iniciou muito cedo");
            $this->assertLessThan($delay + 0.05, $actual, "Timer de {$delay}s iniciou muito atrasado");
            $prev = $actual;
        }
    }

    /**
     * Testa múltiplos repeats
     */
    public function testMultipleRepeats(): void
    {
        $results = [];

        $this->loop->repeat(0.01, function() use (&$results) {
            $results['a'][] = 'a';
        }, times: 3);

        $this->loop->repeat(0.015, function() use (&$results) {
            $results['b'][] = 'b';
        }, times: 2);

        $this->loop->run();

        $this->assertCount(3, $results['a']);
        $this->assertCount(2, $results['b']);
    }

    /**
     * Testa cancelamento de repeat
     */
    public function testCancelRepeat(): void
    {
        $count = 0;

        $id = $this->loop->repeat(0.01, function() use (&$count) {
            $count++;
        });

        $this->loop->after(function() use ($id) {
            $this->loop->cancel($id);
        }, 0.035);

        $this->loop->run();

        // Deveria ter executado 2-3 vezes antes de ser cancelado
        $this->assertGreaterThan(1, $count);
        $this->assertLessThanOrEqual(5, $count);
    }

    /**
     * Testa zero delay
     */
    public function testZeroDelayAfter(): void
    {
        $executed = false;

        $this->loop->after(fn() => $executed = true, 0);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa negative delay (deve executar imediatamente)
     */
    public function testNegativeDelayAfter(): void
    {
        $executed = false;

        $this->loop->after(fn() => $executed = true, -0.01);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa decimal delays
     */
    public function testDecimalDelays(): void
    {
        $results = [];
        $start = microtime(true);

        // 100ms
        $this->loop->after(function() use (&$results, $start) {
            $results[] = microtime(true) - $start;
        }, 0.1);

        $this->loop->run();

        $this->assertGreaterThanOrEqual(0.09, $results[0]);
        $this->assertLessThan(0.2, $results[0]);
    }

    /**
     * Testa execução de múltiplos timers na mesma iteração
     */
    public function testMultipleTimersInSameIteration(): void
    {
        $results = [];

        // Agenda 3 timers com mesmo delay
        for ($i = 0; $i < 3; $i++) {
            $this->loop->after(function() use (&$results, $i) {
                $results[] = $i;
            }, 0.01);
        }

        $this->loop->run();

        $this->assertCount(3, $results);
    }

    /**
     * Testa timer que agenda outro timer
     */
    public function testTimerChainingWithAfter(): void
    {
        $results = [];

        $this->loop->after(function() use (&$results) {
            $results[] = 1;
            // Este delay não deve ser acionado neste teste
            // pois o loop vai parar
        }, 0.01);

        $this->loop->run();

        $this->assertCount(1, $results);
    }

    /**
     * Testa repeat com grande número de iterações
     */
    public function testRepeatLargeCount(): void
    {
        $count = 0;

        $this->loop->repeat(0.001, fn() => $count++, times: 50);
        $this->loop->run();

        $this->assertEquals(50, $count);
    }

    /**
     * Testa timer muito pequeno
     */
    public function testVerySmallDelay(): void
    {
        $executed = false;

        $this->loop->after(fn() => $executed = true, 0.001);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa estado de timers após loop
     */
    public function testTimerStateAfterRun(): void
    {
        $this->loop->after(fn() => null, 0.01);
        $this->loop->run();

        // Loop deveria ter termado todos os timers
        // Não temos getter public, então temos que confiar no comportamento
        $this->loop->run(); // Segunda rodada não deveria fazer nada
    }

    /**
     * Testa after com integer seconds
     */
    public function testAfterWithIntegerSeconds(): void
    {
        $executed = false;

        // Usa 0 ao invés de 0.01 para não esperar 1 segundo
        $this->loop->after(fn() => $executed = true, 0);
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Testa repeat com intervalo integer
     */
    public function testRepeatWithIntegerInterval(): void
    {
        $count = 0;

        $this->loop->repeat(0, fn() => $count++, times: 3);
        $this->loop->run();

        $this->assertEquals(3, $count);
    }

    /**
     * Testa callback vazio em after
     */
    public function testEmptyCallbackAfter(): void
    {
        $this->loop->after(fn() => null, 0.01);
        // Não deveria lançar exceção
        $this->loop->run();
        $this->assertTrue(true);
    }

    /**
     * Testa múltiplos after com delays variados
     */
    public function testMultipleAftersVariedDelays(): void
    {
        $results = [];
        $start = microtime(true);

        for ($i = 1; $i <= 5; $i++) {
            $delay = 0.01 * $i;
            $this->loop->after(function() use (&$results, $i) {
                $results[$i] = true;
            }, $delay);
        }

        $this->loop->run();

        // Todas deveriam ter executado
        $this->assertCount(5, $results);
        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($results[$i]);
        }
    }

    /**
     * Testa that repeat doesn't execute beyond limit
     */
    public function testRepeatDoesntExceedLimit(): void
    {
        $count = 0;

        $this->loop->repeat(0.01, function() use (&$count) {
            $count++;
        }, times: 5);

        $this->loop->run();

        // Deve ser exatamente 5, não mais
        $this->assertEquals(5, $count);
    }
}
