<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Trait;

/**
 * 异步断言 Trait
 *
 * 提供针对异步操作的专用断言方法
 */
trait AsyncAssertions
{
    /**
     * 断言异步回调被调用
     *
     * @param callable $trigger 触发异步操作的函数
     * @param float    $timeout 超时时间（秒）
     * @param string   $message 断言失败消息
     */
    protected function assertAsyncCallbackCalled(callable $trigger, float $timeout = 5.0, string $message = ''): void
    {
        $called = false;

        $callback = function (...$args) use (&$called): void {
            $called = true;
        };

        $trigger($callback);

        $this->waitForCondition(
            fn () => $called,
            $timeout,
            '' !== $message ? $message : '异步回调应该被调用'
        );
    }

    /**
     * 等待条件满足
     *
     * @param callable $condition 条件检查函数
     * @param float    $timeout   超时时间（秒）
     * @param string   $message   超时失败消息
     * @param float    $interval  检查间隔（秒）
     */
    protected function waitForCondition(
        callable $condition,
        float $timeout = 5.0,
        string $message = '等待条件超时',
        float $interval = 0.1,
    ): void {
        $startTime = $this->getCurrentTime();

        while (!$condition()) {
            $this->fastForward($interval);

            if ($this->getCurrentTime() - $startTime > $timeout) {
                self::fail($message . "（超时时间：{$timeout}秒）");
            }
        }
    }

    /**
     * 断言异步回调被调用指定次数
     *
     * @param int      $expectedCount 期望的调用次数
     * @param callable $trigger       触发异步操作的函数
     * @param float    $timeout       超时时间（秒）
     * @param string   $message       断言失败消息
     */
    protected function assertAsyncCallbackCalledTimes(
        int $expectedCount,
        callable $trigger,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $callCount = 0;

        $callback = function (...$args) use (&$callCount): void {
            ++$callCount;
        };

        $trigger($callback);

        $this->waitForCondition(
            fn () => $callCount >= $expectedCount,
            $timeout,
            '' !== $message ? $message : "异步回调应该被调用 {$expectedCount} 次"
        );

        $this->assertEquals(
            $expectedCount,
            $callCount,
            '' !== $message ? $message : "异步回调应该被调用 {$expectedCount} 次，实际调用了 {$callCount} 次"
        );
    }

    /**
     * 断言异步回调在指定时间内被调用
     *
     * @param float    $maxTime 最大时间（秒）
     * @param callable $trigger 触发异步操作的函数
     * @param string   $message 断言失败消息
     */
    protected function assertAsyncCallbackCalledWithin(
        float $maxTime,
        callable $trigger,
        string $message = '',
    ): void {
        $called = false;
        $callTime = null;
        $startTime = $this->getCurrentTime();

        $callback = function (...$args) use (&$called, &$callTime): void {
            $called = true;
            $callTime = $this->getCurrentTime();
        };

        $trigger($callback);

        $this->waitForCondition(
            fn () => $called,
            $maxTime,
            '' !== $message ? $message : "异步回调应该在 {$maxTime} 秒内被调用"
        );

        if (null === $callTime) {
            self::fail('异步回调未被调用，无法计算耗时');
        }

        $actualTime = $callTime - $startTime;
        $this->assertLessThanOrEqual(
            $maxTime,
            $actualTime,
            '' !== $message ? $message : "异步回调应该在 {$maxTime} 秒内被调用，实际用时 {$actualTime} 秒"
        );
    }

    /**
     * 断言异步回调不会被调用
     *
     * @param callable $trigger  触发异步操作的函数
     * @param float    $waitTime 等待时间（秒）
     * @param string   $message  断言失败消息
     */
    protected function assertAsyncCallbackNotCalled(
        callable $trigger,
        float $waitTime = 1.0,
        string $message = '',
    ): void {
        $called = false;

        $callback = function (...$args) use (&$called): void {
            $called = true;
        };

        $trigger($callback);

        // 等待指定时间
        $this->fastForward($waitTime);

        $this->assertFalse(
            $called,
            '' !== $message ? $message : '异步回调不应该被调用'
        );
    }

    /**
     * 断言异步操作返回期望值
     *
     * @param mixed    $expectedValue 期望的返回值
     * @param callable $trigger       触发异步操作的函数
     * @param float    $timeout       超时时间（秒）
     * @param string   $message       断言失败消息
     */
    protected function assertAsyncResult(
        $expectedValue,
        callable $trigger,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $result = null;
        $hasResult = false;

        $callback = function ($value) use (&$result, &$hasResult): void {
            $result = $value;
            $hasResult = true;
        };

        $trigger($callback);

        $this->waitForCondition(
            fn () => $hasResult,
            $timeout,
            '异步操作应该返回结果'
        );

        $this->assertEquals(
            $expectedValue,
            $result,
            '' !== $message ? $message : '异步操作应该返回期望的值'
        );
    }

    /**
     * 断言异步操作抛出异常
     *
     * @param class-string<\Throwable> $expectedExceptionClass 期望的异常类
     * @param callable                  $trigger                触发异步操作的函数
     * @param float                     $timeout                超时时间（秒）
     * @param string                    $message                断言失败消息
     */
    protected function assertAsyncException(
        string $expectedExceptionClass,
        callable $trigger,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $exception = null;
        $hasException = false;

        $callback = function (): void {
            // 正常回调，不应该被调用
        };

        $errorCallback = function ($error) use (&$exception, &$hasException): void {
            $exception = $error;
            $hasException = true;
        };

        $trigger($callback, $errorCallback);

        $this->waitForCondition(
            fn () => $hasException,
            $timeout,
            '异步操作应该抛出异常'
        );

        $this->assertInstanceOf(
            $expectedExceptionClass,
            $exception,
            '' !== $message ? $message : "异步操作应该抛出 {$expectedExceptionClass} 异常"
        );
    }

    /**
     * 断言多个异步操作都完成
     *
     * @param array<int, callable> $triggers 触发异步操作的函数数组
     * @param float                 $timeout  超时时间（秒）
     * @param string                $message  断言失败消息
     */
    protected function assertAllAsyncCompleted(
        array $triggers,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $completed = array_fill(0, count($triggers), false);

        foreach ($triggers as $index => $trigger) {
            $callback = function (...$args) use (&$completed, $index): void {
                $completed[$index] = true;
            };

            \call_user_func($trigger, $callback);
        }

        $this->waitForCondition(
            fn () => !in_array(false, $completed, true),
            $timeout,
            '' !== $message ? $message : '所有异步操作都应该完成'
        );
    }

    /**
     * 断言异步操作按顺序完成
     *
     * @param array<int, callable> $triggers 按顺序的触发函数数组
     * @param float                 $timeout  超时时间（秒）
     * @param string                $message  断言失败消息
     */
    protected function assertAsyncCompletionOrder(
        array $triggers,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $completionOrder = [];

        foreach ($triggers as $index => $trigger) {
            $callback = function (...$args) use (&$completionOrder, $index): void {
                $completionOrder[] = $index;
            };

            \call_user_func($trigger, $callback);
        }

        $this->waitForCondition(
            fn () => count($completionOrder) === count($triggers),
            $timeout,
            '所有异步操作都应该完成'
        );

        $expectedOrder = array_keys($triggers);
        $this->assertEquals(
            $expectedOrder,
            $completionOrder,
            '' !== $message ? $message : '异步操作应该按预期顺序完成'
        );
    }

    /**
     * 断言异步流程的状态变化
     *
     * @param array<int, mixed> $expectedStates 期望的状态序列
     * @param callable          $trigger        触发异步操作的函数
     * @param float             $timeout        超时时间（秒）
     * @param string            $message        断言失败消息
     */
    protected function assertAsyncStateTransition(
        array $expectedStates,
        callable $trigger,
        float $timeout = 5.0,
        string $message = '',
    ): void {
        $actualStates = [];

        $stateCallback = function ($state) use (&$actualStates): void {
            $actualStates[] = $state;
        };

        $trigger($stateCallback);

        $this->waitForCondition(
            fn () => count($actualStates) >= count($expectedStates),
            $timeout,
            '异步状态变化应该完成'
        );

        $this->assertEquals(
            $expectedStates,
            array_slice($actualStates, 0, count($expectedStates)),
            '' !== $message ? $message : '异步状态变化应该符合预期'
        );
    }
}
