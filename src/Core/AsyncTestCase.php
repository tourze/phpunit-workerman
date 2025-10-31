<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Core;

use Tourze\PHPUnitWorkerman\Mock\MockEventLoop;
use Workerman\Timer;

/**
 * 异步测试基类
 *
 * 专门用于测试异步操作，提供时间控制和异步断言功能
 */
abstract class AsyncTestCase extends TestCase
{
    /** @var MockEventLoop 模拟事件循环 */
    protected MockEventLoop $eventLoop;

    /** @var float 测试开始时间 */
    protected float $testStartTime;

    /** @var array<int, array{time: float, assertion: callable(): void}> 延迟执行的断言 */
    protected array $delayedAssertions = [];

    /**
     * 测试初始化
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->eventLoop = new MockEventLoop();
        $this->testStartTime = microtime(true);
        $this->installMockTimer();
    }

    /**
     * 安装模拟定时器
     */
    protected function installMockTimer(): void
    {
        $reflection = new \ReflectionClass(Timer::class);
        if ($reflection->hasProperty('event')) {
            $property = $reflection->getProperty('event');
            $property->setAccessible(true);
            $property->setValue(null, $this->eventLoop);
        }
    }

    /**
     * 测试清理
     */
    protected function tearDown(): void
    {
        $this->runDelayedAssertions();
        $this->eventLoop->clear();
        $this->restoreTimer();

        parent::tearDown();
    }

    /**
     * 运行延迟的断言
     */
    protected function runDelayedAssertions(): void
    {
        foreach ($this->delayedAssertions as $delayedAssertion) {
            $timeToWait = $delayedAssertion['time'] - $this->eventLoop->getCurrentTime();
            if ($timeToWait > 0) {
                $this->advanceTime($timeToWait);
            }

            $assertion = $delayedAssertion['assertion'];
            $assertion();
        }

        $this->delayedAssertions = [];
    }

    /**
     * 时间快进
     *
     * @param float $seconds 快进秒数
     */
    protected function advanceTime(float $seconds): void
    {
        $this->eventLoop->fastForward($seconds);
    }

    /**
     * 获取当前模拟时间
     *
     * @return float 当前时间
     */
    protected function getCurrentTime(): float
    {
        return $this->eventLoop->getCurrentTime();
    }

    /**
     * 恢复原始定时器
     */
    protected function restoreTimer(): void
    {
        $reflection = new \ReflectionClass(Timer::class);
        if ($reflection->hasProperty('event')) {
            $property = $reflection->getProperty('event');
            $property->setAccessible(true);
            $property->setValue(null, null);
        }
    }

    /**
     * 执行异步操作并等待结果
     *
     * @param callable $asyncOperation 异步操作
     * @param float    $timeout        超时时间
     *
     * @return mixed 操作结果
     */
    protected function runAsync(callable $asyncOperation, float $timeout = 5.0)
    {
        $result = null;
        $completed = false;
        $error = null;

        $callback = function ($value = null) use (&$result, &$completed): void {
            $result = $value;
            $completed = true;
        };

        $errorCallback = function ($err) use (&$error, &$completed): void {
            $error = $err;
            $completed = true;
        };

        // 执行异步操作
        $executionResult = $this->executeAsyncOperation($asyncOperation, $callback, $errorCallback);
        if (null !== $executionResult['error']) {
            $error = $executionResult['error'];
            $completed = $executionResult['completed'];
        }

        // 等待完成
        $this->waitUntil(fn () => $completed, $timeout, 0.1, '异步操作超时');

        $this->throwIfAsyncError($error);

        return $result;
    }

    /**
     * 执行异步操作并处理异常
     * @return array{error: mixed, completed: bool}
     */
    private function executeAsyncOperation(
        callable $asyncOperation,
        callable $callback,
        callable $errorCallback,
    ): array {
        $error = null;
        $completed = false;

        try {
            $asyncOperation($callback, $errorCallback);
        } catch (\Throwable $e) {
            $error = $e;
            $completed = true;
        }

        return ['error' => $error, 'completed' => $completed];
    }

    /**
     * 如果存在异步错误则抛出异常
     * @param mixed $error
     * @throws \Throwable
     */
    private function throwIfAsyncError($error): void
    {
        if (null === $error) {
            return;
        }

        if ($error instanceof \Throwable) {
            throw $error;
        }

        $errorMessage = $this->formatErrorMessage($error);
        throw new \RuntimeException($errorMessage);
    }

    /**
     * 等待异步操作完成
     *
     * @param callable $condition 完成条件
     * @param float    $timeout   超时时间
     * @param float    $interval  检查间隔
     * @param string   $message   超时消息
     */
    protected function waitUntil(
        callable $condition,
        float $timeout = 5.0,
        float $interval = 0.1,
        string $message = '等待异步操作完成超时',
    ): void {
        $startTime = $this->eventLoop->getCurrentTime();

        while (!$condition()) {
            $this->advanceTime($interval);

            if ($this->eventLoop->getCurrentTime() - $startTime > $timeout) {
                throw new \RuntimeException($message);
            }
        }
    }

    /**
     * 并行执行多个异步操作
     *
     * @param array<callable> $operations 异步操作数组
     * @param float $timeout    超时时间
     *
     * @return array<mixed> 结果数组
     */
    protected function runAsyncParallel(array $operations, float $timeout = 5.0): array
    {
        $executionState = $this->executeParallelOperations($operations);

        // 等待所有操作完成
        $this->waitUntil(
            fn () => !in_array(false, $executionState['completed'], true),
            $timeout,
            0.1,
            '并行异步操作超时'
        );

        $this->throwFirstParallelError($executionState['errors']);

        return $executionState['results'];
    }

    /**
     * 执行并行操作
     * @param array<callable> $operations
     * @return array{results: array<mixed>, completed: array<bool>, errors: array<mixed>}
     */
    private function executeParallelOperations(array $operations): array
    {
        $results = [];
        $completed = array_fill(0, count($operations), false);
        $errors = [];

        foreach ($operations as $index => $operation) {
            $callback = function ($value = null) use (&$results, &$completed, $index): void {
                $results[$index] = $value;
                $completed[$index] = true;
            };

            $errorCallback = function ($err) use (&$errors, &$completed, $index): void {
                $errors[$index] = $err;
                $completed[$index] = true;
            };

            try {
                $operation($callback, $errorCallback);
            } catch (\Throwable $e) {
                $errors[$index] = $e;
                $completed[$index] = true;
            }
        }

        return [
            'results' => $results,
            'completed' => $completed,
            'errors' => $errors,
        ];
    }

    /**
     * 抛出并行操作中的第一个错误
     * @param array<mixed> $errors
     * @throws \Throwable
     */
    private function throwFirstParallelError(array $errors): void
    {
        if (0 === count($errors)) {
            return;
        }

        $firstError = reset($errors);
        if ($firstError instanceof \Throwable) {
            throw $firstError;
        }

        $errorMessage = $this->formatErrorMessage($firstError);
        throw new \RuntimeException($errorMessage);
    }

    /**
     * 移除定时器
     *
     * @param int $timerId 定时器ID
     */
    protected function removeTimer(int $timerId): void
    {
        $this->eventLoop->offDelay($timerId);
    }

    /**
     * 获取定时器数量
     *
     * @return int 定时器数量
     */
    protected function getTimerCount(): int
    {
        return $this->eventLoop->getTimerCount();
    }

    /**
     * 延迟执行断言
     *
     * @param float    $delay     延迟时间
     * @param callable $assertion 断言函数
     */
    protected function assertAfter(float $delay, callable $assertion): void
    {
        $this->delayedAssertions[] = [
            'time' => $this->eventLoop->getCurrentTime() + $delay,
            'assertion' => $assertion,
        ];
    }

    /**
     * 断言定时器在指定时间执行
     *
     * @param float    $expectedTime 期望执行时间
     * @param callable $timerSetup   设置定时器的函数
     * @param float    $tolerance    时间容差
     */
    protected function assertTimerExecutesAt(
        float $expectedTime,
        callable $timerSetup,
        float $tolerance = 0.1,
    ): void {
        $executed = false;
        $executionTime = null;

        $callback = function () use (&$executed, &$executionTime): void {
            $executed = true;
            $executionTime = $this->eventLoop->getCurrentTime();
        };

        $startTime = $this->eventLoop->getCurrentTime();
        $timerSetup($callback);

        // 快进到期望时间
        $this->advanceTime($expectedTime);

        $this->assertTrue($executed, '定时器应该被执行');
        $this->assertEqualsWithDelta(
            $startTime + $expectedTime,
            $executionTime,
            $tolerance,
            "定时器应该在 {$expectedTime} 秒后执行"
        );
    }

    /**
     * 断言重复定时器按间隔执行
     *
     * @param float    $interval      间隔时间
     * @param int      $expectedCount 期望执行次数
     * @param callable $timerSetup    设置定时器的函数
     */
    protected function assertRepeatedTimerExecutes(
        float $interval,
        int $expectedCount,
        callable $timerSetup,
    ): void {
        $executions = [];

        $callback = function () use (&$executions): void {
            $executions[] = $this->eventLoop->getCurrentTime();
        };

        $timerSetup($callback);

        // 执行足够长的时间
        $totalTime = $interval * $expectedCount + 0.1;
        $this->advanceTime($totalTime);

        $this->assertCount($expectedCount, $executions, "应该执行 {$expectedCount} 次");

        // 检查间隔
        for ($i = 1; $i < count($executions); ++$i) {
            $actualInterval = $executions[$i] - $executions[$i - 1];
            $this->assertEqualsWithDelta(
                $interval,
                $actualInterval,
                0.1,
                "第 {$i} 次执行的间隔应该是 {$interval}"
            );
        }
    }

    /**
     * 模拟异步 I/O 操作
     *
     * @param float         $duration 操作持续时间
     * @param mixed         $result   操作结果
     * @param callable|null $callback 完成回调
     */
    protected function simulateAsyncIO(float $duration, $result = null, ?callable $callback = null): void
    {
        $this->addTimer($duration, function () use ($result, $callback): void {
            if (null !== $callback) {
                $callback($result);
            }
        });
    }

    /**
     * 测试定时器
     *
     * @param float    $delay    延迟时间
     * @param callable $callback 回调函数
     * @param bool     $repeat   是否重复
     *
     * @return int 定时器ID
     */
    protected function addTimer(float $delay, callable $callback, bool $repeat = false): int
    {
        return $repeat ?
            $this->eventLoop->repeat($delay, $callback) :
            $this->eventLoop->delay($delay, $callback);
    }

    /**
     * 模拟网络请求
     *
     * @param float         $latency       网络延迟
     * @param mixed         $response      响应数据
     * @param float         $failureRate   失败率 (0-1)
     * @param callable|null $callback      完成回调
     * @param callable|null $errorCallback 错误回调
     */
    protected function simulateNetworkRequest(
        float $latency,
        $response = null,
        float $failureRate = 0.0,
        ?callable $callback = null,
        ?callable $errorCallback = null,
    ): void {
        $this->addTimer($latency, function () use ($response, $failureRate, $callback, $errorCallback): void {
            $this->executeNetworkRequest($response, $failureRate, $callback, $errorCallback);
        });
    }

    /**
     * 执行网络请求模拟
     * @param mixed $response
     */
    private function executeNetworkRequest(
        $response,
        float $failureRate,
        ?callable $callback,
        ?callable $errorCallback,
    ): void {
        if ($this->shouldSimulateFailure($failureRate)) {
            $this->handleNetworkFailure($errorCallback);
        } else {
            $this->handleNetworkSuccess($response, $callback);
        }
    }

    /**
     * 判断是否应该模拟失败
     */
    private function shouldSimulateFailure(float $failureRate): bool
    {
        return mt_rand() / mt_getrandmax() < $failureRate;
    }

    /**
     * 处理网络失败
     */
    private function handleNetworkFailure(?callable $errorCallback): void
    {
        if (null !== $errorCallback) {
            $errorCallback(new \RuntimeException('网络请求失败'));
        }
    }

    /**
     * 处理网络成功
     * @param mixed $response
     */
    private function handleNetworkSuccess($response, ?callable $callback): void
    {
        if (null !== $callback) {
            $callback($response);
        }
    }

    /**
     * 创建异步操作的 Promise 风格接口
     *
     * @param callable $executor 执行器函数
     *
     * @return array{0: callable, 1: callable, 2: callable} [成功回调, 失败回调, 等待完成函数]
     */
    protected function createPromise(callable $executor): array
    {
        $state = 'pending'; // pending, resolved, rejected
        $result = null;
        $error = null;

        $resolve = function ($value) use (&$state, &$result): void {
            if ('pending' === $state) {
                $state = 'resolved';
                $result = $value;
            }
        };

        $reject = function ($err) use (&$state, &$error): void {
            if ('pending' === $state) {
                $state = 'rejected';
                $error = $err;
            }
        };

        $wait = function (float $timeout = 5.0) use (&$state, &$result, &$error) {
            $this->waitUntil(
                fn () => 'pending' !== $state,
                $timeout,
                0.1,
                'Promise 超时'
            );

            $this->throwIfPromiseRejected($state, $error);

            return $result;
        };

        // 执行器
        try {
            $executor($resolve, $reject);
        } catch (\Throwable $e) {
            $reject($e);
        }

        return [$resolve, $reject, $wait];
    }

    /**
     * 如果Promise被拒绝则抛出异常
     * @param mixed $error
     * @throws \Throwable
     */
    private function throwIfPromiseRejected(string $state, $error): void
    {
        if ('rejected' !== $state) {
            return;
        }

        if ($error instanceof \Throwable) {
            throw $error;
        }

        $errorMessage = $this->formatErrorMessage($error);
        throw new \RuntimeException($errorMessage);
    }

    /**
     * 格式化错误消息
     * @param mixed $error
     */
    private function formatErrorMessage($error): string
    {
        if (is_string($error)) {
            return $error;
        }

        if (is_scalar($error)) {
            return (string) $error;
        }

        $encoded = json_encode($error);

        return false !== $encoded ? $encoded : 'Unknown error';
    }
}
