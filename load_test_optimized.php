#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace LoadTest;

require_once __DIR__ . '/vendor/autoload.php';

use Omegaalfa\FiberEventLoop\FiberEventLoop;

/**
 * Optimized Synthetic Load Test - Simulates 1000+ webhooks/second
 *
 * This version uses a simplified algorithm for better performance
 */

class OptimizedWebhookLoadTest
{
    private FiberEventLoop $loop;
    private int $totalWebhooks;
    private int $processedWebhooks = 0;
    private int $failedWebhooks = 0;
    private float $startTime = 0;
    private int $simulatedLatency = 10; // milliseconds
    private int $concurrentWorkers = 10;
    private bool $verbose = false;

    public function __construct(array $options = [])
    {
        $this->loop = new FiberEventLoop();

        $this->totalWebhooks = (int) ($options['webhooks'] ?? 10000);
        $this->simulatedLatency = (int) ($options['latency'] ?? 10);
        $this->concurrentWorkers = (int) ($options['workers'] ?? 10);
        $this->verbose = (bool) ($options['verbose'] ?? false);
    }

    /**
     * Run the load test
     */
    public function run(): void
    {
        $this->startTime = microtime(true);

        echo $this->header("═", 70);
        echo str_pad("Synthetic Webhook Load Test", 70, " ", STR_PAD_BOTH) . "\n";
        echo $this->header("═", 70);
        echo "\nConfiguration:\n";
        echo "  Total Webhooks: " . number_format($this->totalWebhooks) . "\n";
        echo "  Concurrent Workers: {$this->concurrentWorkers}\n";
        echo "  Simulated Latency: {$this->simulatedLatency}ms\n";
        echo "  Target Rate: ~1,000 webhooks/sec\n\n";

        // Schedule all webhooks
        $this->scheduleAllWebhooks();

        // Schedule progress updates
        $this->loop->repeat(0.5, fn() => $this->printProgress(), (int) ($this->getTotalDuration() * 2));

        // Auto-stop
        $this->loop->after(fn() => $this->loop->stop(), $this->getTotalDuration() + 1);

        // Run
        $this->loop->run();

        // Final report
        echo "\n\n";
        $this->printReport();
    }

    /**
     * Schedule all webhooks efficiently
     */
    private function scheduleAllWebhooks(): void
    {
        $webhooksPerWorker = (int) ceil($this->totalWebhooks / $this->concurrentWorkers);
        $delayUnit = $this->getTotalDuration() / max(1, $webhooksPerWorker);

        for ($worker = 0; $worker < $this->concurrentWorkers; $worker++) {
            for ($i = 0; $i < $webhooksPerWorker; $i++) {
                $webhookId = $worker * $webhooksPerWorker + $i;

                if ($webhookId >= $this->totalWebhooks) {
                    break;
                }

                $delay = $delayUnit * $i;

                $this->loop->after(
                    fn() => $this->processWebhook($webhookId),
                    $delay
                );
            }
        }
    }

    /**
     * Process a single webhook
     */
    private function processWebhook(int $id): void
    {
        try {
            // Schedule actual processing after simulated latency
            $this->loop->after(
                fn() => $this->completeWebhook($id),
                $this->simulatedLatency / 1000
            );

            if ($this->verbose) {
                echo "Processing webhook #{$id}\n";
            }
        } catch (\Exception $e) {
            $this->failedWebhooks++;
            if ($this->verbose) {
                echo "Failed webhook #{$id}: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Mark webhook as completed
     */
    private function completeWebhook(int $id): void
    {
        $this->processedWebhooks++;
    }

    /**
     * Print progress
     */
    private function printProgress(): void
    {
        $total = $this->processedWebhooks + $this->failedWebhooks;
        $percentage = min(100, ($total / $this->totalWebhooks) * 100);

        $elapsedTime = microtime(true) - $this->startTime;
        $throughput = $elapsedTime > 0 ? ($this->processedWebhooks / $elapsedTime) : 0;

        $bar = str_repeat("█", (int) ($percentage / 5)) .
               str_repeat("░", 20 - (int) ($percentage / 5));

        echo "\r[{$bar}] " . number_format($percentage, 1) . "% | " .
             number_format($total) . "/" . number_format($this->totalWebhooks) . " | " .
             "Rate: " . number_format($throughput, 0) . "/s | " .
             "Errors: {$this->failedWebhooks}";
    }

    /**
     * Print final report
     */
    private function printReport(): void
    {
        $elapsedTime = microtime(true) - $this->startTime;
        $total = $this->processedWebhooks + $this->failedWebhooks;
        $throughput = $elapsedTime > 0 ? ($this->processedWebhooks / $elapsedTime) : 0;
        $successRate = $total > 0 ? ($this->processedWebhooks / $total) * 100 : 0;

        echo $this->header("═", 70);
        echo str_pad("Final Report", 70, " ", STR_PAD_BOTH) . "\n";
        echo $this->header("═", 70);

        echo "\nTest Duration: " . number_format($elapsedTime, 2) . "s\n";
        echo "Total Processed: " . number_format($total) . "\n";
        echo "  ✓ Successful: " . number_format($this->processedWebhooks) .
             " (" . number_format($successRate, 2) . "%)\n";
        echo "  ✗ Failed: " . number_format($this->failedWebhooks) .
             " (" . number_format(100 - $successRate, 2) . "%)\n\n";

        echo "Performance:\n";
        echo "  Throughput: " . number_format($throughput, 0) . " webhooks/sec\n";
        echo "  Avg Latency: {$this->simulatedLatency}ms\n";
        echo "  Total Time: " . number_format($elapsedTime, 2) . "s\n\n";

        $metrics = $this->loop->getMetrics();
        echo "Event Loop:\n";
        echo "  Iterations: " . number_format($metrics['iterations']) . "\n";
        echo "  Work Cycles: " . number_format($metrics['work_cycles']) . "\n";
        echo "  Empty Iterations: " . number_format($metrics['empty_iterations']) . "\n\n";

        $status = $this->failedWebhooks === 0 ? "✅ SUCCESS" : "⚠️  WARNINGS";
        echo "Status: $status\n";
        echo $this->header("═", 70);
    }

    /**
     * Helper: create header
     */
    private function header(string $char, int $width): string
    {
        return str_repeat($char, $width) . "\n";
    }

    /**
     * Get total test duration in seconds
     */
    private function getTotalDuration(): float
    {
        // Target 1000 webhooks per second
        return max(1.0, $this->totalWebhooks / 1000);
    }
}

// Parse arguments
$options = [];
for ($i = 1; $i < $argc; $i++) {
    if (strpos($argv[$i], '--') === 0) {
        [$key, $value] = array_pad(explode('=', substr($argv[$i], 2)), 2, 'true');
        $options[$key] = $value;
    }
}

if (isset($options['help'])) {
    echo <<<'HELP'
Optimized Webhook Load Test

USAGE: php load_test_optimized.php [OPTIONS]

OPTIONS:
  --webhooks=N   Total webhooks (default: 10000)
  --latency=N    Processing latency in ms (default: 10)
  --workers=N    Concurrent workers (default: 10)
  --verbose      Show detailed output
  --help         Show this help

EXAMPLES:
  php load_test_optimized.php --webhooks=50000 --workers=20
  php load_test_optimized.php --webhooks=100000 --latency=5

HELP;
    exit(0);
}

try {
    $test = new OptimizedWebhookLoadTest($options);
    $test->run();
    exit(0);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
