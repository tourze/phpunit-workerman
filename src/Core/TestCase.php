<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Core;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Tourze\PHPUnitWorkerman\Utility\CommandLineHelper;

/**
 * Workerman 测试基础类
 *
 * 提供 Workerman 应用测试的基础设施
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * 测试开始前的清理工作
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetWorkermanGlobals();
    }

    /**
     * 重置 Workerman 全局状态
     *
     * Workerman 使用大量静态变量，测试间需要重置
     */
    protected function resetWorkermanGlobals(): void
    {
        $this->resetWorkerGlobals();
        $this->resetTimerGlobals();
    }

    /**
     * 重置 Worker 全局状态
     */
    private function resetWorkerGlobals(): void
    {
        if (!class_exists('\Workerman\Worker')) {
            return;
        }

        $reflection = new \ReflectionClass('\Workerman\Worker');
        $properties = [
            'workers',
            'pidMap',
            'status',
            'globalEvent',
            'masterPid',
            'gracefulStop',
        ];

        foreach ($properties as $property) {
            $this->resetWorkerProperty($reflection, $property);
        }
    }

    /**
     * 重置单个 Worker 属性
     */
    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function resetWorkerProperty(\ReflectionClass $reflection, string $property): void
    {
        if (!$reflection->hasProperty($property)) {
            return;
        }

        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        $value = $this->getWorkerPropertyDefaultValue($property);
        $prop->setValue(null, $value);
    }

    /**
     * 获取 Worker 属性的默认值
     */
    private function getWorkerPropertyDefaultValue(string $property): mixed
    {
        return match ($property) {
            'workers', 'pidMap' => [],
            'status', 'masterPid' => 0,
            'gracefulStop' => false,
            default => null,
        };
    }

    /**
     * 重置 Timer 全局状态
     */
    private function resetTimerGlobals(): void
    {
        if (!class_exists('\Workerman\Timer')) {
            return;
        }

        $reflection = new \ReflectionClass('\Workerman\Timer');

        if ($reflection->hasProperty('event')) {
            $prop = $reflection->getProperty('event');
            $prop->setAccessible(true);
            $prop->setValue(null, null);
        }
    }

    /**
     * 测试结束后的清理工作
     */
    protected function tearDown(): void
    {
        $this->resetWorkermanGlobals();
        $this->restoreArgv();
        parent::tearDown();
    }

    /**
     * 捕获 Worker 输出
     *
     * @param callable $callback 执行的回调
     *
     * @return string 捕获的输出
     */
    protected function captureWorkerOutput(callable $callback): string
    {
        ob_start();
        try {
            $callback();

            $output = ob_get_contents();

            return false !== $output ? $output : '';
        } finally {
            ob_end_clean();
        }
    }

    /**
     * 模拟命令行参数
     *
     * @param array<string> $mockArgv 命令行参数
     */
    protected function mockArgv(array $mockArgv): void
    {
        CommandLineHelper::setArgv($mockArgv);

        // 不需要手动增加断言计数
    }

    /**
     * 恢复原始的命令行参数
     */
    protected function restoreArgv(): void
    {
        CommandLineHelper::restoreArgv();
    }

    /**
     * 断言回调被调用
     *
     * @param callable $callback      要监控的回调
     * @param callable $trigger       触发回调的操作
     * @param int      $expectedCount 期望调用次数
     */
    protected function assertCallbackCalled(callable $callback, callable $trigger, int $expectedCount = 1): void
    {
        $callCount = 0;
        $wrapper = function (...$args) use ($callback, &$callCount) {
            ++$callCount;

            return $callback(...$args);
        };

        $trigger($wrapper);

        $this->assertEquals($expectedCount, $callCount, "回调应该被调用 {$expectedCount} 次，实际调用了 {$callCount} 次");
    }
}
