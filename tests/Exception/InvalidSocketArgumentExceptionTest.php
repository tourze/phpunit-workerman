<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\InvalidSocketArgumentException;

/**
 * @internal
 */
#[CoversClass(InvalidSocketArgumentException::class)]
final class InvalidSocketArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstantiation(): void
    {
        $exception = new InvalidSocketArgumentException();

        $this->assertInstanceOf(InvalidSocketArgumentException::class, $exception);
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Invalid socket argument provided';
        $exception = new InvalidSocketArgumentException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Invalid socket argument provided';
        $code = 1001;
        $exception = new InvalidSocketArgumentException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new InvalidSocketArgumentException('Current exception', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
