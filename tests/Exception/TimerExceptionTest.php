<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\TimerException;

/**
 * @internal
 */
#[CoversClass(TimerException::class)]
final class TimerExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstantiation(): void
    {
        $exception = new TimerException();

        $this->assertInstanceOf(TimerException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Timer operation failed';
        $exception = new TimerException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Timer operation failed';
        $code = 4001;
        $exception = new TimerException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new TimerException('Current exception', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
