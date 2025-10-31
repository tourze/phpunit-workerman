<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Exception;

/**
 * 连接异常类
 */
class ConnectionException extends PHPUnitWorkermanException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
