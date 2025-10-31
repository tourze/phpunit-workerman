<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\RealWorkerTestCase;

/**
 * 简单的真实Worker测试
 *
 * @internal
 */
#[CoversClass(RealWorkerTestCase::class)]
final class SimpleRealWorkerTest extends RealWorkerTestCase
{
    public function testBasicWorkerCreation(): void
    {
        $worker = $this->createRealWorker('tcp://127.0.0.1:8080');

        // 验证Worker创建成功
        $this->assertNotNull($worker);
        $this->assertEquals('tcp://127.0.0.1:8080', $worker->getSocketName());
    }

    public function testWorkerStartup(): void
    {
        $worker = $this->createRealWorker('tcp://127.0.0.1:8080');

        $startupCalled = false;
        $worker->onWorkerStart = function ($w) use (&$startupCalled): void {
            $startupCalled = true;
        };

        $this->startRealWorker($worker);

        $this->assertTrue($startupCalled, 'onWorkerStart should be called');
    }

    public function testSimpleEcho(): void
    {
        $worker = $this->createRealWorker('tcp://127.0.0.1:8080');

        $worker->onMessage = function ($connection, $data): void {
            $connection->send('Echo: ' . $data);
        };

        $this->startRealWorker($worker);

        $connection = $this->createRealConnection($worker, '127.0.0.1:12345');
        $this->sendRealData($worker, $connection, 'Hello World');

        $sentData = $this->getSentData($connection);
        $this->assertNotEmpty($sentData, 'Connection should send data');

        $response = implode('', $sentData);
        $this->assertStringContainsString('Echo: Hello World', $response);
    }
}
