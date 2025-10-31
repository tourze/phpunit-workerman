<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Utility;

/**
 * 命令行参数辅助类
 *
 * 封装对命令行参数的操作，避免直接使用 $GLOBALS
 */
final class CommandLineHelper
{
    /** @var array<string>|null */
    private static ?array $originalArgv = null;

    /**
     * 获取当前的命令行参数
     *
     * @return array<string>
     */
    public static function getArgv(): array
    {
        $argv = $GLOBALS['argv'] ?? [];
        assert(is_array($argv));

        // 确保所有元素都是字符串类型
        return array_map(self::ensureString(...), $argv);
    }

    /**
     * 确保值为字符串类型
     */
    private static function ensureString(mixed $item): string
    {
        if (is_string($item)) {
            return $item;
        }

        if (is_scalar($item)) {
            return (string) $item;
        }

        if (is_object($item) && method_exists($item, '__toString')) {
            return (string) $item;
        }

        return '';
    }

    /**
     * 设置命令行参数
     *
     * @param array<string> $argv 新的命令行参数
     */
    public static function setArgv(array $argv): void
    {
        if (null === self::$originalArgv) {
            self::$originalArgv = self::getArgv();
        }

        $GLOBALS['argv'] = $argv;
    }

    /**
     * 恢复原始的命令行参数
     */
    public static function restoreArgv(): void
    {
        if (null !== self::$originalArgv) {
            $GLOBALS['argv'] = self::$originalArgv;
            self::$originalArgv = null;
        }
    }

    /**
     * 检查是否有已保存的原始参数
     */
    public static function hasStoredArgv(): bool
    {
        return null !== self::$originalArgv;
    }
}
