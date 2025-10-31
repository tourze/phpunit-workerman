<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Trait;

use Tourze\PHPUnitWorkerman\Exception\TimeControlException;
use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;

/**
 * 时间控制 Trait
 *
 * 提供时间快进、定时器控制等测试功能
 */
trait TimeControl
{
    /** @var MockEventLoop|null 模拟事件循环实例 */
    private ?MockEventLoop $timeControlEventLoop = null;

    /**
     * 初始化时间控制
     *
     * @param MockEventLoop $eventLoop 事件循环实例
     */
    protected function initializeTimeControl(MockEventLoop $eventLoop): void
    {
        $this->timeControlEventLoop = $eventLoop;
    }

    /**
     * 快进并执行所有待执行的定时器
     *
     * @param float $maxTime 最大快进时间
     *
     * @return int 执行的定时器数量
     */
    protected function executeAllPendingTimers(float $maxTime = 3600.0): int
    {
        if (null === $this->timeControlEventLoop) {
            throw new TimeControlException('时间控制未初始化');
        }

        $executed = 0;
        $startTime = $this->timeControlEventLoop->getCurrentTime();

        while ($this->timeControlEventLoop->hasPendingTimers()) {
            if ($this->timeControlEventLoop->getCurrentTime() - $startTime > $maxTime) {
                break;
            }

            if ($this->fastForwardToNextTimer()) {
                ++$executed;
            } else {
                break;
            }
        }

        return $executed;
    }

    /**
     * 获取当前模拟时间
     *
     * @return float Unix 时间戳
     */
    protected function getCurrentTime(): float
    {
        if (null === $this->timeControlEventLoop) {
            throw new TimeControlException('时间控制未初始化');
        }

        return $this->timeControlEventLoop->getCurrentTime();
    }

    /**
     * 检查是否有待执行的定时器
     */
    protected function hasPendingTimers(): bool
    {
        return $this->getPendingTimerCount() > 0;
    }

    /**
     * 获取待执行的定时器数量
     *
     * @return int 定时器数量
     */
    protected function getPendingTimerCount(): int
    {
        if (null === $this->timeControlEventLoop) {
            return 0;
        }

        return $this->timeControlEventLoop->getTimerCount();
    }

    /**
     * 快进到下一个定时器
     *
     * @return bool 是否有定时器被执行
     */
    protected function fastForwardToNextTimer(): bool
    {
        if (null === $this->timeControlEventLoop) {
            throw new TimeControlException('时间控制未初始化');
        }

        $timers = $this->timeControlEventLoop->getTimers();
        if (0 === count($timers)) {
            return false;
        }

        // 找到最近的定时器时间
        $nextTime = min(array_column($timers, 'time'));
        $currentTime = $this->timeControlEventLoop->getCurrentTime();

        if ($nextTime > $currentTime) {
            $this->fastForward($nextTime - $currentTime);

            return true;
        }

        return false;
    }

    /**
     * 时间快进
     *
     * @param float $seconds 快进的秒数
     */
    protected function fastForward(float $seconds): void
    {
        if (null === $this->timeControlEventLoop) {
            throw new TimeControlException('时间控制未初始化，请先调用 initializeTimeControl()');
        }

        $this->timeControlEventLoop->fastForward($seconds);
    }

    /**
     * 设置当前时间
     *
     * @param float $time Unix 时间戳
     */
    protected function setCurrentTime(float $time): void
    {
        if (null === $this->timeControlEventLoop) {
            throw new TimeControlException('时间控制未初始化');
        }

        $this->timeControlEventLoop->setCurrentTime($time);
    }

    /**
     * 等待指定时间
     *
     * @param float $seconds 等待的秒数
     */
    protected function wait(float $seconds): void
    {
        $this->fastForward($seconds);
    }

    /**
     * 计算距离下一个定时器的剩余时间
     *
     * @return float|null 剩余时间（秒），如果没有定时器则返回 null
     */
    protected function getTimeToNextTimer(): ?float
    {
        $nextTime = $this->getNextTimerTime();
        if (null === $nextTime) {
            return null;
        }

        return max(0.0, $nextTime - $this->getCurrentTime());
    }

    /**
     * 获取下一个定时器的执行时间
     *
     * @return float|null 下一个定时器的时间，如果没有则返回 null
     */
    protected function getNextTimerTime(): ?float
    {
        if (null === $this->timeControlEventLoop) {
            return null;
        }

        $timers = $this->timeControlEventLoop->getTimers();
        if (0 === count($timers)) {
            return null;
        }

        return min(array_column($timers, 'time'));
    }

    /**
     * 断言在指定时间内定时器被执行
     *
     * @param float    $expectedTime  期望的执行时间
     * @param callable $timerCallback 定时器回调
     * @param float    $tolerance     时间容差
     */
    protected function assertTimerExecutedAt(float $expectedTime, callable $timerCallback, float $tolerance = 0.001): void
    {
        $executed = false;
        $executionTime = null;

        $wrapper = function (...$args) use ($timerCallback, &$executed, &$executionTime) {
            $executed = true;
            $executionTime = $this->getCurrentTime();

            return $timerCallback(...$args);
        };

        // 这里需要外部设置定时器并传入包装的回调
        // 然后快进到期望时间
        $this->fastForward($expectedTime);

        $this->assertTrue($executed, '定时器应该被执行');
        if (null === $executionTime) {
            throw new \RuntimeException('未记录到定时器执行时间');
        }
        $this->assertEqualsWithDelta(
            $expectedTime,
            $executionTime - $this->getCurrentTime() + $expectedTime,
            $tolerance,
            "定时器应该在 {$expectedTime} 秒时执行"
        );
    }

    /**
     * 断言定时器按预期间隔重复执行
     *
     * @param float    $interval   期望的间隔
     * @param int      $times      检查的次数
     * @param callable $setupTimer 设置定时器的回调
     * @param float    $tolerance  时间容差
     */
    protected function assertTimerRepeatsWithInterval(
        float $interval,
        int $times,
        callable $setupTimer,
        float $tolerance = 0.001,
    ): void {
        $executions = [];

        $wrapper = function (...$args) use (&$executions): void {
            $executions[] = $this->getCurrentTime();
        };

        $setupTimer($wrapper);

        // 快进多个间隔
        for ($i = 0; $i < $times; ++$i) {
            $this->fastForward($interval);
        }

        $this->assertCount($times, $executions, "应该执行 {$times} 次");

        // 检查间隔
        for ($i = 1; $i < count($executions); ++$i) {
            $actualInterval = $executions[$i] - $executions[$i - 1];
            $this->assertEqualsWithDelta(
                $interval,
                $actualInterval,
                $tolerance,
                "第 {$i} 次执行的间隔应该是 {$interval} 秒"
            );
        }
    }
}
