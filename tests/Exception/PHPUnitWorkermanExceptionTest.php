<?php

declare(strict_types=1);

namespace Tourze\PHPUnitWorkerman\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;
use Tourze\PHPUnitWorkerman\Exception\PHPUnitWorkermanException;

/**
 * @internal
 */
#[CoversClass(PHPUnitWorkermanException::class)]
class PHPUnitWorkermanExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCreation(): void
    {
        $exception = new class('Test message') extends PHPUnitWorkermanException {};

        $this->assertInstanceOf(PHPUnitWorkermanException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('Test message', $exception->getMessage());
    }

    public function testExceptionWithCode(): void
    {
        $exception = new class('Test message', 100) extends PHPUnitWorkermanException {};

        $this->assertSame(100, $exception->getCode());
    }

    public function testExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Previous exception');
        $exception = new class('Test message', 0, $previous) extends PHPUnitWorkermanException {};

        $this->assertSame($previous, $exception->getPrevious());
    }
}
