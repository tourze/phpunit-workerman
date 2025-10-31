<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Workerman\Worker;

/**
 * @internal
 */
#[CoversClass(WorkermanTestCase::class)]
#[RunTestsInSeparateProcesses] final class WorkermanTestCaseTest extends WorkermanTestCase
{
    public function testWorkermanTestCaseInstantiation(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(WorkermanTestCase::class, $this);
            }

            public function getMockEventLoopForTest(): MockEventLoop
            {
                if (null === $this->mockEventLoop) {
                    throw new \RuntimeException('MockEventLoop not initialized');
                }
                return $this->mockEventLoop;
            }
        };

        $this->assertInstanceOf(WorkermanTestCase::class, $testCase);
    }

    public function testSetUpInstallsMockEventLoop(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(WorkermanTestCase::class, $this);
            }

            public function getMockEventLoopForTest(): MockEventLoop
            {
                if (null === $this->mockEventLoop) {
                    throw new \RuntimeException('MockEventLoop not initialized');
                }
                return $this->mockEventLoop;
            }
        };

        $testCase->setUp();
        $eventLoop = $testCase->getMockEventLoopForTest();

        $this->assertInstanceOf(MockEventLoop::class, $eventLoop);
    }

    public function testCreateWorker(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker('tcp://127.0.0.1:8080');

                $this->assertInstanceOf(Worker::class, $worker);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testStartAndStopWorker(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker();
                $started = false;
                $stopped = false;

                $worker->onWorkerStart = function () use (&$started): void {
                    $started = true;
                };

                $worker->onWorkerStop = function () use (&$stopped): void {
                    $stopped = true;
                };

                $this->startWorker($worker);
                $this->assertTrue($started);

                $this->stopWorker($worker);
                $this->assertTrue($stopped);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testSendDataToWorker(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker();
                $receivedData = null;

                $worker->onMessage = function ($connection, $data) use (&$receivedData): void {
                    $receivedData = $data;
                };

                $this->sendDataToWorker($worker, 'test_message');

                $this->assertEquals('test_message', $receivedData);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testCloseConnection(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker();
                $connectionClosed = false;

                $worker->onClose = function ($connection) use (&$connectionClosed): void {
                    $connectionClosed = true;
                };

                $this->closeConnection($worker);

                $this->assertTrue($connectionClosed);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testAssertCallbackTriggered(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $callback = function ($data) {
                    return $data;
                };

                $trigger = function ($wrappedCallback): void {
                    call_user_func($wrappedCallback, 'test');
                };

                $this->assertCallbackTriggered($callback, $trigger);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testWaitFor(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $completed = false;

                // 添加一个定时器
                $this->getMockEventLoop()->delay(0.1, function () use (&$completed): void {
                    $completed = true;
                });

                $this->waitFor(function () use (&$completed) {
                    return $completed;
                }, 1.0);

                $this->assertTrue($completed);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testGetAndSetWorkerProperty(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker();

                // 测试设置和获取属性
                $this->setWorkerProperty($worker, 'name', 'test_worker');
                $name = $this->getWorkerProperty($worker, 'name');

                $this->assertEquals('test_worker', $name);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testRunEventLoop(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $executed = false;

                $this->getMockEventLoop()->delay(0, function () use (&$executed): void {
                    $executed = true;
                });

                $this->runEventLoop();

                $this->assertTrue($executed);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
        $testCase->tearDown();
    }

    public function testTearDownCleansUpWorkers(): void
    {
        $testCase = new #[CoversClass(WorkermanTestCase::class)] #[RunTestsInSeparateProcesses] /**
         * @coversNothing
         *
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $worker = $this->createWorker();
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
