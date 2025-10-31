<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Utility\ConnectionDataStorage;
use Workerman\Connection\ConnectionInterface;

/**
 * @internal
 */
#[CoversClass(ConnectionDataStorage::class)]
#[RunTestsInSeparateProcesses] final class ConnectionDataStorageTest extends WorkermanTestCase
{
    private ConnectionInterface $connection;

    private ConnectionInterface $connection2;

    protected function onSetUp(): void
    {
        // 创建测试连接（使用mock对象避免构造函数问题）
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->connection2 = $this->createMock(ConnectionInterface::class);
    }

    protected function onTearDown(): void
    {
        // 清理存储的数据
        ConnectionDataStorage::clear($this->connection);
        ConnectionDataStorage::clear($this->connection2);
    }

    public function testSetAndGetData(): void
    {
        ConnectionDataStorage::set($this->connection, 'test_key', 'test_value');

        $value = ConnectionDataStorage::get($this->connection, 'test_key');

        $this->assertEquals('test_value', $value);
    }

    public function testGetWithDefault(): void
    {
        $value = ConnectionDataStorage::get($this->connection, 'non_existent_key', 'default_value');

        $this->assertEquals('default_value', $value);
    }

    public function testGetWithoutDefault(): void
    {
        $value = ConnectionDataStorage::get($this->connection, 'non_existent_key');

        $this->assertNull($value);
    }

    public function testSetDifferentDataTypes(): void
    {
        ConnectionDataStorage::set($this->connection, 'string', 'string_value');
        ConnectionDataStorage::set($this->connection, 'integer', 123);
        ConnectionDataStorage::set($this->connection, 'array', ['a', 'b', 'c']);
        ConnectionDataStorage::set($this->connection, 'boolean', true);
        ConnectionDataStorage::set($this->connection, 'null', null);

        $this->assertEquals('string_value', ConnectionDataStorage::get($this->connection, 'string'));
        $this->assertEquals(123, ConnectionDataStorage::get($this->connection, 'integer'));
        $this->assertEquals(['a', 'b', 'c'], ConnectionDataStorage::get($this->connection, 'array'));
        $this->assertTrue(ConnectionDataStorage::get($this->connection, 'boolean'));
        $this->assertNull(ConnectionDataStorage::get($this->connection, 'null'));
    }

    public function testRemoveData(): void
    {
        ConnectionDataStorage::set($this->connection, 'test_key', 'test_value');

        $this->assertEquals('test_value', ConnectionDataStorage::get($this->connection, 'test_key'));

        ConnectionDataStorage::remove($this->connection, 'test_key');

        $this->assertNull(ConnectionDataStorage::get($this->connection, 'test_key'));
    }

    public function testClearData(): void
    {
        ConnectionDataStorage::set($this->connection, 'key1', 'value1');
        ConnectionDataStorage::set($this->connection, 'key2', 'value2');

        $this->assertEquals('value1', ConnectionDataStorage::get($this->connection, 'key1'));
        $this->assertEquals('value2', ConnectionDataStorage::get($this->connection, 'key2'));

        ConnectionDataStorage::clear($this->connection);

        $this->assertNull(ConnectionDataStorage::get($this->connection, 'key1'));
        $this->assertNull(ConnectionDataStorage::get($this->connection, 'key2'));
    }

    public function testDataIsolationBetweenConnections(): void
    {
        ConnectionDataStorage::set($this->connection, 'shared_key', 'value1');
        ConnectionDataStorage::set($this->connection2, 'shared_key', 'value2');

        $this->assertEquals('value1', ConnectionDataStorage::get($this->connection, 'shared_key'));
        $this->assertEquals('value2', ConnectionDataStorage::get($this->connection2, 'shared_key'));
    }

    public function testOverwriteExistingKey(): void
    {
        ConnectionDataStorage::set($this->connection, 'test_key', 'original_value');
        ConnectionDataStorage::set($this->connection, 'test_key', 'new_value');

        $this->assertEquals('new_value', ConnectionDataStorage::get($this->connection, 'test_key'));
    }

    public function testRemoveNonExistentKey(): void
    {
        // 移除不存在的键不应该抛出异常
        ConnectionDataStorage::remove($this->connection, 'non_existent_key');

        // 验证移除不存在的键后，该键仍然不存在
        $this->assertNull(ConnectionDataStorage::get($this->connection, 'non_existent_key'));
    }

    public function testClearEmptyConnection(): void
    {
        // 清理没有数据的连接不应该抛出异常
        ConnectionDataStorage::clear($this->connection);

        // 验证清理后数据仍然为空
        $this->assertNull(ConnectionDataStorage::get($this->connection, 'any_key'));
    }
}
