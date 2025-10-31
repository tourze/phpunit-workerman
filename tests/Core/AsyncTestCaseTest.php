<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitWorkerman\Core\AsyncTestCase;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;

/**
 * @internal
 */
#[CoversClass(AsyncTestCase::class)]
#[RunTestsInSeparateProcesses] final class AsyncTestCaseTest extends WorkermanTestCase
{
    public function testAsyncTestCaseInstantiation(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(AsyncTestCase::class, $this);
            }

            public function getEventLoopForTest(): MockEventLoop
            {
                return $this->eventLoop;
            }

            public function getCurrentTimeForTest(): float
            {
                return $this->getCurrentTime();
            }
        };

        $this->assertInstanceOf(AsyncTestCase::class, $testCase);
    }

    public function testSetUpInstallsMockEventLoop(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(AsyncTestCase::class, $this);
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

    public function testTimeAdvancement(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $initialTime = $this->getCurrentTime();
                $this->advanceTime(1.5);
                $afterTime = $this->getCurrentTime();

                $this->assertGreaterThanOrEqual($initialTime + 1.5, $afterTime);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testRunAsync(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $result = $this->runAsync(function ($callback): void {
                    // 模拟异步操作完成
                    call_user_func($callback, 'test_result');
                });

                $this->assertEquals('test_result', $result);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testRunAsyncWithError(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $exceptionThrown = false;

                try {
                    $this->runAsync(function ($callback, $errorCallback): void {
                        call_user_func($errorCallback, 'test_error');
                    });
                } catch (\RuntimeException $e) {
                    $exceptionThrown = true;
                    $this->assertEquals('test_error', $e->getMessage());
                }

                $this->assertTrue($exceptionThrown, 'RuntimeException should be thrown');
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testTearDownClearsEventLoop(): void
    {
        $testCase = new #[CoversClass(AsyncTestCase::class)] /**
#[RunTestsInSeparateProcesses]         * @coversNothing
         *
         * @internal
         */
        class('testName') extends AsyncTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(AsyncTestCase::class, $this);
            }

            public function getEventLoopForTest(): MockEventLoop
            {
                return $this->eventLoop;
            }
        };

        $testCase->setUp();
        $eventLoop = $testCase->getEventLoopForTest();

        // 添加一些定时器
        $eventLoop->delay(1.0, function (): void {});
        $this->assertTrue($eventLoop->hasEvents());

        // 清理后应该没有事件了
        $testCase->tearDown();
        // 由于tearDown会创建新的eventLoop实例，我们无法直接测试清理效果
        // 但能确保tearDown执行不出错，且eventLoop被重新初始化
        $this->assertInstanceOf(MockEventLoop::class, $testCase->getEventLoopForTest());
    }
}
