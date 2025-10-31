<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Examples;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitWorkerman\Core\RealWorkerTestCase;
use Workerman\Timer;

/**
 * 简单的定时器测试
 *
 * @internal
 */
#[CoversClass(Timer::class)]
final class SimpleTimerTest extends RealWorkerTestCase
{
    public function testBasicTimer(): void
    {
        $executed = false;

        Timer::add(1.0, function () use (&$executed): void {
            $executed = true;
        }, [], false);

        // 快进时间触发定时器
        $this->fastForward(1.1);

        $this->assertTrue($executed, 'Timer should execute');
    }

    public function testRepeatingTimer(): void
    {
        $count = 0;

        $timerId = Timer::add(0.5, function () use (&$count): void {
            ++$count;
        }, [], true);

        // 快进2秒，应该执行4次
        $this->fastForward(2.0);

        $this->assertEquals(4, $count, 'Repeating timer should execute 4 times');

        // 删除定时器
        Timer::del($timerId);

        // 再快进，不应该再执行
        $this->fastForward(1.0);
        $this->assertEquals(4, $count, 'Timer should stop after deletion');
    }

    public function testTimerWithWorker(): void
    {
        $worker = $this->createRealWorker('tcp://127.0.0.1:8080');

        $messages = [];

        $worker->onWorkerStart = function ($w) use (&$messages): void {
            // 在Worker启动后添加定时器
            Timer::add(0.5, function () use (&$messages): void {
                $messages[] = 'timer_tick';
            }, [], true);
        };

        $this->startRealWorker($worker);

        // 快进时间
        $this->fastForward(1.5);

        $this->assertCount(3, $messages, 'Timer should execute 3 times in 1.5 seconds');
        $this->assertEquals(['timer_tick', 'timer_tick', 'timer_tick'], $messages);
    }
}
