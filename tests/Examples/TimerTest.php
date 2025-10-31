<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\AsyncTestCase;
use Tourze\PHPUnitWorkerman\Exception\TimerException;
use Workerman\Timer;

/**
 * 定时器测试示例
 *
 * 演示如何测试 Workerman 的定时器功能
 *
 * @internal
 */
#[CoversClass(Timer::class)]
final class TimerTest extends AsyncTestCase
{
    public function testBasicTimer(): void
    {
        $executed = false;

        // 添加定时器
        Timer::add(1.0, function () use (&$executed): void {
            $executed = true;
        }, [], false);

        // 验证定时器未立即执行
        $this->assertFalse($executed, '定时器不应该立即执行');

        // 快进时间
        $this->advanceTime(1.0);

        // 验证定时器已执行
        $this->assertTrue($executed, '定时器应该在1秒后执行');
    }

    public function testRepeatingTimer(): void
    {
        $executionCount = 0;
        $executionTimes = [];
        // 添加重复定时器
        Timer::add(0.5, function () use (&$executionCount, &$executionTimes): void {
            ++$executionCount;
            $executionTimes[] = $this->getCurrentTime();
        }, [], true);

        // 快进2.5秒，应该执行5次
        $this->advanceTime(2.5);

        $this->assertEquals(5, $executionCount, '重复定时器应该执行5次');
        $this->assertCount(5, $executionTimes, '应该记录5次执行时间');

        // 验证执行间隔
        for ($i = 1; $i < count($executionTimes); ++$i) {
            $interval = $executionTimes[$i] - $executionTimes[$i - 1];
            $this->assertEqualsWithDelta(0.5, $interval, 0.01, '执行间隔应该是0.5秒');
        }
    }

    public function testTimerWithArguments(): void
    {
        $receivedArgs = null;

        Timer::add(1.0, function ($arg1, $arg2, $arg3) use (&$receivedArgs): void {
            $receivedArgs = [$arg1, $arg2, $arg3];
        }, ['hello', 123, true], false);

        $this->advanceTime(1.0);

        $this->assertEquals(['hello', 123, true], $receivedArgs, '定时器应该接收到正确的参数');
    }

    public function testTimerRemoval(): void
    {
        $executed = false;

        // 添加定时器
        $timerId = Timer::add(1.0, function () use (&$executed): void {
            $executed = true;
        }, [], false);

        // 立即删除定时器
        Timer::del($timerId);

        // 快进时间
        $this->advanceTime(1.0);

        // 验证定时器没有执行
        $this->assertFalse($executed, '被删除的定时器不应该执行');
    }

    public function testMultipleTimers(): void
    {
        $results = [];

        // 添加多个定时器，不同的延迟
        Timer::add(0.5, function () use (&$results): void {
            $results[] = 'timer1';
        }, [], false);

        Timer::add(1.0, function () use (&$results): void {
            $results[] = 'timer2';
        }, [], false);

        Timer::add(1.5, function () use (&$results): void {
            $results[] = 'timer3';
        }, [], false);

        // 逐步快进并检查执行顺序
        $this->advanceTime(0.5);
        $this->assertEquals(['timer1'], $results);

        $this->advanceTime(0.5);
        $this->assertEquals(['timer1', 'timer2'], $results);

        $this->advanceTime(0.5);
        $this->assertEquals(['timer1', 'timer2', 'timer3'], $results);
    }

    public function testTimerPrecision(): void
    {
        $startTime = $this->getCurrentTime();
        $executionTime = null;
        Timer::add(0.123, function () use (&$executionTime): void {
            $executionTime = $this->getCurrentTime();
        }, [], false);

        $this->advanceTime(0.123);

        $this->assertNotNull($executionTime, '定时器应该被执行');
        $this->assertEqualsWithDelta(
            0.123,
            $executionTime - $startTime,
            0.001,
            '定时器执行时间应该精确'
        );
    }

    public function testNestedTimers(): void
    {
        $results = [];

        Timer::add(1.0, function () use (&$results): void {
            $results[] = 'outer';

            // 在定时器回调中添加新的定时器
            Timer::add(0.5, function () use (&$results): void {
                $results[] = 'inner';
            }, [], false);
        }, [], false);

        // 快进1秒，执行外层定时器
        $this->advanceTime(1.0);
        $this->assertEquals(['outer'], $results);

        // 再快进0.5秒，执行内层定时器
        $this->advanceTime(0.5);
        $this->assertEquals(['outer', 'inner'], $results);
    }

    public function testTimerException(): void
    {
        $exceptionCaught = false;
        $secondTimerExecuted = false;

        // 第一个定时器抛出异常
        Timer::add(0.5, function (): void {
            throw new TimerException('定时器异常');
        }, [], false);

        // 第二个定时器应该正常执行
        Timer::add(1.0, function () use (&$secondTimerExecuted): void {
            $secondTimerExecuted = true;
        }, [], false);

        // 快进时间
        $this->advanceTime(1.0);

        // 第二个定时器应该仍然执行（异常被捕获）
        $this->assertTrue($secondTimerExecuted, '其他定时器应该不受异常影响');
    }

    public function testTimerPerformance(): void
    {
        $executionCount = 0;

        // 添加大量定时器
        for ($i = 0; $i < 1000; ++$i) {
            Timer::add(0.001 * $i, function () use (&$executionCount): void {
                ++$executionCount;
            }, [], false);
        }

        // 快进1秒
        $this->advanceTime(1.0);

        $this->assertEquals(1000, $executionCount, '所有定时器都应该被执行');
    }

    public function testTimerMemoryLeak(): void
    {
        $initialTimerCount = $this->getTimerCount();

        // 添加并删除大量定时器
        for ($i = 0; $i < 100; ++$i) {
            $timerId = Timer::add(1.0, function (): void {}, [], false);
            Timer::del($timerId);
        }

        $finalTimerCount = $this->getTimerCount();

        $this->assertEquals(
            $initialTimerCount,
            $finalTimerCount,
            '删除定时器后不应该有内存泄漏'
        );
    }

    public function testTimerOrderGuarantee(): void
    {
        $results = [];

        // 添加多个相同延迟的定时器
        for ($i = 0; $i < 10; ++$i) {
            Timer::add(1.0, function () use (&$results, $i): void {
                $results[] = $i;
            }, [], false);
        }

        $this->advanceTime(1.0);

        // 验证执行顺序（应该按添加顺序执行）
        $this->assertEquals([0, 1, 2, 3, 4, 5, 6, 7, 8, 9], $results);
    }

    public function testAsyncTimerChain(): void
    {
        $results = [];

        // 创建异步定时器链
        Timer::add(0.1, function () use (&$results): void {
            $results[] = 'step1';

            Timer::add(0.1, function () use (&$results): void {
                $results[] = 'step2';

                Timer::add(0.1, function () use (&$results): void {
                    $results[] = 'step3';
                }, [], false);
            }, [], false);
        }, [], false);

        // 等待足够时间让所有定时器执行
        $this->advanceTime(0.5);

        $this->assertEquals(['step1', 'step2', 'step3'], $results);
    }

    public function testTimerStateConsistency(): void
    {
        $timerCount = $this->getTimerCount();
        $this->assertEquals(0, $timerCount, '初始状态应该没有定时器');

        // 添加定时器
        $timerId1 = Timer::add(1.0, function (): void {}, [], false);
        $timerId2 = Timer::add(2.0, function (): void {}, [], true);

        $this->assertEquals(2, $this->getTimerCount(), '应该有2个定时器');

        // 执行第一个定时器
        $this->advanceTime(1.0);
        $this->assertEquals(1, $this->getTimerCount(), '应该剩余1个定时器');

        // 删除重复定时器
        Timer::del($timerId2);
        $this->assertEquals(0, $this->getTimerCount(), '应该没有定时器了');
    }
}
