<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Tourze\PHPUnitWorkerman\Mock\TestableTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

/**
 * 真实 Worker 测试基类
 *
 * 支持测试真实的 Workerman Worker 实例，而不依赖 Mock
 * 通过控制事件循环和网络I/O来实现可控的测试环境
 *
 * @internal
 *
 * @coversNothing
 */
#[CoversNothing]
#[RunTestsInSeparateProcesses]
abstract class RealWorkerTestCase extends TestCase
{
    /** @var MockEventLoop 模拟事件循环 */
    protected MockEventLoop $eventLoop;

    /** @var array<Worker> 测试中的Worker实例 */
    protected array $workers = [];

    /** @var array<array{server: resource, client: resource}|resource> Socket资源池 */
    protected array $socketPairs = [];

    /** @var array<TcpConnection> 真实连接池 */
    protected array $connections = [];

    /** @var bool 是否在测试模式 */
    protected bool $testMode = false;

    protected function setUp(): void
    {
        parent::setUp();

        // 安装模拟事件循环
        $this->eventLoop = new MockEventLoop();
        $this->installEventLoop();

        // 初始化Timer环境
        $this->initializeTimerEnvironment();

        // 设置测试模式
        $this->testMode = true;

        // 阻止真实的监听
        $this->preventRealListening();
    }

    /**
     * 安装事件循环
     */
    private function installEventLoop(): void
    {
        $reflection = new \ReflectionClass(Worker::class);
        $property = $reflection->getProperty('globalEvent');
        $property->setAccessible(true);
        $property->setValue(null, $this->eventLoop);
    }

    /**
     * 初始化Timer环境
     */
    private function initializeTimerEnvironment(): void
    {
        // 设置Timer所需的事件循环
        $reflection = new \ReflectionClass(Timer::class);
        $property = $reflection->getProperty('event');
        $property->setAccessible(true);
        $property->setValue(null, $this->eventLoop);
    }

    /**
     * 阻止真实的监听
     */
    private function preventRealListening(): void
    {
        // 通过环境变量标记测试模式
        $_ENV['WORKERMAN_TEST_MODE'] = '1';
    }

    protected function tearDown(): void
    {
        // 清理资源
        $this->cleanupWorkers();
        $this->cleanupSockets();
        $this->cleanupConnections();

        // 恢复原始状态
        $this->restoreEventLoop();
        $this->testMode = false;

        parent::tearDown();
    }

    /**
     * 清理Workers
     */
    private function cleanupWorkers(): void
    {
        foreach ($this->workers as $worker) {
            if (null !== $worker->onWorkerStop) {
                try {
                    ($worker->onWorkerStop)($worker);
                } catch (\Throwable $e) {
                    // 忽略清理中的异常
                }
            }
        }

        $this->workers = [];
    }

    /**
     * 清理Socket资源
     */
    private function cleanupSockets(): void
    {
        foreach ($this->socketPairs as $socketInfo) {
            if (is_resource($socketInfo)) {
                @fclose($socketInfo);
            } elseif (is_array($socketInfo)) {
                // 类型已确定为数组，直接检查资源类型
                if (isset($socketInfo['server']) && is_resource($socketInfo['server'])) {
                    @fclose($socketInfo['server']);
                }
                if (isset($socketInfo['client']) && is_resource($socketInfo['client'])) {
                    @fclose($socketInfo['client']);
                }
            }
        }

        $this->socketPairs = [];
    }

    /**
     * 清理连接
     */
    private function cleanupConnections(): void
    {
        foreach ($this->connections as $connection) {
            if (null !== $connection->onClose) {
                try {
                    ($connection->onClose)($connection);
                } catch (\Throwable $e) {
                    // 忽略清理中的异常
                }
            }
        }

        $this->connections = [];
    }

    /**
     * 恢复事件循环
     */
    private function restoreEventLoop(): void
    {
        $reflection = new \ReflectionClass(Worker::class);
        $property = $reflection->getProperty('globalEvent');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * 创建真实的Worker实例进行测试
     *
     * @param string $listen  监听地址（在测试中不会真实监听）
     * @param array<string, mixed>  $context Socket上下文
     */
    protected function createRealWorker(string $listen = 'tcp://127.0.0.1:0', array $context = []): Worker
    {
        $worker = new Worker($listen, $context);

        // 禁用进程fork
        $worker->count = 1;

        // 记录Worker
        $this->workers[] = $worker;

        return $worker;
    }

    /**
     * 启动Worker（在测试环境中）
     *
     * @param Worker $worker Worker实例
     */
    protected function startRealWorker(Worker $worker): void
    {
        // 创建测试用的socket pair
        $socketPair = $this->createSocketPair();

        // 替换Worker的mainSocket
        $this->setWorkerProperty($worker, 'mainSocket', $socketPair['server']);

        // 设置Worker的运行状态
        $this->setWorkerProperty($worker, 'status', Worker::STATUS_RUNNING);

        // 触发onWorkerStart回调
        if (null !== $worker->onWorkerStart) {
            ($worker->onWorkerStart)($worker);
        }

        // 将Worker注册到事件循环中
        $this->registerWorkerEvents($worker, $socketPair);
    }

    /**
     * 创建Socket pair
     *
     * @return array{server: resource, client: resource}
     */
    private function createSocketPair(): array
    {
        $pair = RealWorkerTestHelper::createSocketPair();
        $this->socketPairs[] = $pair;

        return $pair;
    }

    /**
     * 设置Worker属性
     *
     * @param Worker $worker   Worker实例
     * @param string $property 属性名
     * @param mixed  $value    属性值
     */
    private function setWorkerProperty(Worker $worker, string $property, $value): void
    {
        RealWorkerTestHelper::setWorkerProperty($worker, $property, $value);
    }

    /**
     * 注册Worker事件到事件循环
     *
     * @param Worker $worker     Worker实例
     * @param array<string, resource>  $socketPair Socket pair
     */
    private function registerWorkerEvents(Worker $worker, array $socketPair): void
    {
        // 这里可以添加更复杂的事件注册逻辑
        // 目前我们主要依赖手动触发连接事件
    }

    /**
     * 创建真实的客户端连接
     *
     * @param Worker $worker        目标Worker
     * @param string $remoteAddress 客户端地址
     */
    protected function createRealConnection(Worker $worker, string $remoteAddress = '127.0.0.1:12345'): TcpConnection
    {
        // 获取Worker的socket pair
        $workerSocket = $this->getWorkerSocket($worker);
        if (null === $workerSocket) {
            throw new WorkerException('Worker must be started before creating connections');
        }

        // 创建客户端socket pair
        $clientSocketPair = $this->createSocketPair();

        // 创建可测试的TcpConnection
        $connection = new TestableTcpConnection($this->eventLoop, $clientSocketPair['client'], $remoteAddress);

        // 设置连接属性
        $connection->worker = $worker;
        if (is_string($worker->protocol) && class_exists($worker->protocol)) {
            /** @var class-string $protocol */
            $protocol = $worker->protocol;
            $connection->protocol = $protocol;
        } else {
            $connection->protocol = null;
        }

        // 复制Worker的回调到连接
        $connection->onMessage = $worker->onMessage;
        $connection->onClose = $worker->onClose;
        $connection->onError = $worker->onError;
        $connection->onBufferFull = $worker->onBufferFull;
        $connection->onBufferDrain = $worker->onBufferDrain;

        // 设置连接状态
        $this->setConnectionProperty($connection, 'status', TcpConnection::STATUS_ESTABLISHED);

        // 添加到Worker的连接列表
        if (!isset($worker->connections)) {
            $worker->connections = [];
        }
        $worker->connections[$connection->id] = $connection;

        // 记录连接
        $this->connections[] = $connection;

        // 触发onConnect回调
        if (null !== $worker->onConnect) {
            ($worker->onConnect)($connection);
        }

        return $connection;
    }

    /**
     * 获取Worker的socket
     *
     * @param Worker $worker Worker实例
     *
     * @return mixed
     */
    private function getWorkerSocket(Worker $worker)
    {
        return $this->getWorkerProperty($worker, 'mainSocket');
    }

    /**
     * 获取Worker属性
     *
     * @param Worker $worker   Worker实例
     * @param string $property 属性名
     */
    private function getWorkerProperty(Worker $worker, string $property): mixed
    {
        return RealWorkerTestHelper::getWorkerProperty($worker, $property);
    }

    /**
     * 设置连接属性
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $property   属性名
     * @param mixed         $value      属性值
     */
    private function setConnectionProperty(TcpConnection $connection, string $property, $value): void
    {
        RealWorkerTestHelper::setConnectionProperty($connection, $property, $value);
    }

    /**
     * 向Worker发送真实数据
     *
     * @param Worker        $worker     目标Worker
     * @param TcpConnection $connection 连接实例
     * @param string        $data       数据内容
     */
    protected function sendRealData(Worker $worker, TcpConnection $connection, string $data): void
    {
        // 如果有协议，先处理协议编码
        if (null !== $connection->protocol && method_exists($connection->protocol, 'encode')) {
            $data = $connection->protocol::encode($data, $connection);
        }

        // 模拟接收数据的过程
        $this->simulateDataReceive($connection, $data);
    }

    /**
     * 模拟数据接收
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $data       数据内容
     */
    private function simulateDataReceive(TcpConnection $connection, string $data): void
    {
        $processedData = $this->processProtocolData($connection, $data);
        if (null === $processedData) {
            return; // 数据不完整或处理失败
        }

        $this->triggerMessageCallback($connection, $processedData);
    }

    /**
     * 处理协议数据
     */
    private function processProtocolData(TcpConnection $connection, string $data): ?string
    {
        return RealWorkerTestHelper::processProtocolData($connection, $data);
    }

    /**
     * 触发消息回调
     */
    private function triggerMessageCallback(TcpConnection $connection, string $data): void
    {
        if (null === $connection->onMessage) {
            return;
        }

        try {
            ($connection->onMessage)($connection, $data);
        } catch (\Throwable $e) {
            $this->handleConnectionError($connection, $e);
        }
    }

    /**
     * 处理连接错误
     *
     * @param TcpConnection $connection 连接实例
     * @param \Throwable    $error      错误
     */
    private function handleConnectionError(TcpConnection $connection, \Throwable $error): void
    {
        if (null !== $connection->onError) {
            try {
                ($connection->onError)($connection, $error->getCode(), $error->getMessage());
            } catch (\Throwable $e) {
                // 忽略错误处理中的异常
            }
        }

        // 如果没有错误处理器，重新抛出异常
        if (null === $connection->onError) {
            throw $error;
        }
    }

    /**
     * 断言连接发送了指定数据
     *
     * @param TcpConnection $connection   连接实例
     * @param string        $expectedData 期望的数据
     * @param string        $message      断言消息
     */
    protected function assertConnectionSentData(TcpConnection $connection, string $expectedData, string $message = ''): void
    {
        $sentData = $this->getSentData($connection);
        $allData = implode('', $sentData);

        $this->assertStringContainsString($expectedData, $allData, '' !== $message ? $message : '连接应该发送指定的数据');
    }

    /**
     * 获取连接发送的数据
     *
     * @param TcpConnection $connection 连接实例
     *
     * @return array<int, string>
     */
    protected function getSentData(TcpConnection $connection): array
    {
        // 如果是 TestableTcpConnection，直接读取其 _sentData 属性
        if ($connection instanceof TestableTcpConnection) {
            /** @var array<int, string> */
            return $connection->_sentData;
        }

        // 对于其他连接类型，尝试通过反射获取
        $reflection = new \ReflectionClass($connection);
        if ($reflection->hasProperty('_sentData')) {
            $prop = $reflection->getProperty('_sentData');
            $prop->setAccessible(true);
            $sentData = $prop->getValue($connection);

            /** @var array<int, string> */
            return is_array($sentData) ? $sentData : [];
        }

        return [];
    }

    /**
     * 等待条件满足
     *
     * @param callable $condition 条件函数
     * @param float    $timeout   超时时间
     * @param float    $interval  检查间隔
     */
    protected function waitFor(callable $condition, float $timeout = 5.0, float $interval = 0.01): bool
    {
        $startTime = $this->getCurrentTime();

        while ($this->getCurrentTime() - $startTime < $timeout) {
            if ($condition()) {
                return true;
            }

            $this->fastForward($interval);
            $this->runEventLoop(1);
        }

        return false;
    }

    /**
     * 获取当前时间
     */
    protected function getCurrentTime(): float
    {
        return $this->eventLoop->getCurrentTime();
    }

    /**
     * 快进时间
     *
     * @param float $seconds 秒数
     */
    protected function fastForward(float $seconds): void
    {
        $this->eventLoop->fastForward($seconds);
    }

    /**
     * 运行事件循环（同步执行）
     *
     * @param int $iterations 迭代次数，0表示运行直到没有事件
     */
    protected function runEventLoop(int $iterations = 1): void
    {
        for ($i = 0; $i < $iterations || 0 === $iterations; ++$i) {
            if (!$this->eventLoop->hasEvents()) {
                break;
            }
            $this->eventLoop->tick();
        }
    }
}
