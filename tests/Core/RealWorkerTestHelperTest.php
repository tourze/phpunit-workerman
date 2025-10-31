<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\RealWorkerTestHelper;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Tourze\PHPUnitWorkerman\Mock\TestableTcpConnection;
use Workerman\Worker;

/**
 * @internal
 */
#[CoversClass(RealWorkerTestHelper::class)]
#[RunTestsInSeparateProcesses] final class RealWorkerTestHelperTest extends WorkermanTestCase
{
    public function testCreateSocketPair(): void
    {
        $pair = RealWorkerTestHelper::createSocketPair();

        $this->assertArrayHasKey('server', $pair);
        $this->assertArrayHasKey('client', $pair);
        $this->assertIsResource($pair['server']);
        $this->assertIsResource($pair['client']);

        // 清理资源
        fclose($pair['server']);
        fclose($pair['client']);
    }

    public function testWorkerPropertyOperations(): void
    {
        $worker = new Worker();

        // 测试设置属性
        RealWorkerTestHelper::setWorkerProperty($worker, 'count', 5);

        // 测试获取属性
        $count = RealWorkerTestHelper::getWorkerProperty($worker, 'count');
        $this->assertEquals(5, $count);

        // 测试不存在的属性
        $nonExistent = RealWorkerTestHelper::getWorkerProperty($worker, 'nonExistentProperty');
        $this->assertNull($nonExistent);
    }

    public function testConnectionPropertyOperations(): void
    {
        $eventLoop = new MockEventLoop();
        $socketPair = RealWorkerTestHelper::createSocketPair();
        $connection = new TestableTcpConnection($eventLoop, $socketPair['client'], '127.0.0.1:8080');

        // 测试设置属性
        RealWorkerTestHelper::setConnectionProperty($connection, 'protocol', 'http');

        // 验证属性设置成功
        $this->assertEquals('http', $connection->protocol);

        // 清理资源
        fclose($socketPair['server']);
        fclose($socketPair['client']);
    }

    public function testProcessProtocolDataWithoutProtocol(): void
    {
        $eventLoop = new MockEventLoop();
        $socketPair = RealWorkerTestHelper::createSocketPair();
        $connection = new TestableTcpConnection($eventLoop, $socketPair['client'], '127.0.0.1:8080');
        $data = 'test data';

        $result = RealWorkerTestHelper::processProtocolData($connection, $data);

        $this->assertEquals($data, $result);

        // 清理资源
        fclose($socketPair['server']);
        fclose($socketPair['client']);
    }

    public function testProcessProtocolDataWithProtocol(): void
    {
        $eventLoop = new MockEventLoop();
        $socketPair = RealWorkerTestHelper::createSocketPair();
        $connection = new TestableTcpConnection($eventLoop, $socketPair['client'], '127.0.0.1:8080');

        // 直接测试协议为null的情况
        $connection->protocol = null;
        $data = 'test data';

        $result = RealWorkerTestHelper::processProtocolData($connection, $data);

        // 协议为null时，应该返回原始数据
        $this->assertEquals($data, $result);

        // 清理资源
        fclose($socketPair['server']);
        fclose($socketPair['client']);
    }

    public function testProcessProtocolDataWithNullProtocol(): void
    {
        $eventLoop = new MockEventLoop();
        $socketPair = RealWorkerTestHelper::createSocketPair();
        $connection = new TestableTcpConnection($eventLoop, $socketPair['client'], '127.0.0.1:8080');

        // 测试协议为null的情况
        $connection->protocol = null;
        $data = 'test data';

        $result = RealWorkerTestHelper::processProtocolData($connection, $data);

        // 没有协议时，返回原始数据
        $this->assertEquals($data, $result);

        // 清理资源
        fclose($socketPair['server']);
        fclose($socketPair['client']);
    }
}
