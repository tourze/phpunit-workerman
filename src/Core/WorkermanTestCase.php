<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Exception\PHPUnitWorkermanException;
use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Tourze\PHPUnitWorkerman\Trait\AsyncAssertions;
use Tourze\PHPUnitWorkerman\Trait\ConnectionMocking;
use Tourze\PHPUnitWorkerman\Trait\TimeControl;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Workerman 专用测试基类
 *
 * 提供完整的 Workerman 测试支持，包括事件循环模拟、时间控制、连接模拟等
 * @internal
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
abstract class WorkermanTestCase extends TestCase
{
    use TimeControl;
    use AsyncAssertions;
    use ConnectionMocking;

    /** @var MockEventLoop 模拟事件循环 */
    protected ?MockEventLoop $mockEventLoop = null;

    /** @var array<Worker> 测试中创建的 Worker 实例 */
    protected array $workers = [];

    /**
     * 测试初始化
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mockEventLoop = new MockEventLoop();
        $this->installMockEventLoop();
        if (null !== $this->mockEventLoop) {
            $this->initializeTimeControl($this->mockEventLoop);
        }
        $this->onSetUp();
    }

    /**
     * 测试清理
     */
    protected function tearDown(): void
    {
        $this->onTearDown();
        $this->cleanupWorkers();
        if (null !== $this->mockEventLoop) {
            $this->mockEventLoop->clear();
        }
        $this->restoreOriginalEventLoop();
        parent::tearDown();
    }

    /**
     * 安装模拟事件循环
     */
    protected function installMockEventLoop(): void
    {
        // 替换 Worker 的全局事件循环
        $reflection = new \ReflectionClass(Worker::class);
        if ($reflection->hasProperty('globalEvent')) {
            $property = $reflection->getProperty('globalEvent');
            $property->setAccessible(true);
            $property->setValue(null, $this->mockEventLoop);
        }

        // 替换 Timer 的事件循环
        $timerReflection = new \ReflectionClass(Timer::class);
        if ($timerReflection->hasProperty('event')) {
            $property = $timerReflection->getProperty('event');
            $property->setAccessible(true);
            $property->setValue(null, $this->mockEventLoop);
        }
    }

    /**
     * 恢复原始事件循环
     */
    protected function restoreOriginalEventLoop(): void
    {
        // 重置为 null，让 Workerman 重新选择事件循环
        $reflection = new \ReflectionClass(Worker::class);
        if ($reflection->hasProperty('globalEvent')) {
            $property = $reflection->getProperty('globalEvent');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }

        $timerReflection = new \ReflectionClass(Timer::class);
        if ($timerReflection->hasProperty('event')) {
            $property = $timerReflection->getProperty('event');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }

    /**
     * 创建测试用 Worker
     *
     * @param string $socketName    Socket 地址
     * @param array  $contextOption Socket 选项
     */
    /**
     * @param array<string, mixed> $contextOption
     */
    protected function createWorker(string $socketName = '', array $contextOption = []): Worker
    {
        $worker = new Worker($socketName, $contextOption);
        $this->workers[] = $worker;

        return $worker;
    }

    /**
     * 清理所有 Worker
     */
    protected function cleanupWorkers(): void
    {
        foreach ($this->workers as $worker) {
            $this->stopWorker($worker);
        }
        $this->workers = [];
    }

    /**
     * 停止 Worker
     */
    protected function stopWorker(Worker $worker): void
    {
        // 模拟停止过程
        if (null !== $worker->onWorkerStop) {
            try {
                ($worker->onWorkerStop)($worker);
            } catch (\Throwable $e) {
                // 忽略停止过程中的异常
            }
        }

        // 关闭主 socket
        $mainSocket = $this->getWorkerProperty($worker, 'mainSocket');
        if (null !== $mainSocket && is_resource($mainSocket)) {
            @fclose($mainSocket);
        }
    }

    /**
     * 模拟 Worker 启动
     */
    protected function startWorker(Worker $worker): void
    {
        // 模拟监听过程
        $transport = $this->getWorkerProperty($worker, 'transport') ?? 'tcp';
        if ('tcp' === $transport || 'ssl' === $transport) {
            $this->mockTcpListen($worker);
        }

        // 触发 onWorkerStart 回调
        if (null !== $worker->onWorkerStart) {
            ($worker->onWorkerStart)($worker);
        }
    }

    /**
     * 模拟 TCP 监听
     */
    protected function mockTcpListen(Worker $worker): void
    {
        // 创建模拟的 socket
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if (false === $socketPair) {
            throw new SocketException('无法创建 socket 对');
        }

        // 设置 mainSocket 属性
        $this->setWorkerProperty($worker, 'mainSocket', $socketPair[0]);

        // 注册接受连接的事件
        $mainSocket = $this->getWorkerProperty($worker, 'mainSocket');
        if (null !== $mainSocket && null !== $this->mockEventLoop) {
            $this->mockEventLoop->onReadable($mainSocket, function ($socket) use ($worker): void {
                $this->acceptConnection($worker, $socket);
            });
        }
    }

    /**
     * 模拟接受连接
     *
     * @param resource $socket
     */
    protected function acceptConnection(Worker $worker, $socket): void
    {
        // 创建模拟连接
        $connection = $this->createMockConnection($worker);

        // 触发 onConnect 回调
        if (null !== $worker->onConnect) {
            ($worker->onConnect)($connection);
        }
    }

    /**
     * 模拟向 Worker 发送数据
     *
     * @param string $data          数据内容
     * @param string $remoteAddress 远程地址
     */
    protected function sendDataToWorker(Worker $worker, string $data, string $remoteAddress = '127.0.0.1:12345'): void
    {
        $connection = $this->createMockConnection($worker, $remoteAddress);

        // 触发 onMessage 回调
        if (null !== $worker->onMessage) {
            ($worker->onMessage)($connection, $data);
        }
    }

    /**
     * 模拟连接关闭
     *
     * @param string $remoteAddress 远程地址
     */
    protected function closeConnection(Worker $worker, string $remoteAddress = '127.0.0.1:12345'): void
    {
        $connection = $this->createMockConnection($worker, $remoteAddress);

        // 触发 onClose 回调
        if (null !== $worker->onClose) {
            ($worker->onClose)($connection);
        }
    }

    /**
     * 断言回调被触发
     *
     * @param callable $callback 回调函数
     * @param callable $trigger  触发操作
     * @param string   $message  断言消息
     */
    protected function assertCallbackTriggered(callable $callback, callable $trigger, string $message = ''): void
    {
        $called = false;
        $wrapper = function (...$args) use ($callback, &$called) {
            $called = true;

            return $callback(...$args);
        };

        call_user_func($trigger, $wrapper);

        $this->assertTrue($called, '' !== $message ? $message : '回调应该被触发');
    }

    /**
     * 断言在指定时间内回调被触发
     *
     * @param float    $timeLimit 时间限制（秒）
     * @param callable $trigger   触发操作
     * @param string   $message   断言消息
     */
    protected function assertCallbackTriggeredWithin(float $timeLimit, callable $trigger, string $message = ''): void
    {
        $called = false;

        $callback = function () use (&$called): void {
            $called = true;
        };

        call_user_func($trigger, $callback);

        // 快进时间并检查
        $this->fastForward($timeLimit);

        $this->assertTrue($called, '' !== $message ? $message : "回调应该在 {$timeLimit} 秒内被触发");
    }

    /**
     * 等待异步操作完成
     *
     * @param callable $condition 完成条件
     * @param float    $timeout   超时时间
     * @param float    $interval  检查间隔
     */
    protected function waitFor(callable $condition, float $timeout = 5.0, float $interval = 0.1): void
    {
        if (null === $this->mockEventLoop) {
            throw new class('Mock event loop not initialized') extends PHPUnitWorkermanException {};
        }
        $startTime = $this->mockEventLoop->getCurrentTime();

        while (!call_user_func($condition)) {
            $this->fastForward($interval);

            if (null !== $this->mockEventLoop && $this->mockEventLoop->getCurrentTime() - $startTime > $timeout) {
                self::fail("等待条件超时（{$timeout}秒）");
            }
        }
    }

    /**
     * 获取 Worker 的属性值
     *
     * @param Worker $worker   Worker 实例
     * @param string $property 属性名
     *
     * @return mixed 属性值
     */
    protected function getWorkerProperty(Worker $worker, string $property): mixed
    {
        try {
            $reflection = new \ReflectionClass($worker);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);

                return $prop->getValue($worker);
            }
        } catch (\ReflectionException $e) {
            // 反射失败，尝试直接访问
        }

        return null;
    }

    /**
     * 设置 Worker 的属性值
     *
     * @param Worker $worker   Worker 实例
     * @param string $property 属性名
     * @param mixed  $value    属性值
     */
    protected function setWorkerProperty(Worker $worker, string $property, mixed $value): void
    {
        try {
            $reflection = new \ReflectionClass($worker);

            if ($reflection->hasProperty($property)) {
                $prop = $reflection->getProperty($property);
                $prop->setAccessible(true);
                $prop->setValue($worker, $value);

                return;
            }
        } catch (\ReflectionException $e) {
            // 反射失败，使用动态属性
        }

        // 无法设置属性
    }

    /**
     * 获取 Worker 的连接数
     */
    protected function getWorkerConnectionCount(Worker $worker): int
    {
        $connections = $this->getWorkerProperty($worker, 'connections');

        return is_array($connections) ? count($connections) : 0;
    }

    /**
     * 运行事件循环指定次数
     *
     * @param int $times 运行次数
     */
    protected function runEventLoop(int $times = 1): void
    {
        if (null !== $this->mockEventLoop) {
            for ($i = 0; $i < $times; ++$i) {
                $this->mockEventLoop->tick();
            }
        }
    }

    /**
     * 获取模拟事件循环实例
     */
    protected function getMockEventLoop(): MockEventLoop
    {
        if (null === $this->mockEventLoop) {
            throw new class('Mock event loop not initialized. Call onSetUp() first.') extends PHPUnitWorkermanException {};
        }

        return $this->mockEventLoop;
    }

    /**
     * 测试设置（子类可重写）
     */
    protected function onSetUp(): void
    {
        // 子类可以重写此方法进行测试特定的设置
    }

    /**
     * 测试清理（子类可重写）
     */
    protected function onTearDown(): void
    {
        // 子类可以重写此方法进行测试特定的清理
    }
}
