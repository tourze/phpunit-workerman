<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\SocketException;

/**
 * @internal
 */
#[CoversClass(SocketException::class)]
final class SocketExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstantiation(): void
    {
        $exception = new SocketException();

        $this->assertInstanceOf(SocketException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Socket operation failed';
        $exception = new SocketException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Socket operation failed';
        $code = 2001;
        $exception = new SocketException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new SocketException('Current exception', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
