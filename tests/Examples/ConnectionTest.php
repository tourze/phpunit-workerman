<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Text;
use Workerman\Timer;

/**
 * 连接测试示例
 *
 * 演示如何测试 Workerman 的连接相关功能
 *
 * @internal
 */
#[CoversClass(WorkermanTestCase::class)]
#[RunTestsInSeparateProcesses] final class ConnectionTest extends WorkermanTestCase
{
    public function testBasicConnection(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        // 验证连接基本属性
        $this->assertInstanceOf(TcpConnection::class, $connection);
        $this->assertEquals('127.0.0.1:12345', $connection->getRemoteAddress());
        $this->assertConnectionStatus($connection, TcpConnection::STATUS_ESTABLISHED);
    }

    public function testConnectionSend(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        // 发送数据
        $result = $this->mockConnectionSend($connection, 'Hello World');

        $this->assertTrue($result, '发送应该成功');
        $this->assertConnectionSent($connection, 'Hello World');
    }

    public function testConnectionReceive(): void
    {
        $worker = $this->createWorker();
        $receivedData = null;

        $worker->onMessage = function ($conn, $data) use (&$receivedData): void {
            $receivedData = $data;
        };

        $connection = $this->createMockConnection($worker);

        // 模拟接收数据
        $this->mockConnectionReceive($connection, 'test data');

        $this->assertEquals('test data', $receivedData, '应该接收到正确的数据');
    }

    public function testConnectionClose(): void
    {
        $worker = $this->createWorker();
        $closeCalled = false;

        $worker->onClose = function ($conn) use (&$closeCalled): void {
            $closeCalled = true;
        };

        $connection = $this->createMockConnection($worker);

        // 关闭连接
        $this->mockConnectionClose($connection);

        $this->assertTrue($closeCalled, 'onClose 回调应该被调用');
        $this->assertConnectionStatus($connection, TcpConnection::STATUS_CLOSED);
    }

    public function testConnectionBuffer(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        $bufferFullCalled = false;
        $bufferDrainCalled = false;

        $connection->onBufferFull = function ($conn) use (&$bufferFullCalled): void {
            $bufferFullCalled = true;
        };

        $connection->onBufferDrain = function ($conn) use (&$bufferDrainCalled): void {
            $bufferDrainCalled = true;
        };

        // 发送大量数据触发缓冲区满
        $largeData = str_repeat('x', 2000000); // 2MB
        $this->mockConnectionSend($connection, $largeData);

        $this->assertTrue($bufferFullCalled, 'onBufferFull 应该被触发');
        $this->assertTrue($bufferDrainCalled, 'onBufferDrain 应该被触发');
    }

    public function testConnectionProtocol(): void
    {
        $worker = $this->createWorker();
        $worker->protocol = Text::class;

        $receivedData = null;
        $worker->onMessage = function ($conn, $data) use (&$receivedData): void {
            $receivedData = $data;
        };

        $connection = $this->createMockConnection($worker);

        // 发送文本协议数据（以换行符结尾）
        $this->mockConnectionReceive($connection, "Hello World\n");

        // Text 协议会去掉换行符
        $this->assertEquals('Hello World', $receivedData);
    }

    public function testMultipleConnections(): void
    {
        $worker = $this->createWorker();
        $connectionCounts = [];

        $worker->onConnect = function ($conn) use (&$connectionCounts): void {
            $connectionCounts[] = $conn->getRemoteAddress();
        };

        // 创建多个连接
        $connections = [];
        for ($i = 0; $i < 5; ++$i) {
            $address = "127.0.0.1:1000{$i}";
            $connection = $this->createMockConnection($worker, $address);
            $connections[] = $connection;

            // 触发连接回调
            if (null !== $worker->onConnect) {
                ($worker->onConnect)($connection);
            }
        }

        $this->assertCount(5, $connectionCounts, '应该有5个连接');
        $this->assertCount(5, $connections, '应该创建5个连接对象');
    }

    public function testConnectionWithCustomAddress(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker, '192.168.1.100:8080');

        $this->assertEquals('192.168.1.100:8080', $connection->getRemoteAddress());
    }

    public function testConnectionBidirectionalCommunication(): void
    {
        $clientMessages = [];

        $worker = $this->createWorker();
        $worker->onMessage = function ($conn, $data) use (&$clientMessages): void {
            $clientMessages[] = $data;
            $conn->send('echo: ' . $data);
        };

        // 测试单条消息处理，这是核心功能
        $connection = $this->createMockConnection($worker, '127.0.0.1:12346');
        $this->mockConnectionReceive($connection, 'hello');

        $this->assertEquals(['hello'], $clientMessages, '应该接收到消息');
        $this->assertConnectionSent($connection, 'echo: hello', '应该发送回响');
    }

    public function testConnectionTimeout(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        $timeoutCalled = false;

        // 模拟连接超时
        Timer::add(2.0, function () use ($connection, &$timeoutCalled): void {
            $timeoutCalled = true;
            $this->mockConnectionClose($connection);
        }, [], false);

        // 快进时间
        $this->fastForward(2.0);

        $this->assertTrue($timeoutCalled, '连接应该超时');
        $this->assertConnectionStatus($connection, TcpConnection::STATUS_CLOSED);
    }

    public function testConnectionError(): void
    {
        $worker = $this->createWorker();
        $errorHandled = false;

        $worker->onError = function ($conn, $code, $msg) use (&$errorHandled): void {
            $errorHandled = true;
            $this->assertEquals(500, $code);
            $this->assertEquals('测试错误', $msg);
        };

        $worker->onMessage = function ($conn, $data): void {
            if ('error' === $data) {
                throw new WorkerException('测试错误');
            }
        };

        $connection = $this->createMockConnection($worker);

        // 触发错误
        try {
            $this->mockConnectionReceive($connection, 'error');
        } catch (\RuntimeException $e) {
            // 错误被捕获并处理
        }

        $this->assertTrue($errorHandled, '错误应该被处理');
        $this->assertConnectionStatus($connection, TcpConnection::STATUS_CLOSED);
    }

    public function testConnectionStatistics(): void
    {
        $worker = $this->createWorker();
        $stats = [
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'messages_sent' => 0,
            'messages_received' => 0,
        ];

        $worker->onMessage = function ($conn, $data) use (&$stats): void {
            $stats['bytes_received'] += strlen($data);
            ++$stats['messages_received'];

            $response = 'ack';
            $conn->send($response);
            $stats['bytes_sent'] += strlen($response);
            ++$stats['messages_sent'];
        };

        $connection = $this->createMockConnection($worker, '127.0.0.1:12347');

        // 发送单条消息进行测试
        $this->mockConnectionReceive($connection, 'hello');

        $this->assertEquals(5, $stats['bytes_received']); // 'hello' = 5 bytes
        $this->assertEquals(3, $stats['bytes_sent']); // 'ack' = 3 bytes
        $this->assertEquals(1, $stats['messages_received']);
        $this->assertEquals(1, $stats['messages_sent']);
    }

    public function testConnectionPool(): void
    {
        $worker = $this->createWorker();
        $connectionPool = [];
        $maxConnections = 10;

        $worker->onConnect = function (TcpConnection $conn) use (&$connectionPool, $maxConnections): void {
            if (count($connectionPool) >= $maxConnections) {
                // 连接池满，关闭新连接
                $conn->close();

                return;
            }

            $connectionPool[$conn->id] = $conn;
        };

        $worker->onClose = function (TcpConnection $conn) use (&$connectionPool): void {
            unset($connectionPool[$conn->id]);
        };

        // 创建超过限制的连接
        $connections = $this->createMassConnections($worker, 15);

        $this->assertLessThanOrEqual($maxConnections, count($connectionPool));
    }

    public function testConnectionPersistence(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        // 发送多次数据，连接应保持
        for ($i = 0; $i < 10; ++$i) {
            $this->mockConnectionReceive($connection, "message {$i}");
            $this->assertConnectionStatus($connection, TcpConnection::STATUS_ESTABLISHED);
        }

        // 手动关闭
        $this->mockConnectionClose($connection);
        $this->assertConnectionStatus($connection, TcpConnection::STATUS_CLOSED);
    }

    public function testConnectionReconnect(): void
    {
        $worker = $this->createWorker();
        $reconnectCount = 0;

        $worker->onConnect = function ($conn) use (&$reconnectCount): void {
            ++$reconnectCount;
        };

        // 模拟连接-断开-重连
        for ($i = 0; $i < 3; ++$i) {
            $connection = $this->createMockConnection($worker, "127.0.0.1:1000{$i}");

            if (null !== $worker->onConnect) {
                ($worker->onConnect)($connection);
            }

            $this->mockConnectionClose($connection, false);
        }

        $this->assertEquals(3, $reconnectCount, '应该有3次连接');
    }

    public function testConnectionBufferFlush(): void
    {
        $worker = $this->createWorker();
        $connection = $this->createMockConnection($worker);

        // 发送数据
        $this->mockConnectionSend($connection, 'test data');

        // 模拟缓冲区刷新后应该为空
        $this->assertConnectionBufferEmpty($connection);
    }
}
