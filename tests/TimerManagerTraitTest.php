<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

class TimerManagerTraitTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Test after() schedules callback to run after delay
     */
    public function testAfterSchedulesCallbackAfterDelay(): void
    {
        $executed = false;
        $startTime = microtime(true);

        $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 0.01); // 10ms delay

        $this->loop->run();
        $elapsedTime = microtime(true) - $startTime;

        $this->assertTrue($executed);
        $this->assertGreaterThanOrEqual(0.01, $elapsedTime);
    }

    /**
     * Test after() returns an ID
     */
    public function testAfterReturnsTimerId(): void
    {
        $id = $this->loop->after(fn() => $this->loop->stop(), 0.01);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /**
     * Test after() with integer seconds
     */
    public function testAfterWithIntegerSeconds(): void
    {
        $executed = false;

        $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 1);

        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        // The callback shouldn't execute because we stop before 1 second
        $this->assertFalse($executed);
    }

    /**
     * Test after() with decimal seconds (millisecond precision)
     */
    public function testAfterWithDecimalSeconds(): void
    {
        $executed = false;
        $startTime = microtime(true);

        $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 0.05); // 50ms

        $this->loop->run();
        $elapsedTime = microtime(true) - $startTime;

        $this->assertTrue($executed);
        $this->assertGreaterThanOrEqual(0.05, $elapsedTime);
        $this->assertLessThan(0.2, $elapsedTime); // Some tolerance
    }

    /**
     * Test multiple after() timers execute in order
     */
    public function testMultipleAfterTimersExecuteInOrder(): void
    {
        $order = [];

        $this->loop->after(function () use (&$order) {
            $order[] = 3;
        }, 0.03);

        $this->loop->after(function () use (&$order) {
            $order[] = 1;
        }, 0.01);

        $this->loop->after(function () use (&$order) {
            $order[] = 2;
        }, 0.02);

        $this->loop->run();

        $this->assertEquals([1, 2, 3], $order);
    }

    /**
     * Test setInterval() repeats callback indefinitely
     */
    public function testSetIntervalRepeatsCallback(): void
    {
        $counter = 0;

        $this->loop->setInterval(0.01, function () use (&$counter) {
            $counter++;
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        // Should execute approximately 5 times (50ms / 10ms)
        $this->assertGreaterThanOrEqual(3, $counter);
    }

    /**
     * Test setInterval() returns an ID
     */
    public function testSetIntervalReturnsId(): void
    {
        $id = $this->loop->setInterval(0.01, fn() => null);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $this->loop->cancel($id);
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();
    }

    /**
     * Test repeat() executes N times then stops
     */
    public function testRepeatExecutesNTimes(): void
    {
        $counter = 0;

        $this->loop->repeat(0.01, function () use (&$counter) {
            $counter++;
        }, 5);

        $this->loop->run();

        $this->assertEquals(5, $counter);
    }

    /**
     * Test repeat() without times parameter repeats indefinitely
     */
    public function testRepeatWithoutTimesRepeatsIndefinitely(): void
    {
        $counter = 0;

        $this->loop->repeat(0.01, function () use (&$counter) {
            $counter++;
        });

        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        // Should execute multiple times
        $this->assertGreaterThanOrEqual(3, $counter);
    }

    /**
     * Test repeat() returns an ID
     */
    public function testRepeatReturnsId(): void
    {
        $id = $this->loop->repeat(0.01, fn() => null, 1);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $this->loop->run();
    }

    /**
     * Test repeat() with integer interval
     */
    public function testRepeatWithIntegerInterval(): void
    {
        $executed = false;

        $this->loop->repeat(1, function () use (&$executed) {
            $executed = true;
        }, 1);

        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        // Shouldn't execute because 1 second is too long
        $this->assertFalse($executed);
    }

    /**
     * Test repeat() with decimal interval
     */
    public function testRepeatWithDecimalInterval(): void
    {
        $counter = 0;

        $this->loop->repeat(0.02, function () use (&$counter) {
            $counter++;
        }, 3);

        $this->loop->run();

        $this->assertEquals(3, $counter);
    }

    /**
     * Test cancel() stops timer
     */
    public function testCancelStopsTimer(): void
    {
        $executed = false;

        $id = $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 0.05);

        $this->loop->cancel($id);
        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();

        $this->assertFalse($executed);
    }

    /**
     * Test cancel() works with setInterval()
     */
    public function testCancelWorksWithSetInterval(): void
    {
        $counter = 0;

        $id = $this->loop->setInterval(0.01, function () use (&$counter) {
            $counter++;
        });

        $this->loop->after(function () use ($id) {
            $this->loop->cancel($id);
        }, 0.03);

        $this->loop->after(fn() => $this->loop->stop(), 0.1);
        $this->loop->run();

        // Should execute a few times then stop when cancelled
        $this->assertGreaterThanOrEqual(2, $counter);
        $this->assertLessThan(10, $counter);
    }

    /**
     * Test cancel() works with repeat()
     */
    public function testCancelWorksWithRepeat(): void
    {
        $counter = 0;

        $id = $this->loop->repeat(0.01, function () use (&$counter) {
            $counter++;
        }, 100); // Would normally execute 100 times

        $this->loop->after(function () use ($id) {
            $this->loop->cancel($id);
        }, 0.05);

        $this->loop->run();

        // Should execute only a few times before cancellation
        $this->assertGreaterThan(2, $counter);
        $this->assertLessThan(100, $counter);
    }

    /**
     * Test timer with exception handling
     */
    public function testTimerExceptionHandling(): void
    {
        $this->loop->after(function () {
            throw new \Exception("Timer error");
        }, 0.01);

        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        $errors = $this->loop->getErrors();
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Timer error", reset($errors));
    }

    /**
     * Test sleep() non-blocking wait
     */
    public function testSleepNonBlockingWait(): void
    {
        $executed = [];

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'start';
            // We can't use sleep() outside of a fiber directly in this context
        });

        // Instead, test that the loop doesn't block on timers
        $this->loop->after(function () use (&$executed) {
            $executed[] = 'timer1';
        }, 0.01);

        $this->loop->after(function () use (&$executed) {
            $executed[] = 'timer2';
        }, 0.02);

        $this->loop->run();

        $this->assertContains('start', $executed);
        $this->assertContains('timer1', $executed);
        $this->assertContains('timer2', $executed);
    }

    /**
     * Test getNextTimerDelay() returns correct delay
     */
    public function testGetNextTimerDelayReturnsCorrectValue(): void
    {
        // This would normally be a protected method, so we test indirectly
        // by verifying that timers are executed at the right time
        $executedAt = [];
        $startTime = microtime(true);

        $this->loop->after(function () use (&$executedAt, $startTime) {
            $executedAt[] = microtime(true) - $startTime;
        }, 0.02);

        $this->loop->after(function () use (&$executedAt, $startTime) {
            $executedAt[] = microtime(true) - $startTime;
        }, 0.05);

        $this->loop->run();

        // Verify both timers executed at approximately the right time
        $this->assertCount(2, $executedAt);
        $this->assertGreaterThanOrEqual(0.02, $executedAt[0]);
        $this->assertGreaterThanOrEqual(0.05, $executedAt[1]);
    }

    /**
     * Test multiple timers of same interval execute in order
     */
    public function testMultipleTimersOfSameIntervalExecuteInOrder(): void
    {
        $order = [];

        $this->loop->after(function () use (&$order) {
            $order[] = 'a';
        }, 0.02);

        $this->loop->after(function () use (&$order) {
            $order[] = 'b';
        }, 0.02);

        $this->loop->after(function () use (&$order) {
            $order[] = 'c';
        }, 0.02);

        $this->loop->run();

        $this->assertEquals(['a', 'b', 'c'], $order);
    }

    /**
     * Test repeat() counts executions correctly
     */
    public function testRepeatCountsExecutionsCorrectly(): void
    {
        $counter = 0;

        for ($i = 0; $i < 10; $i++) {
            $this->loop->repeat(0.01, function () use (&$counter) {
                $counter++;
            }, 3);
        }

        $this->loop->run();

        // 10 repeats × 3 times each = 30 executions
        $this->assertEquals(30, $counter);
    }

    /**
     * Test timer precision with very small delays
     */
    public function testTimerPrecisionWithSmallDelays(): void
    {
        $executed = [];
        $startTime = microtime(true);

        // Very small delays
        $this->loop->after(function () use (&$executed, $startTime) {
            $executed[] = microtime(true) - $startTime;
        }, 0.001); // 1ms

        $this->loop->after(function () use (&$executed, $startTime) {
            $executed[] = microtime(true) - $startTime;
        }, 0.002); // 2ms

        $this->loop->run();

        // Both should execute, and in order
        $this->assertCount(2, $executed);
        $this->assertLessThan($executed[1], $executed[0]); // First should finish before second
    }

    /**
     * Test repeat() with state modification
     */
    public function testRepeatWithStateModification(): void
    {
        $state = ['count' => 0, 'sum' => 0];

        $this->loop->repeat(0.01, function () use (&$state) {
            $state['count']++;
            $state['sum'] += $state['count'];
        }, 5);

        $this->loop->run();

        $this->assertEquals(5, $state['count']);
        $this->assertEquals(15, $state['sum']); // 1+2+3+4+5
    }

    /**
     * Test timer execution doesn't block other operations
     */
    public function testTimerExecutionDoesntBlockOtherOperations(): void
    {
        $operations = [];

        $this->loop->defer(function () use (&$operations) {
            $operations[] = 'defer1';
        });

        $this->loop->after(function () use (&$operations) {
            $operations[] = 'timer';
        }, 0.02);

        $this->loop->defer(function () use (&$operations) {
            $operations[] = 'defer2';
        });

        $this->loop->run();

        // Both defers should execute before the timer stops the loop
        $this->assertContains('defer1', $operations);
        $this->assertContains('defer2', $operations);
        $this->assertContains('timer', $operations);
    }

    /**
     * Test after() with zero delay
     */
    public function testAfterWithZeroDelay(): void
    {
        $executed = false;

        $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 0);

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test repeat() with zero times (should execute immediately)
     */
    public function testRepeatWithZeroTimesDoesNotExecute(): void
    {
        $executed = false;

        $this->loop->repeat(0.01, function () use (&$executed) {
            $executed = true;
        }, 0);

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertFalse($executed);
    }
}
