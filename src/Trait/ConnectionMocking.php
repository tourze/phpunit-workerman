<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Trait;

use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Tourze\PHPUnitWorkerman\Mock\MockTcpConnection;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * 连接模拟 Trait
 *
 * 提供 Workerman 连接的模拟和测试功能
 */
trait ConnectionMocking
{
    /** @var array<string, MockTcpConnection> 模拟连接缓存 */
    private array $mockConnections = [];

    /**
     * 获取事件循环实例
     * 这个方法应该在使用此trait的类中实现
     */
    abstract protected function getMockEventLoop();

    /**
     * 模拟连接发送数据
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $data       数据内容
     */
    protected function mockConnectionSend(TcpConnection $connection, string $data): bool
    {
        // 先模拟发送过程，将数据放入发送缓冲区
        $sendBuffer = $this->getConnectionProperty($connection, 'sendBuffer');
        $sendBufferStr = is_string($sendBuffer) ? $sendBuffer : '';
        $this->setConnectionProperty($connection, 'sendBuffer', $sendBufferStr . $data);

        // 如果是 MockTcpConnection，记录发送的数据
        if ($connection instanceof MockTcpConnection) {
            $connection->_sentData[] = $data;
        } else {
            // 记录发送的数据（用于断言）
            $this->recordConnectionSentData($connection, $data);
        }

        // 触发缓冲区检查（这会清空缓冲区并可能触发回调）
        $this->checkConnectionBuffer($connection);

        return true;
    }

    /**
     * 获取连接的私有属性
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $property   属性名
     */
    protected function getConnectionProperty(TcpConnection $connection, string $property): mixed
    {
        $reflection = new \ReflectionClass($connection);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);

            return $prop->getValue($connection);
        }

        return null;
    }

    /**
     * 设置连接的私有属性
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $property   属性名
     * @param mixed         $value      属性值
     */
    protected function setConnectionProperty(TcpConnection $connection, string $property, $value): void
    {
        $reflection = new \ReflectionClass($connection);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($connection, $value);
        }
    }

    /**
     * 检查连接缓冲区状态
     *
     * @param TcpConnection $connection 连接实例
     */
    protected function checkConnectionBuffer(TcpConnection $connection): void
    {
        $sendBuffer = $this->getConnectionProperty($connection, 'sendBuffer');
        $sendBufferStr = is_string($sendBuffer) ? $sendBuffer : '';

        $maxSendBufferSize = $this->getConnectionProperty($connection, 'maxSendBufferSize');
        $maxSendBufferSizeInt = is_int($maxSendBufferSize) ? $maxSendBufferSize : 1048576;

        if ($this->isBufferFull($sendBufferStr, $maxSendBufferSizeInt)) {
            $this->handleBufferFull($connection);
        } else {
            $this->handleNormalBuffer($connection, $sendBufferStr);
        }
    }

    /**
     * 判断缓冲区是否已满
     */
    private function isBufferFull(string $sendBuffer, int $maxSendBufferSize): bool
    {
        return strlen($sendBuffer) >= $maxSendBufferSize;
    }

    /**
     * 处理缓冲区已满的情况
     */
    private function handleBufferFull(TcpConnection $connection): void
    {
        // 缓冲区满
        if (null !== $connection->onBufferFull) {
            ($connection->onBufferFull)($connection);
        }

        $this->clearBufferAndDrain($connection);
    }

    /**
     * 处理正常缓冲区
     */
    private function handleNormalBuffer(TcpConnection $connection, string $sendBuffer): void
    {
        if ('' !== $sendBuffer) {
            $this->clearBufferAndDrain($connection);
        }
    }

    /**
     * 清空缓冲区并触发排空回调
     */
    private function clearBufferAndDrain(TcpConnection $connection): void
    {
        // 模拟发送完成，清空缓冲区
        $this->setConnectionProperty($connection, 'sendBuffer', '');

        // 缓冲区排空
        if (null !== $connection->onBufferDrain) {
            ($connection->onBufferDrain)($connection);
        }
    }

    /**
     * 模拟连接接收数据
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $data       接收的数据
     */
    protected function mockConnectionReceive(TcpConnection $connection, string $data): void
    {
        $processedData = $this->processIncomingData($connection, $data);
        if (null === $processedData) {
            return; // 数据不完整，等待更多数据
        }

        $this->triggerOnMessage($connection, $processedData);
    }

    /**
     * 处理接收到的数据
     */
    private function processIncomingData(TcpConnection $connection, string $data): ?string
    {
        if (null === $connection->protocol || !method_exists($connection->protocol, 'input')) {
            return $data;
        }

        $length = $connection->protocol::input($data, $connection);
        if (0 === $length) {
            return null; // 数据不完整
        }

        if ($length > 0 && method_exists($connection->protocol, 'decode')) {
            $decoded = $connection->protocol::decode($data, $connection);

            // 确保返回值是 string 或 null
            return is_string($decoded) ? $decoded : null;
        }

        return $data;
    }

    /**
     * 触发 onMessage 回调
     */
    private function triggerOnMessage(TcpConnection $connection, string $data): void
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
     * 模拟连接错误
     *
     * @param TcpConnection $connection 连接实例
     * @param \Throwable    $error      错误对象
     */
    protected function handleConnectionError(TcpConnection $connection, \Throwable $error): void
    {
        // 触发 onError 回调
        if (null !== $connection->onError) {
            try {
                ($connection->onError)($connection, $error->getCode(), $error->getMessage());
            } catch (\Throwable $e) {
                // 忽略错误处理中的异常
            }
        }

        // 严重错误时关闭连接
        $this->mockConnectionClose($connection, false);
    }

    /**
     * 模拟连接关闭
     *
     * @param TcpConnection $connection      连接实例
     * @param bool          $triggerCallback 是否触发 onClose 回调
     */
    protected function mockConnectionClose(TcpConnection $connection, bool $triggerCallback = true): void
    {
        // 设置连接状态
        $this->setConnectionProperty($connection, 'status', TcpConnection::STATUS_CLOSING);

        // 触发 onClose 回调
        if ($triggerCallback && null !== $connection->onClose) {
            try {
                ($connection->onClose)($connection);
            } catch (\Throwable $e) {
                // 忽略关闭过程中的异常
            }
        }

        // 最终状态
        $this->setConnectionProperty($connection, 'status', TcpConnection::STATUS_CLOSED);

        // 从 Worker 的连接列表中移除
        if (null !== $connection->worker && isset($connection->worker->connections)) {
            unset($connection->worker->connections[$connection->id]);
        }

        // 关闭 socket
        $socket = $this->getConnectionProperty($connection, 'socket');
        if (is_resource($socket)) {
            @fclose($socket);
        }

        // 从缓存中移除
        $remoteAddress = $connection->getRemoteAddress();
        unset($this->mockConnections[$remoteAddress]);
    }

    /**
     * 断言连接状态
     *
     * @param TcpConnection $connection     连接实例
     * @param int           $expectedStatus 期望的状态
     * @param string        $message        断言消息
     */
    protected function assertConnectionStatus(
        TcpConnection $connection,
        int $expectedStatus,
        string $message = '',
    ): void {
        $actualStatus = $this->getConnectionProperty($connection, 'status');
        if (!is_int($actualStatus)) {
            throw new \RuntimeException('Connection status must be an integer');
        }

        $statusNames = [
            TcpConnection::STATUS_INITIAL => 'INITIAL',
            TcpConnection::STATUS_CONNECTING => 'CONNECTING',
            TcpConnection::STATUS_ESTABLISHED => 'ESTABLISHED',
            TcpConnection::STATUS_CLOSING => 'CLOSING',
            TcpConnection::STATUS_CLOSED => 'CLOSED',
        ];

        $expectedName = $statusNames[$expectedStatus] ?? (string) $expectedStatus;
        $actualName = $statusNames[$actualStatus] ?? (string) $actualStatus;

        $this->assertEquals(
            $expectedStatus,
            $actualStatus,
            '' !== $message ? $message : "连接状态应该是 {$expectedName}，实际是 {$actualName}"
        );
    }

    /**
     * 断言连接发送了指定数据
     *
     * @param TcpConnection $connection   连接实例
     * @param string        $expectedData 期望的数据
     * @param string        $message      断言消息
     */
    protected function assertConnectionSent(
        TcpConnection $connection,
        string $expectedData,
        string $message = '',
    ): void {
        // 从 send 方法调用中获取记录的数据
        if ($connection instanceof MockTcpConnection) {
            $sentData = $connection->getSentData();
        } else {
            // 使用反射获取发送缓冲区或其他存储的数据
            $sentData = $this->getConnectionSentData($connection);
        }

        $allSentData = implode('', $sentData);

        $this->assertStringContainsString(
            $expectedData,
            $allSentData,
            '' !== $message ? $message : '连接应该发送了指定的数据'
        );
    }

    /**
     * 记录连接发送的数据
     */
    private function recordConnectionSentData(TcpConnection $connection, string $data): void
    {
        /** @var array<int, array<int, string>> $sentDataMap */
        static $sentDataMap = [];
        $connId = spl_object_id($connection);
        if (!isset($sentDataMap[$connId])) {
            $sentDataMap[$connId] = [];
        }
        $sentDataMap[$connId][] = $data;
    }

    /**
     * 获取连接发送的数据
     *
     * @return array<int, string>
     */
    private function getConnectionSentData(TcpConnection $connection): array
    {
        /** @var array<int, array<int, string>> $sentDataMap */
        static $sentDataMap = [];
        $connId = spl_object_id($connection);

        return $sentDataMap[$connId] ?? [];
    }

    /**
     * 断言连接缓冲区为空
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $message    断言消息
     */
    protected function assertConnectionBufferEmpty(
        TcpConnection $connection,
        string $message = '',
    ): void {
        $sendBuffer = $this->getConnectionProperty($connection, 'sendBuffer') ?? '';

        $this->assertEmpty(
            $sendBuffer,
            '' !== $message ? $message : '连接发送缓冲区应该为空'
        );
    }

    /**
     * 清理所有模拟连接
     */
    protected function cleanupMockConnections(): void
    {
        foreach ($this->mockConnections as $connection) {
            $this->mockConnectionClose($connection, false);
        }

        $this->mockConnections = [];
    }

    /**
     * 模拟大量并发连接
     *
     * @param Worker $worker      Worker 实例
     * @param int    $count       连接数量
     * @param string $baseAddress 基础地址模板
     *
     * @return array<MockTcpConnection> 创建的连接数组
     */
    protected function createMassConnections(
        Worker $worker,
        int $count,
        string $baseAddress = '127.0.0.1:1000',
    ): array {
        $connections = [];

        for ($i = 0; $i < $count; ++$i) {
            $address = str_replace('1000', (string) (1000 + $i), $baseAddress);
            $connection = $this->createMockConnection($worker, $address);
            $connections[] = $connection;

            // 触发 onConnect
            if (null !== $worker->onConnect) {
                ($worker->onConnect)($connection);
            }
        }

        return $connections;
    }

    /**
     * 创建模拟连接
     *
     * @param Worker|null   $worker        关联的 Worker
     * @param string        $remoteAddress 远程地址
     * @param resource|null $socket        Socket 资源
     */
    protected function createMockConnection(
        ?Worker $worker = null,
        string $remoteAddress = '127.0.0.1:12345',
        $socket = null,
    ): MockTcpConnection {
        $cacheKey = $remoteAddress;

        if (isset($this->mockConnections[$cacheKey])) {
            $connection = $this->mockConnections[$cacheKey];

            // 更新连接的回调，以防 Worker 的回调在连接创建后被修改
            $this->applyWorkerToConnection($connection, $worker);

            return $connection;
        }

        // 创建模拟 socket（如未提供）
        $socket = $this->createSocketIfNeeded($socket);

        // 创建连接实例，需要传递事件循环作为第一个参数
        $connection = new MockTcpConnection($this->getMockEventLoop(), $socket, $remoteAddress);

        // 设置关联的 Worker
        $this->applyWorkerToConnection($connection, $worker);

        // 模拟连接状态
        $this->setConnectionProperty($connection, 'status', TcpConnection::STATUS_ESTABLISHED);

        // 初始化发送缓冲区
        $this->setConnectionProperty($connection, 'sendBuffer', '');

        // 缓存连接
        $this->mockConnections[$cacheKey] = $connection;

        return $connection;
    }

    /**
     * 为连接应用 Worker 的相关属性和回调
     */
    private function applyWorkerToConnection(MockTcpConnection $connection, ?Worker $worker): void
    {
        if (null === $worker) {
            return;
        }

        $connection->worker = $worker;
        if (is_string($worker->protocol) && class_exists($worker->protocol)) {
            /** @var class-string $protocol */
            $protocol = $worker->protocol;
            $connection->protocol = $protocol;
        } else {
            $connection->protocol = null;
        }

        // 复制回调
        $connection->onMessage = $worker->onMessage;
        $connection->onClose = $worker->onClose;
        $connection->onError = $worker->onError;
        $connection->onBufferFull = $worker->onBufferFull;
        $connection->onBufferDrain = $worker->onBufferDrain;
    }

    /**
     * 如果未提供 socket，则创建一个新的 socket 资源
     *
     * @param resource|null $socket
     * @return resource
     */
    private function createSocketIfNeeded($socket)
    {
        if (null !== $socket) {
            return $socket;
        }

        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (false === $socketPair) {
            throw new SocketException('无法创建 socket 对');
        }

        return $socketPair[0];
    }
}
