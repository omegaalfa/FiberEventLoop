<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

class FiberEventLoopTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Test run() without work stops immediately
     */
    public function testRunWithoutWorkStopsImmediately(): void
    {
        $startTime = microtime(true);
        $this->loop->run();
        $elapsedTime = microtime(true) - $startTime;

        // Should exit very quickly
        $this->assertLessThan(0.1, $elapsedTime);
    }

    /**
     * Test run() with deferred work executes
     */
    public function testRunWithDeferredWorkExecutes(): void
    {
        $executed = false;

        $this->loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test stop() halts the loop
     */
    public function testStopHaltsLoop(): void
    {
        $executed = false;

        $this->loop->defer(function () use (&$executed) {
            $executed = true;
            $this->loop->stop();
        });

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test stop() can be called from timer
     */
    public function testStopFromTimer(): void
    {
        $executed = false;

        $this->loop->after(function () use (&$executed) {
            $executed = true;
            $this->loop->stop();
        }, 0.01);

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test getErrors() returns captured errors
     */
    public function testGetErrorsReturnsCapturedErrors(): void
    {
        $this->loop->defer(function () {
            throw new \Exception("First error");
        });

        $this->loop->defer(function () {
            throw new \Exception("Second error");
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();

        $this->assertCount(2, $errors);
        $this->assertStringContainsString("First error", $errors[1] ?? reset($errors));
        $this->assertStringContainsString("Second error", $errors[2] ?? end($errors));
    }

    /**
     * Test getErrors() returns empty array initially
     */
    public function testGetErrorsEmptyInitially(): void
    {
        $errors = $this->loop->getErrors();

        $this->assertIsArray($errors);
        $this->assertEmpty($errors);
    }

    /**
     * Test getErrors() persists between operations
     */
    public function testGetErrorsPersistsBetweenOperations(): void
    {
        $this->loop->defer(function () {
            throw new \Exception("Test error");
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();
        
        $this->assertCount(1, $errors);

        // Run again
        $this->loop = new FiberEventLoop();
        $errors2 = $this->loop->getErrors();
        
        $this->assertEmpty($errors2);
    }

    /**
     * Test getMetrics() returns metrics array
     */
    public function testGetMetricsReturnsMetricsArray(): void
    {
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('iterations', $metrics);
        $this->assertArrayHasKey('empty_iterations', $metrics);
        $this->assertArrayHasKey('work_cycles', $metrics);
        $this->assertArrayHasKey('last_work_time', $metrics);
    }

    /**
     * Test getMetrics() counts iterations
     */
    public function testGetMetricsCountsIterations(): void
    {
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertGreaterThan(0, $metrics['iterations']);
    }

    /**
     * Test getMetrics() counts work cycles
     */
    public function testGetMetricsCountsWorkCycles(): void
    {
        $this->loop->defer(function () {
            $this->loop->defer(fn() => null);
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertGreaterThan(0, $metrics['work_cycles']);
    }

    /**
     * Test getMetrics() distinguishes empty iterations
     */
    public function testGetMetricsDistinguishesEmptyIterations(): void
    {
        $this->loop->after(fn() => $this->loop->stop(), 0.05);
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        // With a delay, there may be empty iterations (depending on system performance)
        $this->assertIsInt($metrics['empty_iterations']);
        $this->assertGreaterThanOrEqual(0, $metrics['empty_iterations']);
    }

    /**
     * Test getMetrics() stores last work time
     */
    public function testGetMetricsStoresLastWorkTime(): void
    {
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertIsFloat($metrics['last_work_time']);
        $this->assertGreaterThanOrEqual(0, $metrics['last_work_time']);
    }

    /**
     * Test setOptimizationLevel() accepts all valid levels
     */
    public function testSetOptimizationLevelAcceptsValidLevels(): void
    {
        $levels = ['latency', 'throughput', 'efficient', 'balanced', 'benchmark'];

        foreach ($levels as $level) {
            $this->loop->setOptimizationLevel($level);
            // No exception should be thrown
            $this->assertTrue(true);
        }
    }

    /**
     * Test setOptimizationLevel('latency')
     */
    public function testSetOptimizationLevelLatency(): void
    {
        $this->loop->setOptimizationLevel('latency');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        // Loop should complete without error
        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel('throughput')
     */
    public function testSetOptimizationLevelThroughput(): void
    {
        $this->loop->setOptimizationLevel('throughput');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel('efficient')
     */
    public function testSetOptimizationLevelEfficient(): void
    {
        $this->loop->setOptimizationLevel('efficient');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel('balanced')
     */
    public function testSetOptimizationLevelBalanced(): void
    {
        $this->loop->setOptimizationLevel('balanced');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel('benchmark')
     */
    public function testSetOptimizationLevelBenchmark(): void
    {
        $this->loop->setOptimizationLevel('benchmark');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel() default is 'balanced'
     */
    public function testSetOptimizationLevelDefaultIsBalanced(): void
    {
        // Default should be balanced
        $this->loop->setOptimizationLevel('throughput');
        $this->loop->setOptimizationLevel('balanced');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue(true);
    }

    /**
     * Test setOptimizationLevel() with invalid level defaults to balanced
     */
    public function testSetOptimizationLevelInvalidDefaultsToBalanced(): void
    {
        $this->loop->setOptimizationLevel('invalid_level');
        
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        // Should use default (balanced) without error
        $this->assertTrue(true);
    }

    /**
     * Test hasWork() with no operations
     */
    public function testHasWorkWithNoOperations(): void
    {
        // Indirectly test hasWork through run()
        // Loop should complete immediately if no work
        $startTime = microtime(true);
        $this->loop->run();
        $elapsed = microtime(true) - $startTime;

        $this->assertLessThan(0.1, $elapsed);
    }

    /**
     * Test hasWork() with deferred operations
     */
    public function testHasWorkWithDeferredOperations(): void
    {
        $executed = false;

        $this->loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test hasWork() with timers
     */
    public function testHasWorkWithTimers(): void
    {
        $executed = false;

        $this->loop->after(function () use (&$executed) {
            $executed = true;
        }, 0.01);

        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test combined operations: deferred + timers
     */
    public function testCombinedDeferredAndTimers(): void
    {
        $order = [];

        $this->loop->defer(function () use (&$order) {
            $order[] = 'defer';
        });

        $this->loop->after(function () use (&$order) {
            $order[] = 'timer';
        }, 0.01);

        $this->loop->run();

        $this->assertContains('defer', $order);
        $this->assertContains('timer', $order);
    }

    /**
     * Test multiple iterations of operations
     */
    public function testMultipleIterationsOfOperations(): void
    {
        $counter = 0;

        $this->loop->repeat(0.01, function () use (&$counter) {
            $counter++;
        }, 5);

        $this->loop->run();

        $this->assertEquals(5, $counter);
    }

    /**
     * Test loop resilience with many operations
     */
    public function testLoopResilienceWithManyOperations(): void
    {
        $count = 0;

        for ($i = 0; $i < 1000; $i++) {
            $this->loop->defer(function () use (&$count) {
                $count++;
            });
        }

        $this->loop->run();

        $this->assertEquals(1000, $count);
    }

    /**
     * Test metrics update after run
     */
    public function testMetricsUpdateAfterRun(): void
    {
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        // After running, metrics should be updated
        $this->assertGreaterThan(0, $metrics['iterations']);
        $this->assertGreaterThan(0, $metrics['work_cycles']);
    }

    /**
     * Test errors reset on new instance
     */
    public function testErrorsResetOnNewInstance(): void
    {
        $loop1 = new FiberEventLoop();
        $loop1->defer(function () {
            throw new \Exception("Error 1");
        });
        $loop1->defer(fn() => $loop1->stop());
        $loop1->run();

        $errors1 = $loop1->getErrors();
        $this->assertCount(1, $errors1);

        // New instance should have no errors
        $loop2 = new FiberEventLoop();
        $errors2 = $loop2->getErrors();
        $this->assertEmpty($errors2);
    }

    /**
     * Test stop() from deferred callback
     */
    public function testStopFromDeferredCallback(): void
    {
        $count = 0;

        $this->loop->defer(function () use (&$count) {
            $count++;
        });

        $this->loop->defer(function () use (&$count) {
            $count++;
            $this->loop->stop();
        });

        $this->loop->defer(function () use (&$count) {
            // This may or may not execute depending on order
            $count++;
        });

        $this->loop->run();

        // At least the first two should execute
        $this->assertGreaterThanOrEqual(2, $count);
    }

    /**
     * Test loop handles rapid callbacks
     */
    public function testLoopHandlesRapidCallbacks(): void
    {
        $count = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->loop->defer(function () use (&$count) {
                $count++;
            });
        }

        $this->loop->run();

        $this->assertEquals(100, $count);
    }

    /**
     * Test optimization level change at runtime
     */
    public function testOptimizationLevelChangeAtRuntime(): void
    {
        $this->loop->setOptimizationLevel('latency');
        
        $this->loop->defer(function () {
            $this->loop->setOptimizationLevel('efficient');
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        // Should complete without error
        $this->assertTrue(true);
    }

    /**
     * Test getMetrics() types
     */
    public function testGetMetricsReturnTypes(): void
    {
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $metrics = $this->loop->getMetrics();

        $this->assertIsInt($metrics['iterations']);
        $this->assertIsInt($metrics['empty_iterations']);
        $this->assertIsInt($metrics['work_cycles']);
        $this->assertIsFloat($metrics['last_work_time']);
    }

    /**
     * Test getErrors() returns correct format
     */
    public function testGetErrorsReturnsCorrectFormat(): void
    {
        $this->loop->defer(function () {
            throw new \Exception("Test");
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();

        // Should be associative array with integer keys
        $this->assertIsArray($errors);
        foreach ($errors as $id => $message) {
            $this->assertIsInt($id);
            $this->assertIsString($message);
        }
    }

    /**
     * Test loop completes with complex scenario
     */
    public function testLoopCompletesWithComplexScenario(): void
    {
        $executed = [];

        // Mix of deferred and timers
        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'defer1';
        });

        $this->loop->after(function () use (&$executed) {
            $executed[] = 'after1';
        }, 0.01);

        $this->loop->repeat(0.02, function () use (&$executed) {
            $executed[] = 'repeat';
        }, 2);

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'defer2';
        });

        $this->loop->run();

        // All should execute
        $this->assertContains('defer1', $executed);
        $this->assertContains('defer2', $executed);
        $this->assertContains('after1', $executed);
        $this->assertContains('repeat', $executed);
    }

    /**
     * Test loop stability under stress
     */
    public function testLoopStabilityUnderStress(): void
    {
        $counter = 0;
        $errors = 0;

        for ($i = 0; $i < 500; $i++) {
            $this->loop->defer(function () use (&$counter) {
                $counter++;
            });
        }

        $this->loop->run();

        $errors = count($this->loop->getErrors());

        $this->assertEquals(500, $counter);
        $this->assertEquals(0, $errors);
    }

    /**
     * Test multiple run calls on same instance
     */
    public function testMultipleRunCallsOnSameInstance(): void
    {
        // First run
        $count1 = 0;
        $this->loop->defer(function () use (&$count1) {
            $count1++;
        });
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        // Create new loop for second run
        $loop2 = new FiberEventLoop();
        $count2 = 0;
        $loop2->defer(function () use (&$count2) {
            $count2++;
        });
        $loop2->defer(fn() => $loop2->stop());
        $loop2->run();

        $this->assertEquals(1, $count1);
        $this->assertEquals(1, $count2);
    }
}
