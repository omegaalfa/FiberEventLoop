<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Test complex scenario: deferred + timers + state
     */
    public function testComplexScenarioWithMultipleOperations(): void
    {
        $state = [
            'events' => [],
            'errors' => 0,
        ];

        // Start with deferred operations
        $this->loop->defer(function () use (&$state) {
            $state['events'][] = 'start';
        });

        // Add timers
        $this->loop->after(function () use (&$state) {
            $state['events'][] = 'after_10ms';
        }, 0.01);

        // Add repeat
        $this->loop->repeat(0.02, function () use (&$state) {
            $state['events'][] = 'repeat_20ms';
        }, 2);

        // Add deferred with error handling
        $this->loop->defer(function () use (&$state) {
            try {
                $state['events'][] = 'try_block';
            } catch (\Exception $e) {
                $state['errors']++;
            }
        });

        // Stop after delay
        $this->loop->after(fn() => $this->loop->stop(), 0.1);

        // Run
        $this->loop->run();

        // Verify execution
        $this->assertContains('start', $state['events']);
        $this->assertContains('after_10ms', $state['events']);
        $this->assertContains('try_block', $state['events']);
        $this->assertCount(0, $this->loop->getErrors());
    }

    /**
     * Test server-like scenario
     */
    public function testServerLikeScenario(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        try {
            $connections = 0;
            $requests = [];

            // Set up server listener
            $this->loop->listen($server, function ($client) use (&$connections, &$requests) {
                $connections++;
                
                // Try to read from client
                $data = @fread($client, 1024);
                if ($data) {
                    $requests[] = $data;
                }
                
                fclose($client);
            });

            // Simulate client connections
            $this->loop->defer(function () use ($server) {
                $port = stream_socket_get_name($server, false);
                preg_match('/(\d+)$/', $port, $matches);
                $port = $matches[1];

                $client = @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 1);
                if ($client) {
                    @fwrite($client, "Hello Server");
                    @fclose($client);
                }
            });

            // Stop after short delay
            $this->loop->after(fn() => $this->loop->stop(), 0.2);

            // Run
            $this->loop->run();

            // Verify
            $this->assertGreaterThanOrEqual(1, $connections);
        } finally {
            fclose($server);
        }
    }

    /**
     * Test file reading scenario
     */
    public function testFileReadingScenario(): void
    {
        // Create temporary file
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $content = "Line 1\nLine 2\nLine 3\n";
        file_put_contents($tempfile, $content);

        try {
            $readData = '';
            $readCount = 0;

            // Read file
            $this->loop->onReadFile($tempfile, function ($data) use (&$readData, &$readCount) {
                $readData .= $data;
                $readCount++;
            }, 10);

            // Run
            $this->loop->run();

            // Verify
            $this->assertStringContainsString("Line 1", $readData);
            $this->assertStringContainsString("Line 2", $readData);
            $this->assertStringContainsString("Line 3", $readData);
        } finally {
            unlink($tempfile);
        }
    }

    /**
     * Test error recovery scenario
     */
    public function testErrorRecoveryScenario(): void
    {
        $successful = 0;
        $failed = 0;

        // Add operation that fails
        $this->loop->defer(function () {
            throw new \Exception("Intentional error");
        });

        // Add operation that succeeds after error
        $this->loop->defer(function () use (&$successful) {
            $successful++;
        });

        // Add more operations
        $this->loop->defer(function () use (&$successful) {
            $successful++;
        });

        $this->loop->defer(fn() => $this->loop->stop());

        // Run
        $this->loop->run();

        // Verify
        $errors = $this->loop->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(2, $successful);
    }

    /**
     * Test cascading operations
     */
    public function testCascadingOperations(): void
    {
        $order = [];

        // Create cascade of operations
        $this->loop->defer(function () use (&$order) {
            $order[] = 1;
        });

        $this->loop->defer(function () use (&$order) {
            $order[] = 2;
        });

        $this->loop->after(function () use (&$order) {
            $order[] = 3;
        }, 0.01);

        $this->loop->repeat(0.02, function () use (&$order) {
            $order[] = 4;
        }, 2);

        $this->loop->after(fn() => $this->loop->stop(), 0.1);

        // Run
        $this->loop->run();

        // Verify all executed
        $this->assertContains(1, $order);
        $this->assertContains(2, $order);
        $this->assertContains(3, $order);
        $this->assertContains(4, $order);
    }

    /**
     * Test metrics in realistic scenario
     */
    public function testMetricsInRealisticScenario(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->loop->defer(fn() => null);
        }

        $this->loop->after(fn() => $this->loop->stop(), 0.05);

        // Run
        $this->loop->run();

        // Check metrics
        $metrics = $this->loop->getMetrics();

        $this->assertGreaterThan(0, $metrics['iterations']);
        $this->assertGreaterThan(0, $metrics['work_cycles']);
        $this->assertGreaterThanOrEqual(0, $metrics['empty_iterations']);
        $this->assertGreaterThanOrEqual(0, $metrics['last_work_time']);
    }

    /**
     * Test optimization level impact
     */
    public function testOptimizationLevelImpact(): void
    {
        $levels = ['latency', 'throughput', 'efficient', 'balanced', 'benchmark'];

        foreach ($levels as $level) {
            $loop = new FiberEventLoop();
            $loop->setOptimizationLevel($level);

            $counter = 0;

            for ($i = 0; $i < 100; $i++) {
                $loop->defer(function () use (&$counter) {
                    $counter++;
                });
            }

            $loop->run();

            $this->assertEquals(100, $counter, "Failed with level: $level");
        }
    }

    /**
     * Test mixed timer intervals
     */
    public function testMixedTimerIntervals(): void
    {
        $events = [];

        // 10ms timer
        $this->loop->after(function () use (&$events) {
            $events[] = '10ms';
        }, 0.01);

        // 20ms timer
        $this->loop->after(function () use (&$events) {
            $events[] = '20ms';
        }, 0.02);

        // 15ms timer
        $this->loop->after(function () use (&$events) {
            $events[] = '15ms';
        }, 0.015);

        $this->loop->after(fn() => $this->loop->stop(), 0.05);

        // Run
        $this->loop->run();

        // Verify all timers executed
        $this->assertContains('10ms', $events);
        $this->assertContains('15ms', $events);
        $this->assertContains('20ms', $events);

        // Verify order (they should be in time order)
        $first_index = array_search('10ms', $events);
        $second_index = array_search('15ms', $events);
        $third_index = array_search('20ms', $events);

        $this->assertLessThan($second_index, $first_index);
        $this->assertLessThan($third_index, $second_index);
    }

    /**
     * Test graceful shutdown
     */
    public function testGracefulShutdown(): void
    {
        $started = false;
        $gracefully_shutdown = false;

        $this->loop->defer(function () use (&$started) {
            $started = true;
        });

        $this->loop->defer(function () use (&$gracefully_shutdown) {
            $gracefully_shutdown = true;
            $this->loop->stop(); // Graceful shutdown
        });

        $this->loop->run();

        $this->assertTrue($started);
        $this->assertTrue($gracefully_shutdown);
    }

    /**
     * Test exception doesn't stop other operations
     */
    public function testExceptionDoesntStopOtherOperations(): void
    {
        $executed = [];

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'before_error';
        });

        $this->loop->defer(function () {
            throw new \Exception("Error");
        });

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'after_error';
        });

        $this->loop->defer(function () use (&$executed) {
            $executed[] = 'final';
            $this->loop->stop();
        });

        $this->loop->run();

        $this->assertContains('before_error', $executed);
        $this->assertContains('after_error', $executed);
        $this->assertContains('final', $executed);

        $errors = $this->loop->getErrors();
        $this->assertCount(1, $errors);
    }

    /**
     * Test heavy load
     */
    public function testHeavyLoad(): void
    {
        $counter = 0;

        // Add 1000 deferred callbacks
        for ($i = 0; $i < 1000; $i++) {
            $this->loop->defer(function () use (&$counter) {
                $counter++;
            });
        }

        $this->loop->run();

        $this->assertEquals(1000, $counter);

        $metrics = $this->loop->getMetrics();
        $this->assertGreaterThan(0, $metrics['work_cycles']);
    }

    /**
     * Test memory efficiency
     */
    public function testMemoryEfficiency(): void
    {
        $initial_memory = memory_get_usage();

        for ($i = 0; $i < 10000; $i++) {
            $this->loop->defer(function () {
                // Do nothing
            });
        }

        $memory_before_run = memory_get_usage();
        $allocation = $memory_before_run - $initial_memory;

        $this->loop->run();

        $final_memory = memory_get_usage();

        // Memory should not grow excessively
        // This is a rough check - exact values depend on PHP engine
        $this->assertLessThan(50000000, $allocation); // Less than 50MB for 10000 operations
    }

    /**
     * Test concurrent-like behavior
     */
    public function testConcurrentLikeBehavior(): void
    {
        $results = [];

        // Simulate concurrent operations
        $this->loop->defer(function () use (&$results) {
            $results['op1'] = 'started';
        });

        $this->loop->defer(function () use (&$results) {
            $results['op2'] = 'started';
        });

        $this->loop->after(function () use (&$results) {
            $results['op1'] = 'completed';
        }, 0.01);

        $this->loop->after(function () use (&$results) {
            $results['op2'] = 'completed';
        }, 0.02);

        $this->loop->after(fn() => $this->loop->stop(), 0.05);

        $this->loop->run();

        // At least one should start, both may complete depending on timing
        $this->assertTrue(isset($results['op1']));
        $this->assertTrue(isset($results['op2']));
    }
}
