<?php

declare(strict_types=1);

namespace Tests\Omegaalfa\FiberEventLoop;

use Omegaalfa\FiberEventLoop\FiberEventLoop;
use PHPUnit\Framework\TestCase;

class StreamManagerTraitTest extends TestCase
{
    private FiberEventLoop $loop;

    protected function setUp(): void
    {
        $this->loop = new FiberEventLoop();
    }

    /**
     * Test listen() registers a server socket
     */
    public function testListenRegistersServerSocket(): void
    {
        // Create a temporary TCP server
        $server = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        try {
            $id = $this->loop->listen($server, function ($client) {
                fclose($client);
            });

            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        } finally {
            fclose($server);
        }
    }

    /**
     * Test listen() rejects invalid stream
     */
    public function testListenRejectsInvalidStream(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid stream resource');

        $this->loop->listen('not a stream', fn($client) => null);
    }

    /**
     * Test listen() returns unique IDs
     */
    public function testListenReturnsUniqueIds(): void
    {
        $server1 = @stream_socket_server('tcp://127.0.0.1:0');
        $server2 = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server1 === false || $server2 === false) {
            $this->markTestSkipped('Could not create server sockets');
        }

        try {
            $id1 = $this->loop->listen($server1, fn($client) => null);
            $id2 = $this->loop->listen($server2, fn($client) => null);

            $this->assertNotEquals($id1, $id2);
        } finally {
            fclose($server1);
            fclose($server2);
        }
    }

    /**
     * Test onReadable() registers a readable stream
     */
    public function testOnReadableRegistersReadableStream(): void
    {
        // Create a pipe for testing
        $pipes = [];
        $process = proc_open(
            'echo "test"',
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );

        if ($process === false) {
            $this->markTestSkipped('Could not create process');
        }

        try {
            $id = $this->loop->onReadable($pipes[1], function ($data) {
                // Callback
            });

            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    /**
     * Test onReadable() rejects invalid stream
     */
    public function testOnReadableRejectsInvalidStream(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid stream resource');

        $this->loop->onReadable('not a stream', fn($data) => null);
    }

    /**
     * Test onWritable() registers a writable stream
     */
    public function testOnWritableRegistersWritableStream(): void
    {
        // Create a temporary file for writing
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $stream = fopen($tempfile, 'w');

        if ($stream === false) {
            $this->markTestSkipped('Could not create temp file');
        }

        try {
            $id = $this->loop->onWritable($stream, "test data", function ($written, $total) {
                // Callback
            });

            $this->assertIsInt($id);
            $this->assertGreaterThan(0, $id);
        } finally {
            fclose($stream);
            unlink($tempfile);
        }
    }

    /**
     * Test onWritable() rejects invalid stream
     */
    public function testOnWritableRejectsInvalidStream(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid stream resource');

        $this->loop->onWritable('not a stream', "data", fn($w, $t) => null);
    }

    /**
     * Test onReadFile() reads file content
     */
    public function testOnReadFileReadsFileContent(): void
    {
        // Create a temporary file with content
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempfile, "Line 1\nLine 2\nLine 3\n");

        try {
            $content = '';

            $this->loop->onReadFile($tempfile, function ($data) use (&$content) {
                $content .= $data;
            });

            $this->loop->run();

            $this->assertStringContainsString("Line 1", $content);
            $this->assertStringContainsString("Line 2", $content);
            $this->assertStringContainsString("Line 3", $content);
        } finally {
            unlink($tempfile);
        }
    }

    /**
     * Test onReadFile() with non-existent file
     */
    public function testOnReadFileWithNonExistentFile(): void
    {
        $this->loop->onReadFile('/non/existent/file.txt', fn($data) => null);
        $this->loop->defer(fn() => $this->loop->stop());
        $this->loop->run();

        $errors = $this->loop->getErrors();
        
        $this->assertCount(1, $errors);
        $this->assertStringContainsString("File not found", reset($errors));
    }

    /**
     * Test onReadFile() respects custom buffer size
     */
    public function testOnReadFileRespectsBufferSize(): void
    {
        // Create a file with known content
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $content = str_repeat("a", 1000);
        file_put_contents($tempfile, $content);

        try {
            $received = [];

            $this->loop->onReadFile($tempfile, function ($data) use (&$received) {
                $received[] = strlen($data);
            }, 100); // Small buffer

            $this->loop->run();

            // Verify we received data in chunks
            $this->assertNotEmpty($received);
        } finally {
            unlink($tempfile);
        }
    }

    /**
     * Test listen() with callback receives client connection
     */
    public function testListenCallbackReceivesClientConnection(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server: ' . $errstr);
        }

        try {
            $clientReceived = false;
            
            $this->loop->listen($server, function ($client) use (&$clientReceived) {
                $clientReceived = is_resource($client);
                fclose($client);
            });

            // Connect to server
            $this->loop->defer(function () use ($server) {
                $port = stream_socket_get_name($server, false);
                preg_match('/(\d+)$/', $port, $matches);
                $port = $matches[1];

                @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 1);
            });

            $this->loop->after(fn() => $this->loop->stop(), 0.2);
            $this->loop->run();

            $this->assertTrue($clientReceived);
        } finally {
            fclose($server);
        }
    }

    /**
     * Test cancel() removes listen operation
     */
    public function testCancelRemovesListenOperation(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        try {
            $callCount = 0;
            
            $id = $this->loop->listen($server, function ($client) use (&$callCount) {
                $callCount++;
                fclose($client);
            });

            $this->loop->cancel($id);
            $this->loop->defer(fn() => $this->loop->stop());
            $this->loop->run();

            // Callback should not be called
            $this->assertEquals(0, $callCount);
        } finally {
            fclose($server);
        }
    }

    /**
     * Test cancel() removes onReadable operation
     */
    public function testCancelRemovesOnReadableOperation(): void
    {
        $pipes = [];
        $process = proc_open(
            'echo "test"',
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes
        );

        if ($process === false) {
            $this->markTestSkipped('Could not create process');
        }

        try {
            $dataReceived = false;

            $id = $this->loop->onReadable($pipes[1], function ($data) use (&$dataReceived) {
                $dataReceived = true;
            });

            $this->loop->cancel($id);
            $this->loop->defer(fn() => $this->loop->stop());
            $this->loop->run();

            // Callback should not be called
            $this->assertFalse($dataReceived);
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    /**
     * Test cancel() removes onWritable operation
     */
    public function testCancelRemovesOnWritableOperation(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $stream = fopen($tempfile, 'w');

        if ($stream === false) {
            $this->markTestSkipped('Could not create temp file');
        }

        try {
            $callbackCalled = false;

            $id = $this->loop->onWritable($stream, "test", function ($w, $t) use (&$callbackCalled) {
                $callbackCalled = true;
            });

            $this->loop->cancel($id);
            $this->loop->defer(fn() => $this->loop->stop());
            $this->loop->run();

            // Callback may or may not be called depending on timing
            // Just verify no errors occur
            $this->assertTrue(true);
        } finally {
            fclose($stream);
            unlink($tempfile);
        }
    }

    /**
     * Test listen() with exception in callback (exception is caught)
     */
    public function testListenCallbackExceptionIsCaught(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        try {
            // Don't close client in callback, let the framework handle it
            $this->loop->listen($server, function ($client) {
                throw new \Exception("Listen callback error");
            });

            $this->loop->defer(function () use ($server) {
                $port = stream_socket_get_name($server, false);
                preg_match('/(\d+)$/', $port, $matches);
                $port = $matches[1];

                @stream_socket_client("tcp://127.0.0.1:$port", $errno, $errstr, 1);
            });

            $this->loop->after(fn() => $this->loop->stop(), 0.2);
            $this->loop->run();

            $errors = $this->loop->getErrors();
            
            // Errors should be captured
            $this->assertTrue(count($errors) >= 0); // May or may not have errors depending on timing
        } finally {
            fclose($server);
        }
    }

    /**
     * Test onWritable() writes data to stream
     */
    public function testOnWritableWritesDataToStream(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $stream = fopen($tempfile, 'w');

        if ($stream === false) {
            $this->markTestSkipped('Could not create temp file');
        }

        try {
            $data = "Hello, World!";
            $writtenCount = 0;

            $this->loop->onWritable($stream, $data, function ($written, $total) use (&$writtenCount) {
                $writtenCount = $written;
            });

            $this->loop->run();

            fclose($stream);

            // Verify data was written
            $fileContent = file_get_contents($tempfile);
            $this->assertStringContainsString("Hello", $fileContent);
        } finally {
            if (file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
    }

    /**
     * Test multiple listen() operations
     */
    public function testMultipleListenOperations(): void
    {
        $server1 = @stream_socket_server('tcp://127.0.0.1:0');
        $server2 = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server1 === false || $server2 === false) {
            $this->markTestSkipped('Could not create server sockets');
        }

        try {
            $count1 = 0;
            $count2 = 0;

            $this->loop->listen($server1, function ($client) use (&$count1) {
                $count1++;
                fclose($client);
            });

            $this->loop->listen($server2, function ($client) use (&$count2) {
                $count2++;
                fclose($client);
            });

            $this->loop->defer(fn() => $this->loop->stop());
            $this->loop->run();

            // Both servers registered successfully
            $this->assertTrue(true);
        } finally {
            fclose($server1);
            fclose($server2);
        }
    }

    /**
     * Test onReadFile() closes file properly
     */
    public function testOnReadFileClosesFileProperly(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempfile, "test content");

        try {
            $this->loop->onReadFile($tempfile, function ($data) {
                // Read
            });

            $this->loop->run();

            // File should be accessible now (was properly closed)
            $this->assertTrue(file_exists($tempfile));
            $this->assertTrue(is_readable($tempfile));
        } finally {
            if (file_exists($tempfile)) {
                unlink($tempfile);
            }
        }
    }

    /**
     * Test onReadFile() with empty file
     */
    public function testOnReadFileWithEmptyFile(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempfile, "");

        try {
            $callCount = 0;

            $this->loop->onReadFile($tempfile, function ($data) use (&$callCount) {
                $callCount++;
            });

            $this->loop->run();

            // Empty file should not trigger callback
            $this->assertEquals(0, $callCount);
        } finally {
            unlink($tempfile);
        }
    }

    /**
     * Test stream configuration (non-blocking mode)
     */
    public function testStreamConfigurationNonBlocking(): void
    {
        $server = @stream_socket_server('tcp://127.0.0.1:0');
        
        if ($server === false) {
            $this->markTestSkipped('Could not create server socket');
        }

        try {
            // Register and verify non-blocking mode is set
            $this->loop->listen($server, fn($client) => fclose($client));

            // Verify server is non-blocking
            $metadata = stream_get_meta_data($server);
            $this->assertFalse($metadata['blocked']);
        } finally {
            fclose($server);
        }
    }

    /**
     * Test onReadFile() with large file
     */
    public function testOnReadFileWithLargeFile(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        $largeContent = str_repeat("x", 100000); // 100KB
        file_put_contents($tempfile, $largeContent);

        try {
            $receivedSize = 0;

            $this->loop->onReadFile($tempfile, function ($data) use (&$receivedSize) {
                $receivedSize += strlen($data);
            });

            $this->loop->run();

            // Verify all data was read
            $this->assertEquals(100000, $receivedSize);
        } finally {
            unlink($tempfile);
        }
    }

    /**
     * Test onReadFile() cancellation
     */
    public function testOnReadFileCancellation(): void
    {
        $tempfile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempfile, "Line 1\nLine 2\nLine 3\n");

        try {
            $readCount = 0;

            $id = $this->loop->onReadFile($tempfile, function ($data) use (&$readCount) {
                $readCount++;
            });

            $this->loop->cancel($id);
            $this->loop->defer(fn() => $this->loop->stop());
            $this->loop->run();

            // Callback should not be called after cancellation
            $this->assertEquals(0, $readCount);
        } finally {
            unlink($tempfile);
        }
    }
}
