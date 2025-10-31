<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\WorkerException;

/**
 * @internal
 */
#[CoversClass(WorkerException::class)]
final class WorkerExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionInstantiation(): void
    {
        $exception = new WorkerException();

        $this->assertInstanceOf(WorkerException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testExceptionWithMessage(): void
    {
        $message = 'Worker operation failed';
        $exception = new WorkerException($message);

        $this->assertEquals($message, $exception->getMessage());
    }

    public function testExceptionWithMessageAndCode(): void
    {
        $message = 'Worker operation failed';
        $code = 5001;
        $exception = new WorkerException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new WorkerException('Current exception', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }
}
