<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\RealWorkerTestCase;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Workerman\Worker;

/**
 * @internal
 */
#[CoversClass(RealWorkerTestCase::class)]
#[RunTestsInSeparateProcesses] final class RealWorkerTestCaseTest extends WorkermanTestCase
{
    public function testRealWorkerTestCaseInstantiation(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(RealWorkerTestCase::class, $this);
            }

            public function getEventLoopForTest(): MockEventLoop
            {
                return $this->eventLoop;
            }
        };

        $this->assertInstanceOf(RealWorkerTestCase::class, $testCase);
    }

    public function testSetUpInstallsEventLoop(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(RealWorkerTestCase::class, $this);
            }

            public function getEventLoopForTest(): MockEventLoop
            {
                return $this->eventLoop;
            }
        };

        $testCase->setUp();
        $eventLoop = $testCase->getEventLoopForTest();

        $this->assertInstanceOf(MockEventLoop::class, $eventLoop);
    }

    public function testCreateRealWorker(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $worker = $this->createRealWorker('tcp://127.0.0.1:8080');

                $this->assertInstanceOf(Worker::class, $worker);
                $this->assertEquals(1, $worker->count);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testStartRealWorker(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $worker = $this->createRealWorker();
                $started = false;

                $worker->onWorkerStart = function () use (&$started): void {
                    $started = true;
                };

                $this->startRealWorker($worker);

                $this->assertTrue($started);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testCreateRealConnection(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $worker = $this->createRealWorker();
                $this->startRealWorker($worker);

                $connection = $this->createRealConnection($worker);

                $this->assertInstanceOf(\Workerman\Connection\TcpConnection::class, $connection);
                $this->assertEquals($worker, $connection->worker);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testSendRealData(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $worker = $this->createRealWorker();
                $receivedData = null;

                $worker->onMessage = function ($connection, $data) use (&$receivedData): void {
                    $receivedData = $data;
                };

                $this->startRealWorker($worker);
                $connection = $this->createRealConnection($worker);

                $this->sendRealData($worker, $connection, 'test_message');

                $this->assertEquals('test_message', $receivedData);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testWaitFor(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $completed = false;

                // 模拟异步操作
                $this->getMockEventLoop()->delay(0.1, function () use (&$completed): void {
                    $completed = true;
                });

                $result = $this->waitFor(function () use (&$completed) {
                    return $completed;
                }, 1.0);

                $this->assertTrue($result);
                $this->assertTrue($completed);
            }

            public function getMockEventLoop(): MockEventLoop
            {
                return $this->eventLoop;
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testTearDownCleansUp(): void
    {
        $testCase = new #[CoversClass(RealWorkerTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends RealWorkerTestCase {
            public function testExample(): void
            {
                $worker = $this->createRealWorker();
                $this->startRealWorker($worker);

                $this->assertNotEmpty($this->workers);
            }

            /**
             * @return array<int, Worker>
             */
            public function getWorkersForTest(): array
            {
                return $this->workers;
            }
        };

        $testCase->setUp();
        $testCase->testExample();

        $this->assertNotEmpty($testCase->getWorkersForTest());

        $testCase->tearDown();

        // tearDown后workers应该被清空
        $this->assertEmpty($testCase->getWorkersForTest());
    }
}
