<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Workerman\Timer;

/**
 * Workerman 集成测试
 *
 * 测试框架本身的功能是否正常工作
 *
 * @internal
 */
#[CoversClass(WorkermanTestCase::class)]
#[RunTestsInSeparateProcesses] final class WorkermanIntegrationTest extends WorkermanTestCase
{
    public function testFrameworkInitialization(): void
    {
        // 验证模拟事件循环已安装
        $this->assertNotNull($this->mockEventLoop);

        // 验证时间控制功能
        $startTime = $this->getCurrentTime();
        $this->fastForward(1.0);
        $endTime = $this->getCurrentTime();

        $this->assertEquals(1.0, $endTime - $startTime, '时间快进功能应该正常工作');
    }

    public function testWorkerCreationAndManagement(): void
    {
        // 创建多个 Worker
        $worker1 = $this->createWorker('tcp://127.0.0.1:8081');
        $worker2 = $this->createWorker('tcp://127.0.0.1:8082');

        $this->assertCount(2, $this->workers, '应该跟踪创建的 Worker');

        // 验证 Worker 属性
        $this->assertEquals('tcp://127.0.0.1:8081', $worker1->getSocketName());
        $this->assertEquals('tcp://127.0.0.1:8082', $worker2->getSocketName());
    }

    public function testTimerIntegration(): void
    {
        $executionOrder = [];

        // 添加多个定时器
        Timer::add(0.5, function () use (&$executionOrder): void {
            $executionOrder[] = 'timer1';
        }, [], false);

        Timer::add(1.0, function () use (&$executionOrder): void {
            $executionOrder[] = 'timer2';
        }, [], false);

        Timer::add(1.5, function () use (&$executionOrder): void {
            $executionOrder[] = 'timer3';
        }, [], false);

        // 验证初始状态
        $this->assertEquals(3, $this->getPendingTimerCount(), '应该有3个待执行的定时器');

        // 逐步执行
        $this->fastForward(0.5);
        $this->assertEquals(['timer1'], $executionOrder);

        $this->fastForward(0.5);
        $this->assertEquals(['timer1', 'timer2'], $executionOrder);

        $this->fastForward(0.5);
        $this->assertEquals(['timer1', 'timer2', 'timer3'], $executionOrder);

        // 验证所有定时器都已执行
        $this->assertEquals(0, $this->getPendingTimerCount(), '所有定时器都应该已执行');
    }

    public function testConnectionLifecycle(): void
    {
        $worker = $this->createWorker();
        $events = [];

        // 设置所有回调
        $worker->onConnect = function ($conn) use (&$events): void {
            // Type guard: ensure $conn has getRemoteAddress method
            if (!is_object($conn) || !method_exists($conn, 'getRemoteAddress')) {
                return;
            }
            $remoteAddress = $conn->getRemoteAddress();
            $events[] = 'connect:' . (is_string($remoteAddress) ? $remoteAddress : '');
        };

        $worker->onMessage = function ($conn, $data) use (&$events): void {
            // Type guard: ensure $data is convertible to string
            $dataString = is_scalar($data) ? (string) $data : json_encode($data);
            $events[] = 'message:' . $dataString;

            // Type guard: ensure $conn has send method
            if (is_object($conn) && method_exists($conn, 'send')) {
                $conn->send('ack:' . $dataString);
            }
        };

        $worker->onClose = function ($conn) use (&$events): void {
            // Type guard: ensure $conn has getRemoteAddress method
            if (!is_object($conn) || !method_exists($conn, 'getRemoteAddress')) {
                return;
            }
            $remoteAddress = $conn->getRemoteAddress();
            $events[] = 'close:' . (is_string($remoteAddress) ? $remoteAddress : '');
        };

        $this->startWorker($worker);

        // 创建连接
        $connection = $this->createMockConnection($worker, '192.168.1.100:8080');

        // 触发连接事件
        if (null !== $worker->onConnect) {
            ($worker->onConnect)($connection);
        }

        // 发送消息
        $this->mockConnectionReceive($connection, 'hello');

        // 关闭连接
        $this->mockConnectionClose($connection);

        $expectedEvents = [
            'connect:192.168.1.100:8080',
            'message:hello',
            'close:192.168.1.100:8080',
        ];

        $this->assertEquals($expectedEvents, $events, '事件应该按顺序触发');

        // 验证响应
        $this->assertConnectionSent($connection, 'ack:hello');
    }

    public function testAsyncOperationChain(): void
    {
        $results = [];

        // 创建异步操作链
        Timer::add(0.1, function () use (&$results): void {
            $results[] = 'step1';

            Timer::add(0.2, function () use (&$results): void {
                $results[] = 'step2';

                Timer::add(0.3, function () use (&$results): void {
                    $results[] = 'step3';
                }, [], false);
            }, [], false);
        }, [], false);

        // 执行异步链
        $this->fastForward(0.6); // 总共 0.1 + 0.2 + 0.3 = 0.6 秒

        $this->assertEquals(['step1', 'step2', 'step3'], $results, '异步操作链应该按顺序执行');
    }

    public function testConcurrentConnections(): void
    {
        $worker = $this->createWorker();
        $messageCounter = 0;

        $worker->onMessage = function ($conn, $data) use (&$messageCounter): void {
            ++$messageCounter;
            // Type guard: ensure $conn has send method
            if (is_object($conn) && method_exists($conn, 'send')) {
                $conn->send("response_{$messageCounter}");
            }
        };

        $this->startWorker($worker);

        // 创建大量并发连接
        $connections = $this->createMassConnections($worker, 100);

        $this->assertCount(100, $connections, '应该创建100个连接');

        // 每个连接发送消息
        foreach ($connections as $index => $connection) {
            $this->mockConnectionReceive($connection, "message_from_{$index}");
        }

        $this->assertEquals(100, $messageCounter, '应该处理100条消息');
    }

    public function testRepeatingTimerWithCleanup(): void
    {
        $executionCount = 0;

        // 添加重复定时器
        $timerId = Timer::add(0.1, function () use (&$executionCount): void {
            ++$executionCount;
        }, [], true);

        // 让定时器执行几次
        $this->fastForward(0.5);
        $this->assertEquals(5, $executionCount, '重复定时器应该执行5次');

        // 删除定时器
        Timer::del($timerId);

        // 继续快进，定时器不应该再执行
        $this->fastForward(0.5);
        $this->assertEquals(5, $executionCount, '删除后定时器不应该再执行');
    }

    public function testErrorPropagation(): void
    {
        $worker = $this->createWorker();
        $errorCaught = false;

        $worker->onMessage = function ($conn, $data): void {
            if ('trigger_error' === $data) {
                throw new WorkerException('测试异常');
            }
        };

        $this->startWorker($worker);

        // 触发错误
        try {
            $this->sendDataToWorker($worker, 'trigger_error');
        } catch (\RuntimeException $e) {
            $errorCaught = true;
            $this->assertEquals('测试异常', $e->getMessage());
        }

        $this->assertTrue($errorCaught, '异常应该被正确传播');
    }

    public function testComplexScenario(): void
    {
        // 复杂场景：多个 Worker，定时器，连接交互
        $httpWorker = $this->createWorker('tcp://127.0.0.1:8080');
        $wsWorker = $this->createWorker('tcp://127.0.0.1:8081');

        $stats = [
            'http_requests' => 0,
            'ws_messages' => 0,
            'timer_ticks' => 0,
        ];

        // HTTP Worker
        $httpWorker->onMessage = function ($conn, $data) use (&$stats): void {
            ++$stats['http_requests'];
            // Type guard: ensure $conn has send method
            if (is_object($conn) && method_exists($conn, 'send')) {
                $conn->send("HTTP/1.1 200 OK\r\n\r\nHello World");
            }
        };

        // WebSocket Worker (模拟)
        $wsWorker->onMessage = function ($conn, $data) use (&$stats): void {
            ++$stats['ws_messages'];
            // Type guard: ensure $data is convertible to string and $conn has send method
            if (is_object($conn) && method_exists($conn, 'send')) {
                $dataString = is_scalar($data) ? (string) $data : json_encode($data);
                $conn->send('WS: ' . $dataString);
            }
        };

        // 统计定时器
        Timer::add(0.1, function () use (&$stats): void {
            ++$stats['timer_ticks'];
        }, [], true);

        // 启动所有服务
        $this->startWorker($httpWorker);
        $this->startWorker($wsWorker);

        // 模拟客户端活动
        for ($i = 0; $i < 10; ++$i) {
            // HTTP 请求
            $this->sendDataToWorker($httpWorker, "GET / HTTP/1.1\r\n\r\n");

            // WebSocket 消息
            $this->sendDataToWorker($wsWorker, "Hello {$i}");

            // 时间推进
            $this->fastForward(0.05);
        }

        // 最终检查
        $this->assertEquals(10, $stats['http_requests'], '应该处理10个HTTP请求');
        $this->assertEquals(10, $stats['ws_messages'], '应该处理10个WS消息');
        $this->assertGreaterThan(0, $stats['timer_ticks'], '定时器应该执行');
    }

    public function testMemoryAndResourceCleanup(): void
    {
        $initialTimerCount = $this->getPendingTimerCount();

        // 创建资源
        $worker = $this->createWorker();
        $connections = [];

        for ($i = 0; $i < 10; ++$i) {
            $connection = $this->createMockConnection($worker);
            $connections[] = $connection;

            Timer::add(1.0, function (): void {}, [], false);
        }

        $this->assertEquals($initialTimerCount + 10, $this->getPendingTimerCount());

        // 清理资源（通过 tearDown 自动处理）
        $this->cleanupWorkers();
        if (null !== $this->mockEventLoop) {
            $this->mockEventLoop->clear();
        }

        // 验证清理
        $this->assertEmpty($this->workers, 'Workers 应该被清理');
        if (null !== $this->mockEventLoop) {
            $this->assertEquals(0, $this->mockEventLoop->getTimerCount(), '定时器应该被清理');
        }
    }
}
