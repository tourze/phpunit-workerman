<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\InvalidSocketArgumentException;
use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Tourze\PHPUnitWorkerman\Utility\SocketPair;

/**
 * @internal
 */
#[CoversClass(SocketPair::class)]
#[RunTestsInSeparateProcesses] final class SocketPairTest extends WorkermanTestCase
{
    public function testSocketPairInstantiation(): void
    {
        $socketPair = new SocketPair();

        $this->assertInstanceOf(SocketPair::class, $socketPair);
        $this->assertTrue($socketPair->isConnected());

        $socketPair->close();
    }

    public function testCreateUnix(): void
    {
        $socketPair = SocketPair::createUnix();

        $this->assertInstanceOf(SocketPair::class, $socketPair);
        $this->assertTrue($socketPair->isConnected());

        $socketPair->close();
    }

    public function testGetSockets(): void
    {
        $socketPair = new SocketPair();

        $clientSocket = $socketPair->getClientSocket();
        $serverSocket = $socketPair->getServerSocket();

        $this->assertTrue(is_resource($clientSocket));
        $this->assertTrue(is_resource($serverSocket));

        $socketPair->close();
    }

    public function testSendFromClientToServer(): void
    {
        $socketPair = new SocketPair();

        $testData = 'Hello from client';
        $bytesSent = $socketPair->sendFromClient($testData);

        $this->assertGreaterThan(0, $bytesSent);

        // 等待数据可读
        if ($socketPair->waitForReadable($socketPair->getServerSocket(), 1.0)) {
            $receivedData = $socketPair->readFromServer();
            $this->assertStringContainsString($testData, $receivedData);
        }

        $socketPair->close();
    }

    public function testSendFromServerToClient(): void
    {
        $socketPair = new SocketPair();

        $testData = 'Hello from server';
        $bytesSent = $socketPair->sendFromServer($testData);

        $this->assertGreaterThan(0, $bytesSent);

        // 等待数据可读
        if ($socketPair->waitForReadable($socketPair->getClientSocket(), 1.0)) {
            $receivedData = $socketPair->readFromClient();
            $this->assertStringContainsString($testData, $receivedData);
        }

        $socketPair->close();
    }

    public function testWaitForReadable(): void
    {
        $socketPair = new SocketPair();

        // 发送数据
        $socketPair->sendFromClient('test data');

        // 应该能够等到数据可读
        $readable = $socketPair->waitForReadable($socketPair->getServerSocket(), 1.0);
        $this->assertTrue($readable);

        $socketPair->close();
    }

    public function testWaitForWritable(): void
    {
        $socketPair = new SocketPair();

        // socket 应该是可写的
        $writable = $socketPair->waitForWritable($socketPair->getClientSocket(), 1.0);
        $this->assertTrue($writable);

        $socketPair->close();
    }

    public function testGetSocketInfo(): void
    {
        $socketPair = new SocketPair();

        $info = $socketPair->getSocketInfo($socketPair->getClientSocket());

        $this->assertNotEmpty($info);
        $this->assertArrayHasKey('meta', $info);
        $this->assertArrayHasKey('resource_type', $info);

        $socketPair->close();
    }

    public function testSimulateDelay(): void
    {
        $socketPair = new SocketPair();

        $startTime = microtime(true);
        $socketPair->simulateDelay(0.1); // 100ms
        $endTime = microtime(true);

        $duration = $endTime - $startTime;
        $this->assertGreaterThanOrEqual(0.1, $duration);
        $this->assertLessThan(0.2, $duration); // 允许一些误差

        $socketPair->close();
    }

    public function testSimulateError(): void
    {
        $socketPair = new SocketPair();

        $this->assertTrue($socketPair->isConnected());

        $socketPair->simulateError('connection_reset');

        $this->assertFalse($socketPair->isConnected());
    }

    public function testSimulateInvalidError(): void
    {
        $socketPair = new SocketPair();

        $this->expectException(InvalidSocketArgumentException::class);
        $socketPair->simulateError('invalid_error_type');

        $socketPair->close();
    }

    public function testClose(): void
    {
        $socketPair = new SocketPair();

        $this->assertTrue($socketPair->isConnected());

        $socketPair->close();

        $this->assertFalse($socketPair->isConnected());
    }

    public function testIsConnected(): void
    {
        $socketPair = new SocketPair();

        $this->assertTrue($socketPair->isConnected());

        $socketPair->close();

        $this->assertFalse($socketPair->isConnected());
    }

    public function testSendAfterClose(): void
    {
        $socketPair = new SocketPair();
        $socketPair->close();

        $this->expectException(SocketException::class);
        $socketPair->sendFromClient('test');
    }

    public function testReadAfterClose(): void
    {
        $socketPair = new SocketPair();
        $socketPair->close();

        $this->expectException(SocketException::class);
        $socketPair->readFromServer();
    }

    public function testGetBufferStatus(): void
    {
        $socketPair = new SocketPair();

        $status = $socketPair->getBufferStatus($socketPair->getClientSocket());

        $this->assertNotEmpty($status);
        $this->assertArrayHasKey('readable', $status);
        $this->assertArrayHasKey('writable', $status);
        $this->assertArrayHasKey('has_data', $status);

        $socketPair->close();
    }

    public function testDestructorClosesConnection(): void
    {
        $socketPair = new SocketPair();
        $this->assertTrue($socketPair->isConnected());

        // 销毁对象应该自动关闭连接
        unset($socketPair);

        // 由于对象已被销毁，我们无法直接测试isConnected()
        // 但是析构函数的调用不应该抛出异常，验证新实例仍可创建
        $newSocketPair = new SocketPair();
        $this->assertInstanceOf(SocketPair::class, $newSocketPair);
    }

    public function testReadFromClient(): void
    {
        $socketPair = new SocketPair();

        $testData = 'Hello from server to client';
        $bytesSent = $socketPair->sendFromServer($testData);

        $this->assertGreaterThan(0, $bytesSent);

        // 等待数据可读
        if ($socketPair->waitForReadable($socketPair->getClientSocket(), 1.0)) {
            $receivedData = $socketPair->readFromClient();
            $this->assertStringContainsString($testData, $receivedData);
        }

        $socketPair->close();
    }

    public function testReadFromServer(): void
    {
        $socketPair = new SocketPair();

        $testData = 'Hello from client to server';
        $bytesSent = $socketPair->sendFromClient($testData);

        $this->assertGreaterThan(0, $bytesSent);

        // 等待数据可读
        if ($socketPair->waitForReadable($socketPair->getServerSocket(), 1.0)) {
            $receivedData = $socketPair->readFromServer();
            $this->assertStringContainsString($testData, $receivedData);
        }

        $socketPair->close();
    }
}
