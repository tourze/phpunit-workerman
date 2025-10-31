<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\TimeControlException;

/**
 * @internal
 */
#[CoversClass(TimeControlException::class)]
final class TimeControlExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstantiation(): void
    {
        $exception = new TimeControlException();

        $this->assertInstanceOf(TimeControlException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Time control operation failed';
        $exception = new TimeControlException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Time control operation failed';
        $code = 3001;
        $exception = new TimeControlException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new TimeControlException('Current exception', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
