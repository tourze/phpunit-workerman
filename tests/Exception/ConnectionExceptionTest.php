<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\ConnectionException;
use Tourze\PHPUnitWorkerman\Exception\PHPUnitWorkermanException;

/**
 * ConnectionException 测试
 * @internal
 */
#[CoversClass(ConnectionException::class)]
final class ConnectionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreation(): void
    {
        $exception = new ConnectionException();

        $this->assertInstanceOf(ConnectionException::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Test connection error';
        $exception = new ConnectionException($message);

        $this->assertSame($message, $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $code = 500;
        $exception = new ConnectionException('Test message', $code);

        $this->assertSame($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new ConnectionException('Test message', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExceptionInheritance(): void
    {
        $exception = new ConnectionException();

        $this->assertInstanceOf(PHPUnitWorkermanException::class, $exception);
    }
}
