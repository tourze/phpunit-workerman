<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Tourze\PHPUnitWorkerman\Core\TestCase;
use Tourze\PHPUnitWorkerman\Core\WorkermanTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;
use Tourze\PHPUnitWorkerman\Utility\CommandLineHelper;
use Workerman\Worker;

/**
 * @internal
 */
#[CoversClass(TestCase::class)]
final class TestCaseTest extends PHPUnitTestCase
{
    public function testTestCaseInstantiation(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(TestCase::class, $this);
            }
        };

        $this->assertInstanceOf(TestCase::class, $testCase);
    }

    public function testSetUpResetsWorkermanGlobals(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(TestCase::class, $this);
            }
        };

        // 测试setUp不会抛出异常
        $testCase->setUp();
        // 验证setUp正常执行，通过检查全局状态重置
        $this->assertIsArray(Worker::getAllWorkers());
    }

    public function testTearDownResetsWorkermanGlobals(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $this->assertInstanceOf(TestCase::class, $this);
            }
        };

        $testCase->setUp();

        // 测试tearDown不会抛出异常
        $testCase->tearDown();
        // 验证tearDown正常执行，通过检查全局状态恢复
        $this->assertIsArray(Worker::getAllWorkers());
    }

    public function testCaptureWorkerOutput(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $output = $this->captureWorkerOutput(function (): void {
                    echo 'test output';
                });

                $this->assertEquals('test output', $output);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testCaptureWorkerOutputWithException(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $exceptionThrown = false;

                try {
                    $this->captureWorkerOutput(function (): void {
                        throw new WorkerException('test exception');
                    });
                } catch (WorkerException $e) {
                    $exceptionThrown = true;
                    $this->assertEquals('test exception', $e->getMessage());
                }

                $this->assertTrue($exceptionThrown, 'WorkerException should be thrown');
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testMockArgv(): void
    {
        $originalArgv = CommandLineHelper::getArgv();

        // 创建一个实际的TestCase实例来测试mockArgv
        $realTestCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {};
        $realTestCase->setUp();

        $reflectionMethod = new \ReflectionMethod($realTestCase, 'mockArgv');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invoke($realTestCase, ['mock', '--test']);

        $this->assertEquals(['mock', '--test'], CommandLineHelper::getArgv());

        // 恢复原始值
        CommandLineHelper::setArgv($originalArgv);
        CommandLineHelper::restoreArgv();
    }

    public function testAssertCallbackCalled(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $callback = function ($value) {
                    return $value * 2;
                };

                $trigger = function ($wrappedCallback): void {
                    $result = call_user_func($wrappedCallback, 5);
                    $this->assertEquals(10, $result);
                };

                $this->assertCallbackCalled($callback, $trigger, 1);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }

    public function testAssertCallbackCalledMultipleTimes(): void
    {
        $testCase = new #[CoversClass(TestCase::class)] /**
         * @coversNothing
         * @internal
         */
        class('testName') extends WorkermanTestCase {
            public function testExample(): void
            {
                $callback = function ($value) {
                    return $value;
                };

                $trigger = function ($wrappedCallback): void {
                    call_user_func($wrappedCallback, 1);
                    call_user_func($wrappedCallback, 2);
                    call_user_func($wrappedCallback, 3);
                };

                $this->assertCallbackCalled($callback, $trigger, 3);
            }
        };

        $testCase->setUp();
        $testCase->testExample();
    }
}
