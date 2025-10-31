<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Mock;

use Workerman\Events\EventInterface;

/**
 * 模拟事件循环
 *
 * 为测试提供可控的事件循环实现，支持时间快进和事件模拟
 */
class MockEventLoop implements EventInterface
{
    /** @var array<int, callable> 读事件回调 */
    private array $readEvents = [];

    /** @var array<int, callable> 写事件回调 */
    private array $writeEvents = [];

    /** @var array<int, callable> 异常事件回调 */
    private array $exceptEvents = [];

    /** @var array<int, callable> 信号回调 */
    private array $signalEvents = [];

    /**
     * @var array<int, array{
     *     time: float,
     *     func: callable,
     *     args: array<mixed, mixed>,
     *     repeat: bool,
     *     interval: float,
     * }> 定时器
     */
    private array $timers = [];

    /** @var int 下一个定时器ID */
    private int $nextTimerId = 1;

    /** @var float 当前模拟时间 */
    private float $currentTime = 0.0;

    /** @var bool 是否正在运行 */
    private bool $running = false;

    /** @var array<int, resource> 已注册的流资源 */
    private array $streams = [];

    public function __construct()
    {
        $this->currentTime = microtime(true);
    }

    /**
     * 添加读事件监听
     * @param mixed $stream
     */
    public function onReadable($stream, callable $func): void
    {
        assert(is_resource($stream), 'Stream must be a valid resource');
        $key = (int) $stream;
        $this->readEvents[$key] = $func;
        $this->streams[$key] = $stream;
    }

    /**
     * 删除读事件监听
     * @param mixed $stream
     */
    public function offReadable($stream): bool
    {
        assert(is_resource($stream), 'Stream must be a valid resource');
        $key = (int) $stream;
        unset($this->readEvents[$key], $this->streams[$key]);

        return true;
    }

    /**
     * 添加写事件监听
     * @param mixed $stream
     */
    public function onWritable($stream, callable $func): void
    {
        assert(is_resource($stream), 'Stream must be a valid resource');
        $key = (int) $stream;
        $this->writeEvents[$key] = $func;
        $this->streams[$key] = $stream;
    }

    /**
     * 删除写事件监听
     * @param mixed $stream
     */
    public function offWritable($stream): bool
    {
        assert(is_resource($stream), 'Stream must be a valid resource');
        $key = (int) $stream;
        unset($this->writeEvents[$key], $this->streams[$key]);

        return true;
    }

    /**
     * 添加信号监听
     */
    public function onSignal(int $signal, callable $func): void
    {
        $this->signalEvents[$signal] = $func;
    }

    /**
     * 删除信号监听
     */
    public function offSignal(int $signal): bool
    {
        unset($this->signalEvents[$signal]);

        return true;
    }

    /**
     * 添加一次性定时器
     *
     * @param array<mixed, mixed> $args
     */
    public function delay(float $delay, callable $func, array $args = []): int
    {
        $timerId = $this->nextTimerId++;
        $this->timers[$timerId] = [
            'time' => $this->currentTime + $delay,
            'func' => $func,
            'args' => $args,
            'repeat' => false,
            'interval' => $delay,
        ];

        return $timerId;
    }

    /**
     * 添加重复定时器
     *
     * @param array<mixed, mixed> $args
     */
    public function repeat(float $interval, callable $func, array $args = []): int
    {
        $timerId = $this->nextTimerId++;
        $this->timers[$timerId] = [
            'time' => $this->currentTime + $interval,
            'func' => $func,
            'args' => $args,
            'repeat' => true,
            'interval' => $interval,
        ];

        return $timerId;
    }

    /**
     * 删除重复定时器
     */
    public function offRepeat(int $timerId): bool
    {
        return $this->offDelay($timerId);
    }

    /**
     * 删除定时器
     */
    public function offDelay(int $timerId): bool
    {
        unset($this->timers[$timerId]);

        return true;
    }

    /**
     * 运行事件循环（测试环境下通常不需要）
     */
    public function run(): void
    {
        $this->running = true;

        while ($this->running) {
            $this->tick();

            // 在测试环境中，通常手动控制循环
            if ([] === $this->timers && [] === $this->readEvents && [] === $this->writeEvents) {
                break;
            }

            // 避免无限循环
            usleep(1000);
        }
    }

    /**
     * 执行单次循环
     */
    public function tick(): void
    {
        $this->executeDueTimers();
    }

    /**
     * 执行到期的定时器
     */
    private function executeDueTimers(): void
    {
        foreach ($this->timers as $timerId => $timer) {
            if ($timer['time'] <= $this->currentTime) {
                // 执行定时器回调，捕获异常以避免影响其他定时器
                try {
                    call_user_func_array($timer['func'], $timer['args']);
                } catch (\Throwable $e) {
                    // 记录异常但继续执行其他定时器（模拟 Workerman 行为）
                    // 可以在这里记录错误日志
                }

                if ($timer['repeat']) {
                    // 重复定时器，安排下次执行时间
                    $this->timers[$timerId]['time'] = $this->currentTime + $timer['interval'];
                } else {
                    // 一次性定时器，删除
                    unset($this->timers[$timerId]);
                }
            }
        }
    }

    /**
     * 查找下一个定时器的执行时间
     */
    private function findNextTimerTime(): ?float
    {
        $nextTime = null;

        foreach ($this->timers as $timer) {
            if ($timer['time'] > $this->currentTime) {
                if (null === $nextTime || $timer['time'] < $nextTime) {
                    $nextTime = $timer['time'];
                }
            }
        }

        return $nextTime;
    }

    // === 测试专用方法 ===

    /**
     * 停止事件循环
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 获取事件循环名称
     */
    public function getTimerCount(): int
    {
        return count($this->timers);
    }

    /**
     * 时间快进
     *
     * @param float $seconds 快进的秒数
     */
    public function fastForward(float $seconds): void
    {
        $targetTime = $this->currentTime + $seconds;

        // 逐步推进时间，确保重复定时器正确执行
        while ($this->currentTime < $targetTime) {
            $nextTimerTime = $this->findNextTimerTime();

            if (null === $nextTimerTime || $nextTimerTime > $targetTime) {
                // 没有更多定时器或下一个定时器在目标时间之后
                $this->currentTime = $targetTime;
                break;
            }

            // 推进到下一个定时器时间
            $this->currentTime = $nextTimerTime;
            $this->executeDueTimers();
        }
    }

    /**
     * 触发读事件
     *
     * @param resource $stream 流资源
     */
    public function triggerRead($stream): void
    {
        $key = (int) $stream;
        if (isset($this->readEvents[$key])) {
            ($this->readEvents[$key])($stream);
        }
    }

    /**
     * 触发写事件
     *
     * @param resource $stream 流资源
     */
    public function triggerWrite($stream): void
    {
        $key = (int) $stream;
        if (isset($this->writeEvents[$key])) {
            ($this->writeEvents[$key])($stream);
        }
    }

    /**
     * 触发异常事件
     *
     * @param resource $stream 流资源
     */
    public function triggerExcept($stream): void
    {
        $key = (int) $stream;
        if (isset($this->exceptEvents[$key])) {
            ($this->exceptEvents[$key])($stream);
        }
    }

    /**
     * 触发信号
     *
     * @param int $signal 信号编号
     */
    public function triggerSignal(int $signal): void
    {
        if (isset($this->signalEvents[$signal])) {
            ($this->signalEvents[$signal])($signal);
        }
    }

    /**
     * 获取当前时间
     */
    public function getCurrentTime(): float
    {
        return $this->currentTime;
    }

    /**
     * 设置当前时间
     */
    public function setCurrentTime(float $time): void
    {
        $this->currentTime = $time;
    }

    /**
     * 获取所有定时器
     * @return array<int, array{
     *     time: float,
     *     func: callable,
     *     args: array<mixed, mixed>,
     *     repeat: bool,
     *     interval: float,
     * }>
     */
    public function getTimers(): array
    {
        return $this->timers;
    }

    /**
     * 清空所有事件和定时器
     */
    public function clear(): void
    {
        $this->readEvents = [];
        $this->writeEvents = [];
        $this->exceptEvents = [];
        $this->signalEvents = [];
        $this->timers = [];
        $this->streams = [];
        $this->running = false;
    }

    /**
     * 删除所有定时器
     */
    public function deleteAllTimer(): void
    {
        $this->timers = [];
    }

    /**
     * 设置错误处理器
     */
    public function setErrorHandler(callable $errorHandler): void
    {
        // 在测试环境中可以简单实现或忽略
    }

    /**
     * 检查是否有注册的读事件
     */
    public function hasReadEvents(): bool
    {
        return [] !== $this->readEvents;
    }

    /**
     * 检查是否有注册的写事件
     */
    public function hasWriteEvents(): bool
    {
        return [] !== $this->writeEvents;
    }

    /**
     * 检查是否有待执行的定时器
     */
    public function hasPendingTimers(): bool
    {
        return [] !== $this->timers;
    }

    /**
     * 检查是否有任何事件
     */
    public function hasEvents(): bool
    {
        return $this->hasReadEvents() || $this->hasWriteEvents() || $this->hasPendingTimers();
    }
}
