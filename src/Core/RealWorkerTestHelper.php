<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Core;

use Tourze\PHPUnitWorkerman\Exception\SocketException;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

/**
 * RealWorkerTestCase 辅助类
 *
 * 分离复杂的 Worker 和连接操作逻辑
 */
class RealWorkerTestHelper
{
    /**
     * 创建Socket pair
     *
     * @return array{server: resource, client: resource}
     */
    public static function createSocketPair(): array
    {
        $socketPair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if (false === $socketPair) {
            throw new SocketException('Failed to create socket pair');
        }

        // 设置为非阻塞模式
        stream_set_blocking($socketPair[0], false);
        stream_set_blocking($socketPair[1], false);

        return [
            'server' => $socketPair[0],
            'client' => $socketPair[1],
        ];
    }

    /**
     * 设置Worker属性
     *
     * @param Worker $worker   Worker实例
     * @param string $property 属性名
     * @param mixed  $value    属性值
     */
    public static function setWorkerProperty(Worker $worker, string $property, $value): void
    {
        $reflection = new \ReflectionClass($worker);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($worker, $value);
        }
    }

    /**
     * 获取Worker属性
     *
     * @param Worker $worker   Worker实例
     * @param string $property 属性名
     */
    public static function getWorkerProperty(Worker $worker, string $property): mixed
    {
        $reflection = new \ReflectionClass($worker);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);

            return $prop->getValue($worker);
        }

        return null;
    }

    /**
     * 设置连接属性
     *
     * @param TcpConnection $connection 连接实例
     * @param string        $property   属性名
     * @param mixed         $value      属性值
     */
    public static function setConnectionProperty(TcpConnection $connection, string $property, $value): void
    {
        $reflection = new \ReflectionClass($connection);

        if ($reflection->hasProperty($property)) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue($connection, $value);
        }
    }

    /**
     * 处理协议数据
     */
    public static function processProtocolData(TcpConnection $connection, string $data): ?string
    {
        if (null === $connection->protocol) {
            return $data;
        }

        if (!method_exists($connection->protocol, 'input')) {
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
}
