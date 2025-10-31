<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Utility;

use Workerman\Connection\ConnectionInterface;

/**
 * 连接数据存储工具
 *
 * 用于存储连接相关的上下文数据，避免直接向连接对象写入动态属性
 */
class ConnectionDataStorage
{
    /** @var array<int, array<string, mixed>> */
    private static array $storage = [];

    /**
     * 设置连接数据
     */
    public static function set(ConnectionInterface $connection, string $key, mixed $value): void
    {
        $connectionId = spl_object_id($connection);
        self::$storage[$connectionId][$key] = $value;
    }

    /**
     * 获取连接数据
     */
    public static function get(ConnectionInterface $connection, string $key, mixed $default = null): mixed
    {
        $connectionId = spl_object_id($connection);

        return self::$storage[$connectionId][$key] ?? $default;
    }

    /**
     * 删除连接数据
     */
    public static function remove(ConnectionInterface $connection, string $key): void
    {
        $connectionId = spl_object_id($connection);
        unset(self::$storage[$connectionId][$key]);
    }

    /**
     * 清理连接数据
     */
    public static function clear(ConnectionInterface $connection): void
    {
        $connectionId = spl_object_id($connection);
        unset(self::$storage[$connectionId]);
    }
}
