<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 基础 Workerman 集成测试
 *
 * 验证我们的测试方案能够正确测试 Workerman 应用
 *
 * @internal
 */
#[CoversClass(WorkermanTestCase::class)]
#[RunTestsInSeparateProcesses] final class BasicWorkermanTest extends WorkermanTestCase
{
    public function testWorkerCreationAndStartup(): void
    {
        $worker = $this->createWorker('tcp://127.0.0.1:8080');

        $startupCalled = false;
        $worker->onWorkerStart = function (Worker $w) use (&$startupCalled): void {
            $startupCalled = true;
            $this->assertSame('tcp://127.0.0.1:8080', $w->getSocketName());
        };

        $this->startWorker($worker);

        $this->assertTrue($startupCalled, 'onWorkerStart 应该被调用');
    }

    public function testMessageHandling(): void
    {
        $worker = $this->createWorker();
        $receivedMessage = null;

        $worker->onMessage = function ($connection, $data) use (&$receivedMessage): void {
            $receivedMessage = $data;
            // Type guard: ensure $data is convertible to string
            $dataString = is_scalar($data) ? (string) $data : json_encode($data);
            $connection->send('echo: ' . $dataString);
        };

        $this->startWorker($worker);
        $this->sendDataToWorker($worker, 'hello world');

        $this->assertEquals('hello world', $receivedMessage);
    }

    public function testConnectionLifecycle(): void
    {
        $worker = $this->createWorker();
        $events = [];

        $worker->onConnect = function ($connection) use (&$events): void {
            $events[] = 'connect';
        };

        $worker->onMessage = function ($connection, $data) use (&$events): void {
            $dataString = is_scalar($data) ? (string) $data : json_encode($data);
            $events[] = "message:{$dataString}";
        };

        $worker->onClose = function ($connection) use (&$events): void {
            $events[] = 'close';
        };

        $this->startWorker($worker);

        // 模拟完整的连接生命周期
        $connection = $this->createMockConnection($worker);
        if (null !== $worker->onConnect) {
            ($worker->onConnect)($connection);
        }

        $this->sendDataToWorker($worker, 'test');
        $this->closeConnection($worker);

        $this->assertEquals(['connect', 'message:test', 'close'], $events);
    }

    public function testTimerIntegration(): void
    {
        $results = [];

        // 创建worker以初始化环境
        $worker = $this->createWorker();
        $this->startWorker($worker);

        // 添加多个定时器
        Timer::add(0.1, function () use (&$results): void {
            $results[] = 'timer1';
        }, [], false);

        Timer::add(0.2, function () use (&$results): void {
            $results[] = 'timer2';
        }, [], false);

        Timer::add(0.05, function () use (&$results): void {
            $results[] = 'timer3';
        }, [], false);

        // 快进时间
        $this->fastForward(0.3);

        // 验证所有定时器都执行了，顺序可能因为ID分配而不同
        $this->assertCount(3, $results);
        $this->assertContains('timer1', $results);
        $this->assertContains('timer2', $results);
        $this->assertContains('timer3', $results);
    }

    public function testRepeatingTimer(): void
    {
        $count = 0;

        // 创建worker以初始化环境
        $worker = $this->createWorker();
        $this->startWorker($worker);

        $timerId = Timer::add(0.1, function () use (&$count): void {
            ++$count;
        }, [], true);

        // 快进1秒，应该执行10次
        $this->fastForward(1.0);

        $this->assertEquals(10, $count);

        // 删除定时器
        Timer::del($timerId);

        // 再快进，不应该再执行
        $this->fastForward(0.5);
        $this->assertEquals(10, $count);
    }

    public function testAsyncWorkflow(): void
    {
        $processedData = [];

        // 创建worker以初始化环境
        $worker = $this->createWorker();
        $this->startWorker($worker);

        // 直接测试Timer的异步处理
        Timer::add(0.1, function () use (&$processedData): void {
            $processedData[] = 'HELLO';
        }, [], false);

        Timer::add(0.2, function () use (&$processedData): void {
            $processedData[] = 'WORLD';
        }, [], false);

        // 快进时间而不是等待
        $this->fastForward(0.3);

        $this->assertEquals(['HELLO', 'WORLD'], $processedData);
    }

    public function testErrorHandling(): void
    {
        $worker = $this->createWorker();
        $errorHandled = false;

        $worker->onMessage = function ($connection, $data): void {
            if ('error' === $data) {
                throw new WorkerException('测试错误');
            }
        };

        $this->startWorker($worker);

        // 测试错误处理
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('测试错误');

        $this->sendDataToWorker($worker, 'error');
    }

    public function testMultipleWorkers(): void
    {
        $worker1 = $this->createWorker('tcp://127.0.0.1:8081');
        $worker2 = $this->createWorker('tcp://127.0.0.1:8082');

        $worker1Messages = [];
        $worker2Messages = [];

        $worker1->onMessage = function ($conn, $data) use (&$worker1Messages): void {
            $worker1Messages[] = $data;
        };

        $worker2->onMessage = function ($conn, $data) use (&$worker2Messages): void {
            $worker2Messages[] = $data;
        };

        $this->startWorker($worker1);
        $this->startWorker($worker2);

        $this->sendDataToWorker($worker1, 'message1');
        $this->sendDataToWorker($worker2, 'message2');

        $this->assertEquals(['message1'], $worker1Messages);
        $this->assertEquals(['message2'], $worker2Messages);
    }

    public function testConnectionCount(): void
    {
        $worker = $this->createWorker();

        $this->startWorker($worker);

        // 初始连接数为0
        $this->assertEquals(0, $this->getWorkerConnectionCount($worker));

        // 模拟多个连接
        $connections = $this->createMassConnections($worker, 5);

        $this->assertCount(5, $connections);
    }
}
