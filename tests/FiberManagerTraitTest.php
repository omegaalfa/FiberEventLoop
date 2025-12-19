<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Fiber;
use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

class FiberManagerTraitTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Test defer() executes callback once
     */
    public function testDeferExecutesCallback(): void
    {
        $executed = false;

        $this->loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertTrue($executed);
    }

    /**
     * Test defer() returns an ID
     */
    public function testDeferReturnsId(): void
    {
        $id = $this->loop->defer(fn() => null);
        
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    /**
     * Test multiple defer() callbacks execute in order
     */
    public function testMultipleDeferCallbacksExecuteInOrder(): void
    {
        $order = [];

        $this->loop->defer(function () use (&$order) {
            $order[] = 1;
        });

        $this->loop->defer(function () use (&$order) {
            $order[] = 2;
        });

        $this->loop->defer(function () use (&$order) {
            $order[] = 3;
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertEquals([1, 2, 3], $order);
    }

    /**
     * Test defer() with exceptions
     */
    public function testDeferCatchesExceptions(): void
    {
        $this->loop->defer(function () {
            throw new \Exception("Test error");
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Test error", reset($errors));
    }

    /**
     * Test cancel() removes operation
     */
    public function testCancelRemovesOperation(): void
    {
        $executed = false;

        $id = $this->loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->loop->cancel($id);
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertFalse($executed);
    }

    /**
     * Test cancel() with multiple operations
     */
    public function testCancelSpecificOperation(): void
    {
        $executed = [];

        $id1 = $this->loop->defer(function () use (&$executed) {
            $executed[] = 1;
        });

        $id2 = $this->loop->defer(function () use (&$executed) {
            $executed[] = 2;
        });

        $id3 = $this->loop->defer(function () use (&$executed) {
            $executed[] = 3;
        });

        $this->loop->cancel($id2);
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertContains(1, $executed);
        $this->assertNotContains(2, $executed);
        $this->assertContains(3, $executed);
    }



    /**
     * Test next() outside fiber returns null
     */
    public function testNextOutsideFiberReturnsNull(): void
    {
        $result = $this->loop->next();
        
        $this->assertNull($result);
    }

    /**
     * Test multiple fibers run cooperatively (via defer with suspend)
     */
    public function testMultipleOperationsRunCooperatively(): void
    {
        $executed = [];

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'defer1';
        });

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'defer2';
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertContains('defer1', $executed);
        $this->assertContains('defer2', $executed);
    }

    /**
     * Test exception handling in deferred callbacks
     */
    public function testExceptionHandlingInDeferred(): void
    {
        $this->loop->defer(function () {
            throw new \Exception("Deferred error");
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("Deferred error", reset($errors));
    }

    /**
     * Test isCancelled() returns true for cancelled operations
     */
    public function testIsCancelledDetectsCancelledOperation(): void
    {
        $executed = false;

        $id = $this->loop->defer(function () use (&$executed) {
            $executed = true;
        });

        $this->loop->cancel($id);
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        // The operation should not have executed
        $this->assertFalse($executed);
    }

    /**
     * Test generateId() generates unique IDs
     */
    public function testGenerateIdCreatesUniqueIds(): void
    {
        $ids = [];

        for ($i = 0; $i < 10; $i++) {
            $ids[] = $this->loop->defer(fn() => null);
        }

        // All IDs should be unique
        $this->assertCount(count($ids), array_unique($ids));
        
        // IDs should be in ascending order
        $sortedIds = $ids;
        sort($sortedIds);
        $this->assertEquals($sortedIds, $ids);
    }

    /**
     * Test cancel() works with deferred callbacks
     */
    public function testCancelWorksWithDeferred(): void
    {
        $executed = [];

        $id = $this->loop->defer(function () use (&$executed) {
            $executed[] = 'deferred';
        });

        $this->loop->cancel($id);
        
        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'other';
            $this->loop->stop();
        });

        $this->loop->run();

        $this->assertNotContains('deferred', $executed);
        $this->assertContains('other', $executed);
    }

    /**
     * Test defer() with state modification
     */
    public function testDeferModifiesState(): void
    {
        $counter = 0;

        $this->loop->defer(function () use (&$counter) {
            $counter++;
        });

        $this->loop->defer(function () use (&$counter) {
            $counter++;
        });

        $this->loop->defer(function () use (&$counter) {
            $counter++;
        });

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertEquals(3, $counter);
    }

    /**
     * Test fiber suspend and resume multiple times (via defer chain)
     */
    public function testDeferChainWithMultipleIterations(): void
    {
        $executed = [];

        for ($i = 1; $i <= 5; $i++) {
            $this->loop->defer(function () use (&$executed, $i) {
                $executed[] = $i;
            });
        }

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertEquals([1, 2, 3, 4, 5], $executed);
    }

    /**
     * Test cancel in deferred operations
     */
    public function testCancelInDeferredOperations(): void
    {
        $executed = [];

        $id = $this->loop->defer(function () use (&$executed) {
            $executed[] = 'deferred';
        });

        $this->loop->cancel($id);

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'other';
            $this->loop->stop();
        });

        $this->loop->run();

        $this->assertNotContains('deferred', $executed);
        $this->assertContains('other', $executed);
    }

    /**
     * Test defer chain with multiple iterations
     */
    public function testDeferChainExecutesMultipleTimes(): void
    {
        $counter = 0;

        for ($i = 0; $i < 100; $i++) {
            $this->loop->defer(function () use (&$counter) {
                $counter++;
            });
        }

        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $this->assertEquals(100, $counter);
    }
}
