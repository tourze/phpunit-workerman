<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Workerman\Protocols\Http;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 基础 Worker 测试示例
 *
 * 演示如何测试 Workerman 的基本功能
 *
 * @internal
 */
#[CoversClass(WorkermanTestCase::class)]
#[RunTestsInSeparateProcesses] final class BasicWorkerTest extends WorkermanTestCase
{
    public function testWorkerCreation(): void
    {
        // 创建 Worker
        $worker = $this->createWorker('tcp://127.0.0.1:8080');

        // 验证基本属性
        $this->assertEquals('tcp', $worker->transport);
        $this->assertEquals('tcp://127.0.0.1:8080', $worker->getSocketName());
        $this->assertEquals(1, $worker->count);
    }

    public function testWorkerCallbacks(): void
    {
        $worker = $this->createWorker();

        $onWorkerStartCalled = false;
        $onConnectCalled = false;
        $onMessageCalled = false;
        $onCloseCalled = false;

        // 设置回调
        $worker->onWorkerStart = function (Worker $worker) use (&$onWorkerStartCalled): void {
            $onWorkerStartCalled = true;
            $this->assertInstanceOf(Worker::class, $worker);
        };

        $worker->onConnect = function ($connection) use (&$onConnectCalled): void {
            $onConnectCalled = true;
            $this->assertNotNull($connection);
        };

        $worker->onMessage = function ($connection, $data) use (&$onMessageCalled): void {
            $onMessageCalled = true;
            $this->assertEquals('test message', $data);

            // 回响数据
            $connection->send('echo: ' . $data);
        };

        $worker->onClose = function ($connection) use (&$onCloseCalled): void {
            $onCloseCalled = true;
        };

        // 模拟 Worker 启动
        $this->startWorker($worker);
        $this->assertTrue($onWorkerStartCalled, 'onWorkerStart 应该被调用');

        // 模拟客户端连接
        $connection = $this->createMockConnection($worker);
        if (null !== $worker->onConnect) {
            ($worker->onConnect)($connection);
        }
        $this->assertTrue($onConnectCalled, 'onConnect 应该被调用');

        // 模拟接收消息
        $this->sendDataToWorker($worker, 'test message');
        $this->assertTrue($onMessageCalled, 'onMessage 应该被调用');

        // 模拟连接关闭
        $this->closeConnection($worker);
        $this->assertTrue($onCloseCalled, 'onClose 应该被调用');
    }

    public function testMultipleConnections(): void
    {
        $worker = $this->createWorker();
        $connectionCount = 0;

        $worker->onConnect = function ($connection) use (&$connectionCount): void {
            ++$connectionCount;
        };

        // 创建多个连接
        $connections = $this->createMassConnections($worker, 10);

        $this->assertEquals(10, $connectionCount, '应该有10个连接');
        $this->assertCount(10, $connections, '应该返回10个连接对象');
    }

    public function testWorkerWithProtocol(): void
    {
        $worker = $this->createWorker('tcp://127.0.0.1:8080');

        // 设置协议
        $worker->protocol = Http::class;

        $receivedData = null;
        $worker->onMessage = function ($connection, $data) use (&$receivedData): void {
            $receivedData = $data;

            // 发送 HTTP 响应
            $connection->send("HTTP/1.1 200 OK\r\nContent-Length: 11\r\n\r\nHello World");
        };

        $this->startWorker($worker);

        // 模拟 HTTP 请求
        $httpRequest = "GET / HTTP/1.1\r\nHost: localhost\r\n\r\n";
        $this->sendDataToWorker($worker, $httpRequest);

        $this->assertNotNull($receivedData, '应该接收到 HTTP 请求数据');
        $this->assertStringContainsString('GET /', (string) $receivedData);
    }

    public function testWorkerTimer(): void
    {
        $timerExecuted = false;
        $executionCount = 0;

        // 添加一次性定时器
        Timer::add(1.0, function () use (&$timerExecuted): void {
            $timerExecuted = true;
        }, [], false);

        // 添加重复定时器
        Timer::add(0.5, function () use (&$executionCount): void {
            ++$executionCount;
        }, [], true);

        // 快进时间
        $this->fastForward(2.0);

        $this->assertTrue($timerExecuted, '一次性定时器应该被执行');
        $this->assertEquals(4, $executionCount, '重复定时器应该执行4次（2秒 / 0.5秒间隔）');
    }

    public function testAsyncMessageProcessing(): void
    {
        $worker = $this->createWorker();
        $processedMessages = [];

        $worker->onMessage = function ($connection, $data) use (&$processedMessages): void {
            // 模拟异步处理
            Timer::add(0.1, function () use ($connection, $data, &$processedMessages): void {
                $processedMessages[] = $data;
                $connection->send('processed: ' . $data);
            }, [], false);
        };

        $this->startWorker($worker);

        // 发送多条消息
        $messages = ['msg1', 'msg2', 'msg3'];
        foreach ($messages as $message) {
            $this->sendDataToWorker($worker, $message);
        }

        // 推进时间让定时器执行
        $this->fastForward(0.5);  // 足够让所有0.1秒的定时器执行

        $this->assertEquals($messages, $processedMessages, '所有消息都应该被处理');
    }

    public function testConnectionBufferManagement(): void
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

        // 模拟大量数据发送，触发缓冲区满
        $largeData = str_repeat('x', 2000000); // 2MB 数据
        $this->mockConnectionSend($connection, $largeData);

        $this->assertTrue($bufferFullCalled, 'onBufferFull 应该被调用');
        $this->assertTrue($bufferDrainCalled, 'onBufferDrain 应该被调用');
    }

    public function testWorkerStatistics(): void
    {
        $worker = $this->createWorker();
        $messageCount = 0;
        $connectionCount = 0;

        $worker->onConnect = function () use (&$connectionCount): void {
            ++$connectionCount;
        };

        $worker->onMessage = function () use (&$messageCount): void {
            ++$messageCount;
        };

        $this->startWorker($worker);

        // 模拟多个连接和消息
        for ($i = 0; $i < 5; ++$i) {
            $connection = $this->createMockConnection($worker);
            if (null !== $worker->onConnect) {
                ($worker->onConnect)($connection);
            }

            for ($j = 0; $j < 3; ++$j) {
                $this->sendDataToWorker($worker, "message_{$i}_{$j}");
            }
        }

        $this->assertEquals(5, $connectionCount, '应该有5个连接');
        $this->assertEquals(15, $messageCount, '应该处理15条消息');
    }

    public function testWorkerGracefulShutdown(): void
    {
        $worker = $this->createWorker();
        $shutdownCalled = false;

        $worker->onWorkerStop = function (Worker $worker) use (&$shutdownCalled): void {
            $shutdownCalled = true;
        };

        $this->startWorker($worker);
        $this->stopWorker($worker);

        $this->assertTrue($shutdownCalled, 'onWorkerStop 应该被调用');
    }

    public function testErrorHandling(): void
    {
        $worker = $this->createWorker();
        $errorHandled = false;

        $worker->onError = function ($connection, $code, $msg) use (&$errorHandled): void {
            $errorHandled = true;
            $this->assertNotNull($connection);
            $this->assertIsInt($code);
        };

        $worker->onMessage = function ($connection, $data): void {
            if ('error' === $data) {
                throw new WorkerException('测试错误');
            }
        };

        $this->startWorker($worker);

        // 触发错误
        $connection = $this->createMockConnection($worker);
        try {
            $this->sendDataToWorker($worker, 'error');
        } catch (\RuntimeException $e) {
            // 错误被正确抛出
            $this->assertEquals('测试错误', $e->getMessage());
        }
    }
}
